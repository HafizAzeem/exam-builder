<?php

namespace App\Http\Controllers\PaperBuilder;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\SavedPaper;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WizardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('PaperBuilder/Wizard', [
            'grades' => Grade::query()->orderBy('number')->get(['id', 'number', 'label_en', 'label_ur']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'config' => ['required', 'array'],
            'config.question_ids' => ['required', 'array', 'min:1'],
        ]);

        $paper = SavedPaper::create([
            'institution_id' => $request->user()->institution_id,
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'config_snapshot' => $validated['config'],
            'layout_snapshot' => $validated['config']['layout'] ?? null,
            'status' => 'saved',
        ]);

        ActivityLogger::log($request, 'paper.created', ['paper_id' => $paper->id]);

        return redirect()->route('editor.show', $paper);
    }
}
