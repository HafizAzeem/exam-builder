<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\BulkReviewRequest;
use App\Http\Requests\SuperAdmin\MergeAIImportQuestionRequest;
use App\Http\Requests\SuperAdmin\UpdateAIImportQuestionRequest;
use App\Jobs\ImportApprovedQuestionsJob;
use App\Models\AIImport;
use App\Models\AIImportQuestion;
use App\Models\AISetting;
use App\Models\Question;
use App\Services\AIImport\DuplicateDetectionService;
use App\Services\AIImport\QuestionMergeService;
use App\Support\CurriculumLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function __construct(
        protected DuplicateDetectionService $duplicates,
        protected QuestionMergeService $merger,
    ) {}

    public function show(Request $request, AIImport $import): Response
    {
        $this->authorize('view', $import);

        $status = $request->string('status')->toString();
        if ($status === '') {
            $status = 'pending';
        }

        $statusCounts = [
            'pending' => $import->questions()->where('status', 'pending')->count(),
            'approved' => $import->questions()->where('status', 'approved')->count(),
            'rejected' => $import->questions()->where('status', 'rejected')->count(),
            'imported' => $import->questions()->where('status', 'imported')->count(),
            'all' => $import->questions()->count(),
        ];

        $questions = $import->questions()
            ->with([
                'chapter:id,number,title_en',
                'topic:id,code,title_en',
                'paperSource:id,url,title,status,extracted_text',
                'duplicateOf:id,text_en,text_ur,type,chapter_id,topic_id,difficulty,estimated_marks',
            ])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('match_status'), fn ($q) => $q->where('match_status', $request->string('match_status')))
            ->when($request->boolean('duplicates_only'), fn ($q) => $q->where('is_duplicate', true))
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $chapters = CurriculumLookup::chapters($import->subject_id)
            ->get(['id', 'number', 'title_en']);

        $topics = CurriculumLookup::topics()
            ->whereIn('chapter_id', $chapters->pluck('id'))
            ->get(['id', 'chapter_id', 'code', 'title_en']);

        return Inertia::render('SuperAdmin/AIImport/Review', [
            'aiImport' => $import->load(['grade', 'subject']),
            'questions' => $questions,
            'chapters' => $chapters,
            'topics' => $topics,
            'statusCounts' => $statusCounts,
            'filters' => array_merge(
                $request->only(['match_status', 'duplicates_only']),
                ['status' => $status],
            ),
        ]);
    }

    public function update(
        UpdateAIImportQuestionRequest $request,
        AIImport $import,
        AIImportQuestion $question,
    ): RedirectResponse {
        $this->authorize('update', $import);
        abort_unless($question->ai_import_id === $import->id, 404);

        $data = $request->validated();

        if (array_key_exists('chapter_id', $data) && $data['chapter_id']) {
            $data['match_status'] = 'manual';
        }

        $question->update($data);
        $this->duplicates->markIfDuplicate($question->fresh());
        $import->refreshCounters();

        return back()->with('success', 'Question updated.');
    }

    public function approve(AIImport $import, AIImportQuestion $question): RedirectResponse
    {
        $this->authorize('update', $import);
        abort_unless($question->ai_import_id === $import->id, 404);

        if (! $question->chapter_id) {
            return back()->with('error', 'Map a chapter before approving.');
        }

        if ($this->duplicates->markIfDuplicate($question->fresh())) {
            $import->refreshCounters();

            return back()->with('error', 'Duplicate detected — not approved for import.');
        }

        $question->update(['status' => 'approved', 'match_status' => $question->match_status === 'unmatched_chapter' ? 'manual' : $question->match_status]);
        $import->refreshCounters();

        return back()->with('success', 'Question approved.');
    }

    public function reject(AIImport $import, AIImportQuestion $question): RedirectResponse
    {
        $this->authorize('update', $import);
        abort_unless($question->ai_import_id === $import->id, 404);

        $question->update(['status' => 'rejected']);
        $import->refreshCounters();

        return back()->with('success', 'Question rejected.');
    }

    public function merge(
        MergeAIImportQuestionRequest $request,
        AIImport $import,
        AIImportQuestion $question,
    ): RedirectResponse {
        $this->authorize('update', $import);
        abort_unless($question->ai_import_id === $import->id, 404);

        $existing = Question::query()->findOrFail($request->integer('existing_question_id'));
        $this->merger->merge($question, $existing, $import);
        $import->refreshCounters();
        $import->paperCollection?->refreshCounters();

        return back()->with('success', 'Question merged into existing record #'.$existing->id);
    }

    public function bulk(BulkReviewRequest $request, AIImport $import): RedirectResponse
    {
        $this->authorize('update', $import);

        $ids = $request->validated('ids');
        $action = $request->validated('action');

        $questions = $import->questions()->whereIn('id', $ids)->get();

        if ($action === 'edit') {
            $payload = array_filter([
                'chapter_id' => $request->validated('chapter_id'),
                'topic_id' => $request->validated('topic_id'),
                'type' => $request->validated('type'),
                'source' => $request->validated('source'),
                'status' => $request->validated('status'),
            ], fn ($v) => $v !== null);

            if (isset($payload['chapter_id'])) {
                $payload['match_status'] = 'manual';
            }

            foreach ($questions as $question) {
                $question->update($payload);
                $this->duplicates->markIfDuplicate($question->fresh());
            }

            $import->refreshCounters();

            return back()->with('success', 'Bulk edit applied.');
        }

        $updated = 0;
        foreach ($questions as $question) {
            if ($action === 'approve') {
                if (! $question->chapter_id) {
                    continue;
                }
                if ($this->duplicates->markIfDuplicate($question->fresh())) {
                    continue;
                }
                $question->update(['status' => 'approved']);
                $updated++;
            } else {
                $question->update(['status' => 'rejected']);
                $updated++;
            }
        }

        $import->refreshCounters();

        return back()->with('success', "Bulk {$action} applied to {$updated} question(s).");
    }

    public function importApproved(AIImport $import): RedirectResponse
    {
        $this->authorize('update', $import);

        $ready = $import->questions()
            ->where('status', 'approved')
            ->where('is_duplicate', false)
            ->whereNotNull('chapter_id')
            ->count();

        if ($ready === 0) {
            return back()->with('error', 'No approved, non-duplicate questions ready to import.');
        }

        $settings = AISetting::current();
        if ($settings->enable_queue) {
            ImportApprovedQuestionsJob::dispatch($import);
        } else {
            ImportApprovedQuestionsJob::dispatchSync($import);
        }

        return redirect()
            ->route('super-admin.ai-import.show', $import)
            ->with('success', 'Import of approved questions has started.');
    }
}
