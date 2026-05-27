<?php

namespace App\Http\Controllers\PaperBuilder;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\SavedPaper;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WizardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $gradesQuery = Grade::query()->orderBy('number');

        if ($user->hasRole('teacher')) {
            $perms = $user->teacherPermission;
            $allowed = $perms?->allowed_grades ?? null;
            if (is_array($allowed) && count($allowed)) {
                $gradesQuery->whereIn('number', $allowed);
            }
        }

        return Inertia::render('PaperBuilder/Wizard', [
            'grades' => $gradesQuery->get(['id', 'number', 'label_en', 'label_ur']),
            'teacherPermissions' => $user->hasRole('teacher') ? ($user->teacherPermission?->toArray() ?? null) : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'config' => ['required', 'array'],
            'config.question_ids' => ['required', 'array', 'min:1'],
        ]);

        $institution = Institution::find($request->user()->institution_id);

        $paper = SavedPaper::create([
            'institution_id' => $request->user()->institution_id,
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'institute_snapshot' => $institution ? [
                'name' => $institution->name,
                'logo_path' => $institution->logo_path,
                'city' => $institution->city,
                'phone' => $institution->phone,
            ] : null,
            'config_snapshot' => $validated['config'],
            'layout_snapshot' => $validated['config']['layout'] ?? null,
            'status' => 'saved',
        ]);

        ActivityLogger::log($request, 'paper.created', ['paper_id' => $paper->id]);

        return redirect()->route('editor.show', $paper);
    }
}
