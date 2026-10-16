<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\PastPaperTag;
use App\Models\Question;
use App\Support\CurriculumLookup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PastPaperController extends Controller
{
    public function index(Request $request): Response
    {
        $query = PastPaperTag::query()
            ->join('questions', 'questions.id', '=', 'past_paper_tags.question_id')
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->join('subjects', 'subjects.id', '=', 'chapters.subject_id')
            ->join('grades', 'grades.id', '=', 'subjects.grade_id')
            ->whereNull('questions.parent_question_id')
            ->when($request->filled('board'), fn ($q) => $q->where('past_paper_tags.board_name', $request->string('board')))
            ->when($request->filled('year'), fn ($q) => $q->where('past_paper_tags.year', $request->integer('year')))
            ->when($request->filled('session'), fn ($q) => $q->where('past_paper_tags.session', $request->string('session')))
            ->when($request->filled('grade_id'), fn ($q) => $q->where('subjects.grade_id', $request->integer('grade_id')))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('chapters.subject_id', $request->integer('subject_id')));

        $papers = $query
            ->selectRaw('
                past_paper_tags.board_name,
                past_paper_tags.year,
                past_paper_tags.session,
                subjects.grade_id,
                chapters.subject_id,
                grades.label_en as grade_label,
                grades.number as grade_number,
                subjects.name_en as subject_name,
                COUNT(DISTINCT past_paper_tags.question_id) as question_count
            ')
            ->groupBy(
                'past_paper_tags.board_name',
                'past_paper_tags.year',
                'past_paper_tags.session',
                'subjects.grade_id',
                'chapters.subject_id',
                'grades.label_en',
                'grades.number',
                'subjects.name_en',
            )
            ->orderByDesc('past_paper_tags.year')
            ->orderBy('past_paper_tags.board_name')
            ->orderBy('subjects.name_en')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($row) => [
                'board_name' => $row->board_name,
                'year' => (int) $row->year,
                'session' => $row->session,
                'grade_id' => (int) $row->grade_id,
                'subject_id' => (int) $row->subject_id,
                'grade_label' => $row->grade_label,
                'grade_number' => (int) $row->grade_number,
                'subject_name' => $row->subject_name,
                'question_count' => (int) $row->question_count,
            ]);

        return Inertia::render('PastPapers/Index', [
            'papers' => $papers,
            'boards' => CurriculumLookup::boards()->pluck('name'),
            'years' => PastPaperTag::query()->distinct()->orderByDesc('year')->pluck('year'),
            'grades' => CurriculumLookup::grades()->get(['id', 'number', 'label_en']),
            'subjects' => CurriculumLookup::subjects()->get(['id', 'name_en', 'grade_id']),
            'filters' => $request->only(['board', 'year', 'session', 'grade_id', 'subject_id']),
        ]);
    }

    public function show(Request $request): Response
    {
        return Inertia::render('PastPapers/Show', $this->paperPayload($request));
    }

    public function print(Request $request): Response
    {
        return Inertia::render('PastPapers/Print', $this->paperPayload($request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function paperPayload(Request $request): array
    {
        $validated = $request->validate([
            'board' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'session' => ['nullable', 'string', 'max:20'],
            'grade_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
        ]);

        $session = $validated['session'] ?? '';

        $questionIds = PastPaperTag::query()
            ->join('questions', 'questions.id', '=', 'past_paper_tags.question_id')
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->join('subjects', 'subjects.id', '=', 'chapters.subject_id')
            ->where('past_paper_tags.board_name', $validated['board'])
            ->where('past_paper_tags.year', $validated['year'])
            ->where('subjects.grade_id', $validated['grade_id'])
            ->where('chapters.subject_id', $validated['subject_id'])
            ->whereNull('questions.parent_question_id')
            ->when(
                $session === '',
                fn ($q) => $q->where(function ($inner) {
                    $inner->whereNull('past_paper_tags.session')->orWhere('past_paper_tags.session', '');
                }),
                fn ($q) => $q->where('past_paper_tags.session', $session)
            )
            ->orderByRaw("FIELD(questions.type, 'mcq', 'fill', 'truefalse', 'short', 'long')")
            ->orderBy('questions.id')
            ->pluck('questions.id');

        abort_if($questionIds->isEmpty(), 404);

        $questions = Question::query()
            ->with(['mcqOptions', 'parts', 'pastPaperTag', 'chapter.subject.grade'])
            ->whereIn('id', $questionIds)
            ->orderByRaw("FIELD(type, 'mcq', 'fill', 'truefalse', 'short', 'long')")
            ->orderBy('id')
            ->get()
            ->map(fn (Question $question) => $this->serializeQuestion($question))
            ->values()
            ->all();

        $first = Question::query()->with('chapter.subject.grade')->find($questionIds->first());
        $grade = $first?->chapter?->subject?->grade;
        $subject = $first?->chapter?->subject;
        $board = $validated['board'];
        $year = (int) $validated['year'];
        $classLabel = $grade?->label_en ?: ('Class '.($grade?->number ?? ''));
        $subjectName = $subject?->name_en ?? '';
        $sessionLabel = $session !== '' ? ucfirst($session) : '';

        $paperType = trim($board.' Annual Examination '.$year.($sessionLabel !== '' ? ' ('.$sessionLabel.')' : ''));

        $institution = $request->user()?->institution_id
            ? Institution::query()->find($request->user()->institution_id)
            : null;

        return [
            'paper' => [
                'board_name' => $board,
                'year' => $year,
                'session' => $session !== '' ? $session : null,
                'grade_id' => (int) $validated['grade_id'],
                'subject_id' => (int) $validated['subject_id'],
                'grade_label' => $classLabel,
                'subject_name' => $subjectName,
                'question_count' => count($questions),
            ],
            'questions' => $questions,
            'title' => $paperType,
            'examMeta' => [
                'class' => $classLabel,
                'subject' => $subjectName,
                'paper_type' => $paperType,
                'time' => '',
                'marks' => (string) $this->estimateMarks($questions),
            ],
            'layout' => [
                'header_template' => 1,
                'dual_medium' => false,
                'show_past_paper_tags' => false,
                'show_note' => true,
                'show_paper_note' => true,
                'font_family' => 'Arial',
                'font_size' => 11,
                'heading_font_size' => 12,
                'enable_omr' => false,
            ],
            'institution' => [
                'name' => $this->boardLetterhead($board),
                'address' => $institution?->address,
                'city' => $institution?->city,
                'phone' => $institution?->phone,
                'logo_path' => $institution?->logo_path,
            ],
            'query' => [
                'board' => $board,
                'year' => $year,
                'session' => $session,
                'grade_id' => (int) $validated['grade_id'],
                'subject_id' => (int) $validated['subject_id'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeQuestion(Question $question): array
    {
        $options = $question->mcqOptions;

        return [
            'id' => $question->id,
            'type' => $question->type,
            'source' => $question->source,
            'text_en' => $question->text_en,
            'text_ur' => $question->text_ur,
            'mcq_options' => $options ? [
                'option_a_en' => $options->option_a_en,
                'option_a_ur' => $options->option_a_ur,
                'option_b_en' => $options->option_b_en,
                'option_b_ur' => $options->option_b_ur,
                'option_c_en' => $options->option_c_en,
                'option_c_ur' => $options->option_c_ur,
                'option_d_en' => $options->option_d_en,
                'option_d_ur' => $options->option_d_ur,
                'correct_option' => $options->correct_option,
            ] : null,
            'parts' => $question->parts->map(fn (Question $part) => [
                'id' => $part->id,
                'text_en' => $part->text_en,
                'text_ur' => $part->text_ur,
            ])->values()->all(),
            'past_paper_tag' => $question->pastPaperTag,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     */
    protected function estimateMarks(array $questions): int
    {
        $marks = 0;
        foreach ($questions as $question) {
            $marks += match ($question['type'] ?? '') {
                'long' => 5,
                'short' => 2,
                default => 1,
            };
        }

        return $marks;
    }

    protected function boardLetterhead(string $boardName): string
    {
        $city = trim((string) preg_replace('/\s+board$/i', '', $boardName));

        if ($city === '') {
            return strtoupper($boardName);
        }

        return 'BOARD OF INTERMEDIATE & SECONDARY EDUCATION, '.strtoupper($city);
    }
}
