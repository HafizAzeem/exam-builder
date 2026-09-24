<?php

namespace App\Http\Controllers\PaperBuilder;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaperBuilder\AddTeacherAIQuestionsToPaperRequest;
use App\Http\Requests\PaperBuilder\StoreTeacherAIGenerateRequest;
use App\Http\Requests\PaperBuilder\StoreTeacherAIManualQuestionRequest;
use App\Http\Requests\PaperBuilder\StoreTeacherAIPasteRequest;
use App\Jobs\ProcessGenerateQuestionsJob;
use App\Jobs\ProcessUploadedDocumentJob;
use App\Models\AIImport;
use App\Models\AIImportQuestion;
use App\Models\AISetting;
use App\Models\Grade;
use App\Models\Subject;
use App\Services\AIImport\AIImportService;
use App\Services\AIImport\QuestionImportService;
use App\Services\QuestionBankService;
use App\Support\CurriculumLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('PaperBuilder/AI/Paste', [
            ...$context,
            'boards' => CurriculumLookup::boards()->get(['id', 'name']),
        ]);
    }

    public function storePaste(StoreTeacherAIPasteRequest $request): RedirectResponse
    {
        $this->assertTeacherScope($request, $request->integer('grade_id'), $request->integer('subject_id'));

        $data = $request->validated();
        $data['chapter_ids'] = array_values(array_map('intval', $data['chapter_ids']));

        $import = $this->imports->createFromPastedText($data, $data['raw_text'], $request->user()->id);

        $settings = AISetting::current();
        if ($settings->enable_queue) {
            ProcessUploadedDocumentJob::dispatch($import);
        } else {
            ProcessUploadedDocumentJob::dispatchSync($import);
        }

        return redirect()
            ->route('builder.ai.status', $import)
            ->with('success', 'Text submitted. AI is extracting questions.');
    }

    public function generateForm(Request $request): Response|RedirectResponse
    {
        $context = $this->resolveBuilderContext($request);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return Inertia::render('PaperBuilder/AI/Generate', [
            ...$context,
            'boards' => CurriculumLookup::boards()->get(['id', 'name']),
            'defaultLanguage' => strcasecmp((string) ($context['subject']['name_en'] ?? ''), 'Urdu') === 0
                ? 'urdu'
                : 'english',
        ]);
    }

    public function storeGenerate(StoreTeacherAIGenerateRequest $request): RedirectResponse
    {
        $this->assertTeacherScope($request, $request->integer('grade_id'), $request->integer('subject_id'));

        $data = $request->validated();
        $data['chapter_ids'] = array_values(array_map('intval', $data['chapter_ids']));

        $subject = Subject::query()->find($data['subject_id']);
        if ($subject && strcasecmp($subject->name_en, 'Urdu') === 0 && ($data['language'] ?? '') === 'english') {
            $data['language'] = 'urdu';
        }

        $data['content_source'] = $data['content_source'] ?? 'exercise';
        $data['book_type'] = match ($data['content_source']) {
            'past_paper' => 'past_paper',
            'online_practice' => 'additional_questions',
            default => 'text_book',
        };

        $import = $this->imports->createFromGenerate($data, $request->user()->id);
        ProcessGenerateQuestionsJob::dispatchFor($import);

        return redirect()
            ->route('builder.ai.status', $import)
            ->with('success', 'AI is preparing your questions.');
    }

    public function status(Request $request, AIImport $import): Response
    {
        $this->authorizeTeacherImport($request, $import);

        $import->load(['grade:id,number,label_en', 'subject:id,name_en']);

        return Inertia::render('PaperBuilder/AI/Status', [
            'aiImport' => $import,
            'statusPayload' => $import->statusPayload(),
            'composeQuery' => $this->composeQuery($import),
        ]);
    }

    public function statusJson(Request $request, AIImport $import)
    {
        $this->authorizeTeacherImport($request, $import);

        return response()->json($import->statusPayload());
    }

    public function review(Request $request, AIImport $import): Response|RedirectResponse
    {
        $this->authorizeTeacherImport($request, $import);

        if (! in_array($import->status, ['review', 'completed', 'importing'], true)) {
            return redirect()->route('builder.ai.status', $import);
        }

        $this->imports->constrainChapters($import);

        $questions = $import->questions()
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
            ]);

        $import->load(['grade:id,number,label_en', 'subject:id,name_en']);

        return Inertia::render('PaperBuilder/AI/Review', [
            'aiImport' => $import,
            'questions' => $questions,
            'chapters' => CurriculumLookup::chapters($import->subject_id)
                ->whereIn('id', $import->chapterIds() ?: [0])
                ->get(['id', 'number', 'title_en']),
            'composeQuery' => $this->composeQuery($import),
            'paperLanguage' => $import->language,
        ]);
    }

    public function searchBank(Request $request, AIImport $import)
    {
        $this->authorizeTeacherImport($request, $import);

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

        $data = $request->validated();
        $allowedChapters = $import->chapterIds();
        abort_unless(in_array((int) $data['chapter_id'], $allowedChapters, true), 422, 'Invalid chapter.');

        $staging = AIImportQuestion::query()->create([
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

    public function addBankQuestionsToSelection(Request $request, AIImport $import)
    {
        $this->authorizeTeacherImport($request, $import);

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
        $allowedGrades = config('exam.teacher_ai_grade_numbers', [9, 10, 11, 12]);

        if (! $grade || ! $subject || ! $grade->is_active || ! $subject->is_active) {
            return redirect()->route('builder');
        }

        if (! in_array($grade->number, $allowedGrades, true)) {
            return redirect()->route('builder.create', [
                'grade' => $gradeId,
                'subject' => $subjectId,
                'chapters' => implode(',', $chapterIds),
            ])->with('error', 'AI paper tools are available for classes 9–12.');
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

        abort_unless(
            $user && (int) $import->user_id === (int) $user->id,
            403
        );

        abort_unless(
            in_array($import->mode, ['paste', 'generate'], true),
            403
        );
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
