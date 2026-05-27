<?php

namespace App\Http\Controllers;

use App\Models\PastPaperTag;
use App\Models\Question;
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
            ->latest('year')
            ->paginate(20)
            ->withQueryString();

        $boards = PastPaperTag::query()->distinct()->orderBy('board_name')->pluck('board_name');

        return Inertia::render('PastPapers/Index', [
            'tags' => $tags,
            'boards' => $boards,
            'filters' => $request->only(['board', 'year']),
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
