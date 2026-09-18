<?php

namespace App\Http\Controllers\SuperAdmin\Curriculum;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GradeController extends Controller
{
    public function index(): Response
    {
        $grades = Grade::query()
            ->withCount('subjects')
            ->orderBy('number')
            ->get();

        return Inertia::render('SuperAdmin/Curriculum/Grades', [
            'grades' => $grades,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:12', 'unique:grades,number'],
            'label_en' => ['required', 'string', 'max:50'],
            'label_ur' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        Grade::query()->create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Class created.');
    }

    public function update(Request $request, Grade $grade): RedirectResponse
    {
        $validated = $request->validate([
            'number' => ['sometimes', 'integer', 'min:1', 'max:12', Rule::unique('grades', 'number')->ignore($grade->id)],
            'label_en' => ['sometimes', 'string', 'max:50'],
            'label_ur' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $grade->update($validated);

        return back()->with('success', 'Class updated.');
    }

    public function destroy(Grade $grade): RedirectResponse
    {
        if ($grade->subjects()->exists()) {
            return back()->with('error', 'Remove subjects from this class before deleting it.');
        }

        $hasQuestions = Question::query()
            ->whereHas('chapter.subject', fn ($q) => $q->where('grade_id', $grade->id))
            ->exists();

        if ($hasQuestions) {
            return back()->with('error', 'This class still has questions. Deactivate it instead of deleting.');
        }

        $grade->delete();

        return back()->with('success', 'Class deleted.');
    }
}
