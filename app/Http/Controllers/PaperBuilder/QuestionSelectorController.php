<?php

namespace App\Http\Controllers\PaperBuilder;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Subject;
use App\Services\QuestionBankService;
use Illuminate\Http\Request;

class QuestionSelectorController extends Controller
{
    public function __construct(
        protected QuestionBankService $questionBank,
    ) {}

    public function subjects(Request $request, int $gradeId)
    {
        $user = $request->user();

        if ($user->hasRole('teacher')) {
            $allowedGrades = $user->teacherPermission?->allowed_grades ?? null;
            if (is_array($allowedGrades) && count($allowedGrades)) {
                $gradeNumber = Subject::query()->where('grade_id', $gradeId)->value('grade_id');
                // If teacher limited by grade numbers, ensure requested grade is permitted.
                $grade = \App\Models\Grade::query()->find($gradeId);
                abort_if($grade && ! in_array($grade->number, $allowedGrades, true), 403);
            }
        }

        $query = Subject::query()
            ->where('grade_id', $gradeId)
            ->orderBy('sort_order');

        if ($user->hasRole('teacher')) {
            $allowedSubjects = $user->teacherPermission?->allowed_subjects ?? null;
            if (is_array($allowedSubjects) && count($allowedSubjects)) {
                $query->whereIn('id', $allowedSubjects);
            }
        }

        return $query->get(['id', 'name_en', 'name_ur', 'grade_id']);
    }

    public function chapters(Request $request, int $subjectId)
    {
        $user = $request->user();

        if ($user->hasRole('teacher')) {
            $allowedSubjects = $user->teacherPermission?->allowed_subjects ?? null;
            if (is_array($allowedSubjects) && count($allowedSubjects)) {
                abort_if(! in_array($subjectId, $allowedSubjects, true), 403);
            }
        }

        return Chapter::query()
            ->where('subject_id', $subjectId)
            ->orderBy('number')
            ->get(['id', 'number', 'title_en', 'title_ur', 'subject_id']);
    }

    public function questions(Request $request)
    {
        $validated = $request->validate([
            'chapter_ids' => ['required', 'array'],
            'chapter_ids.*' => ['integer'],
            'sources' => ['array'],
            'type' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $user = $request->user();
        if ($user->hasRole('teacher')) {
            $allowedCategories = $user->teacherPermission?->allowed_categories ?? null;
            if (is_array($allowedCategories) && count($allowedCategories)) {
                $requestedSources = $validated['sources'] ?? [];
                foreach ($requestedSources as $src) {
                    abort_if(! in_array($src, $allowedCategories, true), 403);
                }
            }
        }

        return $this->questionBank->manualFetch(
            $validated['chapter_ids'],
            $validated['type'] ?? null,
            $validated['sources'] ?? [],
            $validated['search'] ?? null,
            (int) ($validated['per_page'] ?? 20),
        );
    }

    public function random(Request $request)
    {
        $validated = $request->validate([
            'chapter_ids' => ['required', 'array'],
            'config' => ['required', 'array'],
            'cache_key' => ['required', 'string'],
            'refresh' => ['boolean'],
        ]);

        $user = $request->user();
        if ($user->hasRole('teacher')) {
            $allowedCategories = $user->teacherPermission?->allowed_categories ?? null;
            if (is_array($allowedCategories) && count($allowedCategories)) {
                // Random config is by type; sources are controlled by Step 1 UI.
                // We enforce the sources on manual endpoint and wizard store.
                // Keep a hard fail if teacher isn't allowed to use random selection at all? (Not in PRD)
            }
        }

        if ($request->boolean('refresh')) {
            $this->questionBank->invalidateCache($validated['cache_key']);
        }

        return $this->questionBank->randomFetch(
            $validated['chapter_ids'],
            $validated['config'],
            $validated['cache_key'],
        );
    }
}
