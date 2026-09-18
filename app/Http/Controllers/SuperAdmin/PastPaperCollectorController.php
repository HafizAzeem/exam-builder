<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StorePastPaperCollectionRequest;
use App\Models\AIPaperCollection;
use App\Models\AIPaperSource;
use App\Services\PastPaperCollector\PastPaperCollectionService;
use App\Support\CurriculumLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PastPaperCollectorController extends Controller
{
    public function __construct(
        protected PastPaperCollectionService $collections,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $collections = AIPaperCollection::query()
            ->with(['grade:id,number,label_en', 'subject:id,name_en', 'user:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('SuperAdmin/PastPaperCollector/History', [
            'collections' => $collections,
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $grades = CurriculumLookup::grades()->get(['id', 'number', 'label_en']);
        $subjects = CurriculumLookup::subjects()->get(['id', 'grade_id', 'name_en', 'name_ur']);
        $boards = CurriculumLookup::boards()->get(['id', 'name']);

        return Inertia::render('SuperAdmin/PastPaperCollector/Search', [
            'grades' => $grades,
            'subjects' => $subjects,
            'boards' => $boards,
            'defaults' => [
                'board' => $boards->first()?->name ?: '',
                'country' => 'Pakistan',
                'paper_type' => 'complete',
                'language' => 'english',
                'max_results' => 10,
                'year' => (int) date('Y') - 1,
            ],
        ]);
    }

    public function store(StorePastPaperCollectionRequest $request): RedirectResponse
    {
        $collection = $this->collections->create($request->validated(), (int) $request->user()->id);

        return redirect()
            ->route('super-admin.past-paper-collector.show', $collection)
            ->with('success', 'Past paper collection started.');
    }

    public function show(AIPaperCollection $collection): Response
    {
        $this->authorize('view', $collection);

        $collection->load([
            'grade',
            'subject',
            'import',
            'sources' => fn ($q) => $q->orderBy('id'),
            'user:id,name',
        ]);

        return Inertia::render('SuperAdmin/PastPaperCollector/Show', [
            'collection' => $collection,
            'status' => $this->collections->statusPayload($collection),
        ]);
    }

    public function status(AIPaperCollection $collection): JsonResponse
    {
        $this->authorize('view', $collection);

        return response()->json($this->collections->statusPayload($collection->fresh(['sources'])));
    }

    public function source(AIPaperCollection $collection, AIPaperSource $source): Response
    {
        $this->authorize('view', $collection);
        abort_unless($source->ai_paper_collection_id === $collection->id, 404);

        $source->load(['questions' => fn ($q) => $q->orderBy('id')->limit(100)]);

        return Inertia::render('SuperAdmin/PastPaperCollector/Source', [
            'collection' => $collection->load(['grade', 'subject']),
            'source' => $source,
        ]);
    }

    public function retry(AIPaperCollection $collection): RedirectResponse
    {
        $this->authorize('update', $collection);
        $this->collections->retryFailed($collection);

        return back()->with('success', 'Retry queued for failed/OCR-required sources.');
    }

    public function retrySource(AIPaperCollection $collection, AIPaperSource $source): RedirectResponse
    {
        $this->authorize('update', $collection);
        abort_unless($source->ai_paper_collection_id === $collection->id, 404);

        $this->collections->retrySource($source);

        return back()->with('success', 'Source retry queued.');
    }
}
