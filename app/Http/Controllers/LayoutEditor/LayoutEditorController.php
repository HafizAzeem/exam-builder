<?php

namespace App\Http\Controllers\LayoutEditor;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\SavedPaper;
use App\Services\PaperExportService;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LayoutEditorController extends Controller
{
    public function __construct(
        protected PaperExportService $paperExport,
    ) {}

    public function show(Request $request, SavedPaper $paper): Response
    {
        abort_unless($paper->institution_id === $request->user()->institution_id, 403);

        $institution = Institution::find($request->user()->institution_id);

        return Inertia::render('LayoutEditor/Editor', [
            'savedPaper' => $paper,
            'preview' => $this->paperExport->buildPreviewData($paper, $institution),
            'headerTemplates' => range(1, 7),
        ]);
    }

    public function update(Request $request, SavedPaper $paper)
    {
        abort_unless($paper->institution_id === $request->user()->institution_id, 403);

        $validated = $request->validate([
            'layout_snapshot' => ['required', 'array'],
        ]);

        $paper->update(['layout_snapshot' => $validated['layout_snapshot']]);

        ActivityLogger::log($request, 'paper.layout_updated', ['paper_id' => $paper->id]);

        return back();
    }

    public function print(Request $request, SavedPaper $paper): Response
    {
        abort_unless($paper->institution_id === $request->user()->institution_id, 403);

        $institution = Institution::find($request->user()->institution_id);
        ActivityLogger::log($request, 'paper.printed', ['paper_id' => $paper->id]);

        return Inertia::render('LayoutEditor/Print', [
            'preview' => $this->paperExport->buildPreviewData($paper, $institution),
        ]);
    }
}
