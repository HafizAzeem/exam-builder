<?php

namespace App\Http\Controllers\PaperBuilder;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaperBuilder\AddTeacherAIQuestionsToPaperRequest;
use App\Http\Requests\PaperBuilder\StoreTeacherAIManualQuestionRequest;
use App\Http\Requests\PaperBuilder\StoreTeacherAIPasteRequest;
use App\Http\Requests\PaperBuilder\UpdateTeacherAIQuestionRequest;
use App\Jobs\ProcessQuestionAssistantJob;
use App\Models\AIImport;
use App\Models\AIImportQuestion;
use App\Models\AISetting;
use App\Models\AssistantRequest;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\SyllabusEdition;
use App\Services\AIImport\AIImportService;
use App\Services\AIImport\QuestionImportService;
use App\Services\QuestionAssistant\AssistantAccess;
use App\Services\QuestionAssistant\AssistantBudgetService;
use App\Services\QuestionAssistant\AssistantContextResolver;
use App\Services\QuestionAssistant\AssistantUploadValidator;
use App\Services\QuestionBankService;
use App\Support\CurriculumLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class TeacherAIController extends Controller
{
    public function __construct(
        protected AIImportService $imports,
        protected QuestionImportService $questionImport,
        protected QuestionBankService $questionBank,
    ) {}

    public function pasteForm(Request $request): Response|RedirectResponse
    {
        $context = $this->resolveBuilderContext($request);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sources = $request->user()->hasRole('teacher') ? ($request->user()->teacherPermission?->allowed_categories ?? ['exercise', 'additional', 'past_paper']) : ['exercise', 'additional', 'past_paper'];
        $bookTypes = array_values(array_intersect(['text_book', 'additional_questions', 'past_paper'], array_map(fn ($s) => ['exercise' => 'text_book', 'additional' => 'additional_questions', 'past_paper' => 'past_paper'][$s], $sources)));
        $defaultBookType = in_array('additional_questions', $bookTypes, true) ? 'additional_questions' : ($bookTypes[0] ?? 'additional_questions');
        app(AssistantAccess::class)->assert($request->user(), ['grade_id' => $context['grade']['id'], 'subject_id' => $context['subject']['id'], 'chapter_ids' => $context['selectedChapterIds'], 'book_type' => $defaultBookType]);

        return Inertia::render('PaperBuilder/AI/Paste', [
            ...$context,
            'boards' => CurriculumLookup::boards()->get(['id', 'name']),
            'assistantUsage' => app(AssistantBudgetService::class)->summary($request->user()->institution),
            'defaultBoard' => $request->user()->institution?->board?->name ?? '',
            'curriculumYear' => SyllabusEdition::where('subject_id', $context['subject']['id'])->orderByDesc('academic_session')->value('academic_session'),
            'contextConfirmation' => $request->session()->get('assistant_context_confirmation'),
            'allowedBookTypes' => $bookTypes,
            'defaultBookType' => $defaultBookType,
        ]);
    }

    public function storePaste(StoreTeacherAIPasteRequest $request): RedirectResponse
    {
        $data = $request->validated();
        app(AssistantAccess::class)->assert($request->user(), $data);
        $proposal = app(AssistantContextResolver::class)->proposal($data);
        if ($proposal) {
            $confirmed = $request->session()->get('assistant_context_confirmation');
            if (! $request->boolean('confirm_context') || ! $confirmed || ! hash_equals($proposal['text_hash'], $confirmed['text_hash'] ?? '')) {
                return back()->with('assistant_context_confirmation', $proposal)->withErrors(['raw_text' => 'Confirm the detected curriculum context before submitting.']);
            }
            $data['grade_id'] = $proposal['grade']['id'];
            $data['subject_id'] = $proposal['subject']['id'];
            $data['chapter_ids'] = $proposal['chapter_ids'];
            app(AssistantAccess::class)->assert($request->user(), $data);
        }
        $text = null;
        $file = $request->file('file');
        if ($file) {
            $text = app(AssistantUploadValidator::class)->validate($file);
        }
        $fingerprint = hash('sha256', json_encode(collect($data)->except(['request_key', 'file', 'confirm_context'])->all()).($file ? hash_file('sha256', $file->getRealPath()) : ''));
        $budget = app(AssistantBudgetService::class);
        // Identical completed uploads by this teacher reuse their preview without another provider call.
        if ($file) {
            $cached = AIImport::where('user_id', $request->user()->id)->where('meta->assistant_fingerprint', $fingerprint)->whereIn('status', ['review', 'completed'])->first();
            if ($cached && AssistantRequest::where('ai_import_id', $cached->id)->where('status', 'completed')->exists()) {
                return redirect()->route('builder.ai.review', $cached);
            }
        }
        [$reservation,$import,$created] = DB::transaction(function () use ($request, $data, $budget, $file, $text, $fingerprint) {
            $reservation = $budget->reserve($request->user(), $data['request_key'], $data['mode']);
            if ($reservation->ai_import_id) {
                $import = AIImport::findOrFail($reservation->ai_import_id);
                abort_unless(hash_equals($import->meta['assistant_fingerprint'] ?? '', $fingerprint), 409, 'This request reference was already used for different content.');

                return [$reservation, $import, false];
            }
            $import = $file ? $this->imports->createFromUpload($data, $file, $request->user()->id) : $this->imports->createFromPastedText($data, $data['raw_text'], $request->user()->id);
            $meta = array_merge($import->meta ?? [], [
                'assistant' => true, 'institution_id' => $request->user()->institution_id, 'assistant_request_id' => $reservation->id, 'assistant_fingerprint' => $fingerprint,
                'question_count' => (int) $data['question_count'], 'assistant_mode' => $data['mode'], 'assistant_stage' => 'queued',
                'instructions' => $data['mode'] === 'search' ? mb_substr($data['raw_text'], 0, 2000) : '',
                'curriculum_year' => SyllabusEdition::where('subject_id', $data['subject_id'])->orderByDesc('academic_session')->value('academic_session') ?? now()->year,
            ]);
            if ($text !== null) {
                $meta['extracted_text'] = $text;
            }
            $import->update(['meta' => $meta]);
            $budget->attachImport($reservation, $import);

            return [$reservation, $import, true];
        });
        if ($created) {
            try {
                if (AISetting::current()->enable_queue) {
                    ProcessQuestionAssistantJob::dispatch($reservation->id)->afterCommit();
                } else {
                    ProcessQuestionAssistantJob::dispatchSync($reservation->id);
                }
            } catch (\Throwable $e) {
                $budget->fail($reservation, 'Assistant processing could not start. Your request was refunded.');
                report($e);
            }
        }

        return redirect()->route('builder.ai.status', $import);
    }

    public function generateForm(Request $request): RedirectResponse
    {
        return redirect()->route('builder.ai.paste', $request->query());
    }

    public function storeGenerate(Request $request): RedirectResponse
    {
        abort(410, 'Smart Generate has been replaced. Submit through Question Assistant to apply school limits and sourced questions.');
    }

    public function cancel(Request $request, AIImport $import): RedirectResponse
    {
        $this->authorizeTeacherImport($request, $import);
        $row = AssistantRequest::where('ai_import_id', $import->id)->firstOrFail();
        app(AssistantBudgetService::class)->cancel($row);
        if ($row->fresh()->status === 'canceled') {
            $import->update(['status' => 'failed', 'error_message' => 'Canceled. Your school request was refunded.']);
        }

        return redirect()->route('builder.ai.status', $import);
    }

    public function extractPastPaperForm(Request $request): RedirectResponse
    {
        return redirect()
            ->route('builder.ai.paste', $request->query())
            ->with('success', 'Paste the past paper text and set source type to Past paper.');
    }

    public function storeExtractPastPaper(Request $request): RedirectResponse
    {
        abort(410, 'Submit text or a bounded upload through Question Assistant.');
    }

    public function status(Request $request, AIImport $import): Response
    {
        $this->authorizeTeacherImport($request, $import);

        $import->load(['grade:id,number,label_en', 'subject:id,name_en']);

        return Inertia::render('PaperBuilder/AI/Status', [
            'aiImport' => $import,
            'statusPayload' => $this->assistantStatus($import),
            'assistantRequest' => $this->assistantRow($import),
            'composeQuery' => $this->composeQuery($import),
        ]);
    }

    public function statusJson(Request $request, AIImport $import)
    {
        $this->authorizeTeacherImport($request, $import);

        return response()->json($this->assistantStatus($import));
    }

    public function review(Request $request, AIImport $import): Response|RedirectResponse
    {
        $this->authorizeTeacherImport($request, $import);

        if (! in_array($import->status, ['review', 'completed', 'importing'], true)) {
            return redirect()->route('builder.ai.status', $import);
        }

        if (($import->meta['assistant'] ?? false) && ($this->assistantRow($import)['status'] ?? '') !== 'completed') {
            return redirect()->route('builder.ai.status', $import);
        }
        $this->imports->constrainChapters($import);

        $questions = $import->questions()
            ->whereNotIn('status', ['rejected'])
            ->orderBy('id')
            ->get([
                'id',
                'type',
                'source',
                'text_en',
                'text_ur',
                'mcq_options',
                'parts',
                'chapter_id',
                'status',
                'is_duplicate',
                'duplicate_of_question_id',
                'estimated_marks',
                'difficulty',
                'match_status',
                'source_url', 'source_published_at',
            ]);

        $import->load(['grade:id,number,label_en', 'subject:id,name_en']);

        return Inertia::render('PaperBuilder/AI/Review', [
            'aiImport' => $import,
            'questions' => $questions,
            'assistantRequest' => $this->assistantRow($import),
            'chapters' => CurriculumLookup::chapters($import->subject_id)
                ->whereIn('id', $import->chapterIds() ?: [0])
                ->get(['id', 'number', 'title_en']),
            'composeQuery' => $this->composeQuery($import),
            'paperLanguage' => $import->language,
            'reusedFromDatabase' => (bool) $request->session()->get('past_paper_reused'),
        ]);
    }

    public function searchBank(Request $request, AIImport $import)
    {
        $this->authorizeTeacherImport($request, $import);
        abort_if($import->meta['assistant'] ?? false, 422, 'Insert only questions from the reviewed assistant preview.');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'in:mcq,short,long,fill,truefalse'],
            'chapter_id' => ['nullable', 'integer'],
        ]);

        $chapterIds = $import->chapterIds();
        if ($chapterIds === []) {
            return response()->json(['data' => []]);
        }

        if (! empty($validated['chapter_id'])) {
            $chapterIds = in_array((int) $validated['chapter_id'], $chapterIds, true)
                ? [(int) $validated['chapter_id']]
                : [];
            if ($chapterIds === []) {
                return response()->json(['data' => []]);
            }
        }

        // Scoped bank browse for review fill-in (allowed even when system bank is off on Compose).
        $rows = $this->questionBank->fetchAll(
            $chapterIds,
            $validated['type'] ?? null,
            [],
            $validated['search'] ?? null,
            500,
            [],
            null,
            null,
        );

        return response()->json(['data' => $rows]);
    }

    public function storeManualQuestion(StoreTeacherAIManualQuestionRequest $request, AIImport $import)
    {
        $this->authorizeTeacherImport($request, $import);
        abort_if($import->meta['assistant'] ?? false, 422, 'Insert only questions from the reviewed assistant preview.');

        $data = $request->validated();
        $allowedChapters = $import->chapterIds();
        abort_unless(in_array((int) $data['chapter_id'], $allowedChapters, true), 422, 'Invalid chapter.');

        $staging = AIImportQuestion::query()->create([
            'submitted_by' => $request->user()->id,
            'contributor_board_id' => $request->user()->institution?->board_id,
            'contributed_at' => now(),
            'ai_import_id' => $import->id,
            'chapter_id' => (int) $data['chapter_id'],
            'match_status' => 'manual',
            'type' => $data['type'],
            'source' => $import->sourceForBookType(),
            'text_en' => $data['text_en'] ?? null,
            'text_ur' => $data['text_ur'] ?? null,
            'mcq_options' => $data['type'] === 'mcq' ? ($data['mcq_options'] ?? null) : null,
            'parts' => null,
            'status' => 'pending',
            'is_duplicate' => false,
            'estimated_marks' => $data['estimated_marks'] ?? null,
        ]);

        $import->refreshCounters();

        return response()->json([
            'question' => $staging->fresh(),
        ], 201);
    }

    public function updateQuestion(UpdateTeacherAIQuestionRequest $request, AIImport $import, AIImportQuestion $question)
    {
        $this->authorizeTeacherImport($request, $import);
        abort_unless((int) $question->ai_import_id === (int) $import->id, 404);

        $chapterId = (int) $request->validated('chapter_id');
        abort_unless(in_array($chapterId, $import->chapterIds(), true), 422, 'Invalid chapter.');

        $question->update([
            'chapter_id' => $chapterId,
            'match_status' => 'manual',
        ]);

        return response()->json([
            'question' => $question->fresh(),
        ]);
    }

    public function removeQuestion(Request $request, AIImport $import, AIImportQuestion $question)
    {
        $this->authorizeTeacherImport($request, $import);
        abort_unless((int) $question->ai_import_id === (int) $import->id, 404);

        $question->update(['status' => 'rejected']);
        $import->refreshCounters();

        return response()->json(['ok' => true]);
    }

    public function addBankQuestionsToSelection(Request $request, AIImport $import)
    {
        $this->authorizeTeacherImport($request, $import);
        abort_if($import->meta['assistant'] ?? false, 422, 'Insert only questions from the reviewed assistant preview.');

        $validated = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer'],
        ]);

        $bankQuestions = $this->questionBank->fetchByIds($validated['question_ids']);
        $fallbackChapter = $import->chapterIds()[0] ?? null;
        $created = [];

        foreach ($bankQuestions as $q) {
            $chapterId = in_array((int) $q->chapter_id, $import->chapterIds(), true)
                ? (int) $q->chapter_id
                : $fallbackChapter;

            if (! $chapterId) {
                continue;
            }

            $mcq = null;
            $opt = $q->mcqOptions;
            if ($q->type === 'mcq' && $opt) {
                $mcq = [
                    'option_a_en' => $opt->option_a_en,
                    'option_b_en' => $opt->option_b_en,
                    'option_c_en' => $opt->option_c_en,
                    'option_d_en' => $opt->option_d_en,
                    'option_a_ur' => $opt->option_a_ur,
                    'option_b_ur' => $opt->option_b_ur,
                    'option_c_ur' => $opt->option_c_ur,
                    'option_d_ur' => $opt->option_d_ur,
                    'correct_option' => $opt->correct_option,
                ];
            }

            $created[] = AIImportQuestion::query()->create([
                'ai_import_id' => $import->id,
                'chapter_id' => $chapterId,
                'topic_id' => $q->topic_id,
                'match_status' => 'manual',
                'type' => $q->type,
                'source' => $q->source,
                'text_en' => $q->text_en,
                'text_ur' => $q->text_ur,
                'mcq_options' => $mcq,
                'status' => 'pending',
                'is_duplicate' => false,
                'estimated_marks' => $q->estimated_marks,
                'review_notes' => 'Copied from bank #'.$q->id,
            ]);
        }

        $import->refreshCounters();

        return response()->json([
            'questions' => collect($created)->map->fresh()->values(),
        ]);
    }

    public function addToPaper(AddTeacherAIQuestionsToPaperRequest $request, AIImport $import): RedirectResponse
    {
        $this->authorizeTeacherImport($request, $import);
        if ($import->meta['assistant'] ?? false) {
            abort_unless(($this->assistantRow($import)['status'] ?? '') === 'completed', 409, 'This assistant request is not available for insertion.');
        }

        $stagingIds = array_values(array_map('intval', $request->validated('staging_ids')));

        // Ensure chapter_id before import
        $fallback = $import->chapterIds()[0] ?? null;
        if ($fallback) {
            $import->questions()
                ->whereIn('id', $stagingIds)
                ->whereNull('chapter_id')
                ->update(['chapter_id' => $fallback, 'match_status' => 'manual']);
        }

        $import->update(['status' => 'importing']);

        $result = $this->questionImport->importSelected($import, $stagingIds);

        $import->refreshCounters();
        $this->imports->markStatus($import->fresh(), 'completed');

        $questionIds = $result['question_ids'] ?? [];

        Session::flash('builder_pending_question_ids', $questionIds);
        Session::flash('success', count($questionIds)
            ? count($questionIds).' question(s) added to your paper and saved to the bank.'
            : 'No questions were added. Check selections and try again.');

        return redirect()->route('builder.create', $this->composeQuery($import));
    }

    /**
     * @return array<string, mixed>|RedirectResponse
     */
    protected function resolveBuilderContext(Request $request): array|RedirectResponse
    {
        $user = $request->user();
        $gradeId = $request->integer('grade');
        $subjectId = $request->integer('subject');
        $chapterIds = collect(explode(',', (string) $request->query('chapters', '')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $grade = Grade::query()->find($gradeId);
        $subject = Subject::query()->where('id', $subjectId)->where('grade_id', $gradeId)->first();
        $allowedGrades = config('exam.teacher_ai_grade_numbers', [5, 6, 7, 8, 9, 10, 11, 12]);

        if (! $grade || ! $subject || ! $grade->is_active || ! $subject->is_active) {
            return redirect()->route('builder');
        }

        if (! in_array($grade->number, $allowedGrades, true)) {
            return redirect()->route('builder.create', [
                'grade' => $gradeId,
                'subject' => $subjectId,
                'chapters' => implode(',', $chapterIds),
            ])->with('error', 'AI paper tools are available for classes 5–12.');
        }

        $this->assertTeacherScope($request, $gradeId, $subjectId);

        $validChapterIds = CurriculumLookup::chapters($subjectId)
            ->whereIn('id', $chapterIds ?: [0])
            ->pluck('id')
            ->all();

        if (! $validChapterIds) {
            return redirect()->route('builder.chapters', [
                'grade' => $gradeId,
                'subject' => $subjectId,
            ]);
        }

        return [
            'grade' => [
                'id' => $grade->id,
                'number' => $grade->number,
                'label_en' => $grade->label_en,
            ],
            'subject' => [
                'id' => $subject->id,
                'name_en' => $subject->name_en,
                'name_ur' => $subject->name_ur,
            ],
            'chapters' => CurriculumLookup::chapters($subjectId)
                ->whereIn('id', $validChapterIds)
                ->get(['id', 'number', 'title_en', 'title_ur']),
            'selectedChapterIds' => $validChapterIds,
            'composeQuery' => [
                'grade' => $gradeId,
                'subject' => $subjectId,
                'chapters' => implode(',', $validChapterIds),
            ],
            'teacherPermissions' => $user->hasRole('teacher') ? ($user->teacherPermission?->toArray() ?? null) : null,
        ];
    }

    protected function composeQuery(AIImport $import): array
    {
        $chapterIds = $import->chapterIds();

        return [
            'grade' => $import->grade_id,
            'subject' => $import->subject_id,
            'chapters' => implode(',', $chapterIds),
        ];
    }

    protected function authorizeTeacherImport(Request $request, AIImport $import): void
    {
        $user = $request->user();
        if ($import->meta['assistant'] ?? false) {
            abort_unless($user && (int) $import->user_id === (int) $user->id && (int) ($import->meta['institution_id'] ?? 0) === (int) $user->institution_id, 403);
            app(AssistantAccess::class)->assert($user, ['grade_id' => $import->grade_id, 'subject_id' => $import->subject_id, 'chapter_ids' => $import->chapterIds(), 'book_type' => $import->book_type]);

            return;
        }

        abort_unless(
            $user && (
                (int) $import->user_id === (int) $user->id
                || ($user->institution_id && (int) $user->institution_id === (int) $import->user?->institution_id && $import->book_type === 'past_paper' && in_array($import->status, ['review', 'completed', 'importing', 'processing', 'uploaded'], true))
            ),
            403
        );
        $this->assertTeacherScope($request, (int) $import->grade_id, (int) $import->subject_id);
        if ($user->hasRole('teacher') && is_array($user->teacherPermission?->allowed_categories)) {
            abort_unless(in_array($import->sourceForBookType(), $user->teacherPermission->allowed_categories, true), 403);
        }
    }

    protected function assistantRow(AIImport $import): ?array
    {
        if (! ($import->meta['assistant'] ?? false)) {
            return null;
        }
        $row = AssistantRequest::where('ai_import_id', $import->id)->first();
        if ($row && $row->expires_at->isPast() && in_array($row->status, ['reserved', 'processing'], true)) {
            app(AssistantBudgetService::class)->fail($row, 'The assistant request expired. Your request has been refunded.');
            $row->refresh();
        }

        return $row?->only(['id', 'mode', 'status', 'error_message']);
    }

    protected function assistantStatus(AIImport $import): array
    {
        $payload = $import->statusPayload();
        $row = $this->assistantRow($import);
        if ($row) {
            $payload['assistant_stage'] = $import->fresh()->meta['assistant_stage'] ?? 'queued';
            $payload['assistant_request'] = $row;
            if (in_array($row['status'], ['canceled', 'failed'], true)) {
                $payload['status'] = $row['status'];
                $payload['error_message'] = $row['error_message'];
            }
        }

        return $payload;
    }

    protected function assertTeacherScope(Request $request, int $gradeId, int $subjectId): void
    {
        $user = $request->user();
        if (! $user?->hasRole('teacher')) {
            return;
        }

        $grade = Grade::query()->find($gradeId);
        $allowedGrades = $user->teacherPermission?->allowed_grades;
        if (is_array($allowedGrades) && count($allowedGrades) && $grade && ! in_array($grade->number, $allowedGrades, true)) {
            abort(403);
        }

        $allowedSubjects = $user->teacherPermission?->allowed_subjects;
        if (is_array($allowedSubjects) && count($allowedSubjects) && ! in_array($subjectId, $allowedSubjects, true)) {
            abort(403);
        }
    }
}
