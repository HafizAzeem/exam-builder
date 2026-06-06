<?php

namespace App\Http\Controllers\LayoutEditor;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\SavedPaper;
use App\Jobs\GeneratePdfJob;
use App\Services\PaperExportService;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
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
        $pdfPath = "papers/{$paper->institution_id}/{$paper->id}.pdf";
        $pdfUrl = \Storage::disk('public')->exists($pdfPath)
            ? \Storage::disk('public')->url($pdfPath)
            : null;

        return Inertia::render('LayoutEditor/Editor', [
            'savedPaper' => $paper,
            'preview' => $this->paperExport->buildPreviewData($paper, $institution),
            'headerTemplates' => range(1, 7),
            'pdfUrl' => $pdfUrl,
        ]);
    }

    public function update(Request $request, SavedPaper $paper)
    {
        abort_unless($paper->institution_id === $request->user()->institution_id, 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:500'],
            'exam_meta' => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
            'layout_snapshot' => ['required', 'array'],
        ]);

        $config = $paper->config_snapshot ?? [];

        if (isset($validated['exam_meta'])) {
            $config['exam_meta'] = array_merge($config['exam_meta'] ?? [], $validated['exam_meta']);
        }

        if (isset($validated['settings'])) {
            $config['settings'] = array_merge($config['settings'] ?? [], $validated['settings']);
        }

        $paper->fill([
            'title' => $validated['title'] ?? $paper->title,
            'config_snapshot' => $config,
            'layout_snapshot' => $validated['layout_snapshot'],
        ]);
        $paper->save();

        ActivityLogger::log($request, 'paper.saved', ['paper_id' => $paper->id]);

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

    public function signedPrint(Request $request, SavedPaper $paper): Response
    {
        // Signed URL is the authorization here (for Puppeteer).
        $institution = $paper->institute_snapshot ?? null;

        return Inertia::render('LayoutEditor/Print', [
            'preview' => $this->paperExport->buildPreviewData($paper, null),
            'institutionOverride' => $institution,
        ]);
    }

    public function uploadWatermarkImage(Request $request, SavedPaper $paper)
    {
        abort_unless($paper->institution_id === $request->user()->institution_id, 403);

        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $path = $request->file('image')->store(
            "watermarks/{$paper->institution_id}/{$paper->id}",
            'public',
        );

        $layout = $paper->layout_snapshot ?? [];
        $layout['watermark_image_path'] = $path;
        $layout['watermark_type'] = 'image';
        $layout['enable_watermark'] = true;
        $paper->layout_snapshot = $layout;
        $paper->save();

        return back()->with('watermark_upload', ['path' => $path]);
    }

    public function pdf(Request $request, SavedPaper $paper)
    {
        abort_unless($paper->institution_id === $request->user()->institution_id, 403);

        $signedUrl = URL::temporarySignedRoute(
            'paper.print.signed',
            now()->addMinutes(10),
            ['paper' => $paper->id]
        );

        dispatch(new GeneratePdfJob($paper, $signedUrl));

        ActivityLogger::log($request, 'paper.pdf_requested', ['paper_id' => $paper->id]);

        return back()->with('success', 'PDF generation started. Refresh in a minute to download.');
    }
}
