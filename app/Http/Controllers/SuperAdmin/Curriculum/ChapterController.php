<?php

namespace App\Http\Controllers\SuperAdmin\Curriculum;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChapterController extends Controller
{
    public function index(Subject $subject): Response
    {
        $subject->load('grade');

        $chapters = Chapter::query()
            ->where('subject_id', $subject->id)
            ->withCount(['topics', 'questions'])
            ->orderBy('number')
            ->get();

        return Inertia::render('SuperAdmin/Curriculum/Chapters', [
            'subject' => $subject,
            'chapters' => $chapters,
        ]);
    }

    public function store(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'number' => [
                'required',
                'integer',
                'min:1',
                'max:50',
                Rule::unique('chapters', 'number')->where(fn ($q) => $q->where('subject_id', $subject->id)),
            ],
            'title_en' => ['required', 'string', 'max:500'],
            'title_ur' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $subject->chapters()->create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Chapter created.');
    }

    public function update(Request $request, Chapter $chapter): RedirectResponse
    {
        $validated = $request->validate([
            'number' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
                Rule::unique('chapters', 'number')
                    ->where(fn ($q) => $q->where('subject_id', $chapter->subject_id))
                    ->ignore($chapter->id),
            ],
            'title_en' => ['sometimes', 'string', 'max:500'],
            'title_ur' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $chapter->update($validated);

        return back()->with('success', 'Chapter updated.');
    }

    public function destroy(Chapter $chapter): RedirectResponse
    {
        if ($chapter->questions()->exists()) {
            return back()->with('error', 'This chapter still has questions. Deactivate it instead of deleting.');
        }

        $chapter->delete();

        return back()->with('success', 'Chapter deleted.');
    }
}
