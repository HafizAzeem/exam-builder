<?php

namespace App\Http\Controllers\SuperAdmin\Curriculum;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Grade $grade): Response
    {
        $subjects = Subject::query()
            ->where('grade_id', $grade->id)
            ->withCount('chapters')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        return Inertia::render('SuperAdmin/Curriculum/Subjects', [
            'grade' => $grade,
            'subjects' => $subjects,
        ]);
    }

    public function store(Request $request, Grade $grade): RedirectResponse
    {
        $validated = $request->validate([
            'name_en' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects', 'name_en')->where(fn ($q) => $q->where('grade_id', $grade->id)),
            ],
            'name_ur' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $grade->subjects()->create([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? (($grade->subjects()->max('sort_order') ?? 0) + 1),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Subject created.');
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'name_en' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('subjects', 'name_en')
                    ->where(fn ($q) => $q->where('grade_id', $subject->grade_id))
                    ->ignore($subject->id),
            ],
            'name_ur' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $subject->update($validated);

        return back()->with('success', 'Subject updated.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        if ($subject->chapters()->exists()) {
            return back()->with('error', 'Remove chapters from this subject before deleting it.');
        }

        $hasQuestions = Question::query()
            ->whereHas('chapter', fn ($q) => $q->where('subject_id', $subject->id))
            ->exists();

        if ($hasQuestions) {
            return back()->with('error', 'This subject still has questions. Deactivate it instead of deleting.');
        }

        $subject->delete();

        return back()->with('success', 'Subject deleted.');
    }
}
