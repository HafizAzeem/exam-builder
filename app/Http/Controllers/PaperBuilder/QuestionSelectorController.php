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
        return Subject::query()
            ->where('grade_id', $gradeId)
            ->orderBy('sort_order')
            ->get(['id', 'name_en', 'name_ur', 'grade_id']);
    }

    public function chapters(Request $request, int $subjectId)
    {
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
        ]);

        return $this->questionBank->manualFetch(
            $validated['chapter_ids'],
            $validated['type'] ?? null,
            $validated['sources'] ?? [],
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
