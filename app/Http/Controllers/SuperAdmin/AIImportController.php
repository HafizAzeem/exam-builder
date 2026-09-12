<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreAIImportPasteRequest;
use App\Http\Requests\SuperAdmin\StoreAIImportRequest;
use App\Jobs\ProcessUploadedDocumentJob;
use App\Models\AIImport;
use App\Models\AISetting;
use App\Models\Grade;
use App\Models\Subject;
use App\Services\AIImport\AIImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIImportController extends Controller
{
    public function __construct(
        protected AIImportService $imports,
    ) {}

    public function dashboard(): Response
    {
        $this->authorize('viewAny', AIImport::class);

        return Inertia::render('SuperAdmin/AIImport/Dashboard', [
            'stats' => $this->imports->dashboardStats(),
            'recent' => AIImport::query()
                ->with(['grade:id,label_en,number', 'subject:id,name_en', 'user:id,name'])
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', AIImport::class);

        return Inertia::render('SuperAdmin/AIImport/Upload', [
            'grades' => Grade::query()->orderBy('number')->get(['id', 'number', 'label_en']),
            'subjects' => Subject::query()->orderBy('name_en')->get(['id', 'name_en', 'grade_id']),
        ]);
    }

    public function store(StoreAIImportRequest $request): RedirectResponse
    {
        $this->authorize('create', AIImport::class);

        $import = $this->imports->createFromUpload(
            $request->validated(),
            $request->file('file'),
            $request->user()->id,
        );

        $settings = AISetting::current();

        if ($settings->enable_queue) {
            ProcessUploadedDocumentJob::dispatch($import);
        } else {
            ProcessUploadedDocumentJob::dispatchSync($import);
        }

        return redirect()
            ->route('super-admin.ai-import.show', $import)
            ->with('success', 'File uploaded. AI processing has started.');
    }

    public function pasteForm(): Response
    {
        $this->authorize('create', AIImport::class);

        return Inertia::render('SuperAdmin/AIImport/Paste', [
            'grades' => Grade::query()->orderBy('number')->get(['id', 'number', 'label_en']),
            'subjects' => Subject::query()->orderBy('name_en')->get(['id', 'name_en', 'grade_id']),
        ]);
    }

    public function storePaste(StoreAIImportPasteRequest $request): RedirectResponse
    {
        $this->authorize('create', AIImport::class);

        $import = $this->imports->createFromPastedText(
            $request->validated(),
            $request->validated('raw_text'),
            $request->user()->id,
        );

        $settings = AISetting::current();

        if ($settings->enable_queue) {
            ProcessUploadedDocumentJob::dispatch($import);
        } else {
            ProcessUploadedDocumentJob::dispatchSync($import);
        }

        return redirect()
            ->route('super-admin.ai-import.show', $import)
            ->with('success', 'Pasted text submitted. AI is classifying and organizing questions.');
    }

    public function show(AIImport $import): Response
    {
        $this->authorize('view', $import);
        $import->load(['grade', 'subject', 'user']);

        return Inertia::render('SuperAdmin/AIImport/Show', [
            'aiImport' => $import,
        ]);
    }

    public function status(AIImport $import)
    {
        $this->authorize('view', $import);

        $import->refresh();

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'progress_percent' => $import->progress_percent,
            'total_chunks' => $import->total_chunks,
            'processed_chunks' => $import->processed_chunks,
            'questions_found' => $import->questions_found,
            'approved_count' => $import->approved_count,
            'rejected_count' => $import->rejected_count,
            'imported_count' => $import->imported_count,
            'failed_count' => $import->failed_count,
            'duplicate_count' => $import->duplicate_count,
            'error_message' => $import->error_message,
        ]);
    }

    public function destroy(AIImport $import): RedirectResponse
    {
        $this->authorize('delete', $import);

        if ($import->stored_path) {
            Storage::disk($this->imports->disk())->delete($import->stored_path);
        }

        $import->delete();

        return redirect()
            ->route('super-admin.ai-import.dashboard')
            ->with('success', 'Import deleted.');
    }

    public function download(AIImport $import): StreamedResponse
    {
        $this->authorize('view', $import);

        return Storage::disk($this->imports->disk())->download(
            $import->stored_path,
            $import->original_filename,
        );
    }
}
