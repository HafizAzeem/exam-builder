<?php

namespace App\Http\Controllers;

use App\Models\PastPaperTag;
use App\Models\Question;
use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PastPaperController extends Controller
{
    public function index(Request $request): Response
    {
        $tags = PastPaperTag::query()
            ->with(['question.chapter.subject.grade'])
            ->when($request->filled('board'), fn ($q) => $q->where('board_name', $request->string('board')))
            ->when($request->filled('year'), fn ($q) => $q->where('year', $request->integer('year')))
            ->when($request->filled('session'), fn ($q) => $q->where('session', $request->string('session')))
            ->when($request->filled('grade_id'), function ($q) use ($request) {
                $q->whereHas('question.chapter.subject', fn ($s) => $s->where('grade_id', $request->integer('grade_id')));
            })
            ->when($request->filled('subject_id'), function ($q) use ($request) {
                $q->whereHas('question.chapter', fn ($c) => $c->where('subject_id', $request->integer('subject_id')));
            })
            ->latest('year')
            ->paginate(20)
            ->withQueryString();

        $boards = PastPaperTag::query()->distinct()->orderBy('board_name')->pluck('board_name');
        $years = PastPaperTag::query()->distinct()->orderByDesc('year')->pluck('year');
        $grades = Grade::query()->orderBy('number')->get(['id', 'number', 'label_en']);
        $subjects = Subject::query()->orderBy('name_en')->get(['id', 'name_en', 'grade_id']);

        return Inertia::render('PastPapers/Index', [
            'tags' => $tags,
            'boards' => $boards,
            'years' => $years,
            'grades' => $grades,
            'subjects' => $subjects,
            'filters' => $request->only(['board', 'year', 'session', 'grade_id', 'subject_id']),
        ]);
    }

    public function quickPrint(Request $request, Question $question): Response
    {
        $question->load(['mcqOptions', 'pastPaperTag', 'chapter.subject.grade']);

        return Inertia::render('PastPapers/QuickPrint', [
            'question' => $question,
        ]);
    }
}
