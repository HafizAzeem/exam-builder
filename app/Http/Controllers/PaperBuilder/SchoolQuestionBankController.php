<?php

namespace App\Http\Controllers\PaperBuilder;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaperBuilder\StoreSchoolQuestionRequest;
use App\Http\Requests\PaperBuilder\UpdateSchoolQuestionRequest;
use App\Models\Board;
use App\Models\Chapter;
use App\Models\McqOption;
use App\Models\Question;
use App\Services\SavedQuestionSnapshot;
use App\Support\CurriculumLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SchoolQuestionBankController extends Controller
{
    protected function managedQuestions($user)
    {
        abort_unless($user?->institution_id && $user->hasAnyRole(['institution_admin', 'teacher']), 403);

        return Question::where('institution_id', $user->institution_id)
            ->when(! $user->hasRole('institution_admin'), fn ($query) => $query->where('submitted_by', $user->id));
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'in:mcq,short,long,fill,truefalse'],
            'chapter_id' => ['nullable', 'integer'],
            'grade_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'scope' => ['nullable', 'in:all,mine'],
        ]);
        $this->managedQuestions($request->user()); // Authenticate the school role before browsing.
        $user = $request->user();
        $query = Question::where('institution_id', $user->institution_id)->whereNull('parent_question_id')
            ->when($request->input('scope') === 'mine', fn ($q) => $q->where('submitted_by', $user->id));
        $chapterIds = (clone $query)->distinct()->pluck('chapter_id');
        $entryChapters = CurriculumLookup::chapters()->with('subject.grade');
        if (! $user->hasRole('institution_admin') && $user->hasRole('teacher')) {
            $permission = $user->teacherPermission;
            if (! empty($permission?->allowed_grades)) {
                $entryChapters->whereHas('subject.grade', fn ($q) => $q->whereIn('number', $permission->allowed_grades));
            }
            if (! empty($permission?->allowed_subjects)) {
                $entryChapters->whereIn('subject_id', $permission->allowed_subjects);
            }
        }
        $query->when($request->filled('search'), function ($query) use ($filters) {
            $query->where(fn ($q) => $q->where('text_en', 'like', '%'.$filters['search'].'%')->orWhere('text_ur', 'like', '%'.$filters['search'].'%'));
        })->when($request->filled('type'), fn ($query) => $query->where('type', $filters['type']))
            ->when($request->filled('grade_id'), fn ($query) => $query->whereHas('chapter.subject', fn ($q) => $q->where('grade_id', $filters['grade_id'])))
            ->when($request->filled('subject_id'), fn ($query) => $query->whereHas('chapter', fn ($q) => $q->where('subject_id', $filters['subject_id'])))
            ->when($request->filled('chapter_id'), fn ($query) => $query->where('chapter_id', $filters['chapter_id']));

        return Inertia::render('Questions/Index', [
            'questions' => $query->with(['mcqOptions', 'pastPaperTag', 'parts', 'chapter.subject.grade', 'submitter:id,name'])->latest('id')->paginate(15)->withQueryString()
                ->through(fn ($question) => $question->setAttribute('can_manage', $user->hasRole('institution_admin') || $question->submitted_by === $user->id)),
            'filters' => $filters,
            'schoolWide' => $request->user()->hasRole('institution_admin'),
            'chapters' => Chapter::whereIn('id', $chapterIds)->with('subject.grade')->orderBy('subject_id')->orderBy('number')->get(),
            'boards' => Board::where('is_active', true)->orderBy('name')->pluck('name'),
            'entryChapters' => $entryChapters->get(),
            'initialMode' => $request->routeIs('questions.create') ? 'add' : 'list',
            'draftScope' => $user->institution_id.':'.$user->id,
        ]);
    }

    public function update(UpdateSchoolQuestionRequest $request, Question $question): JsonResponse
    {
        abort_unless($this->managedQuestions($request->user())->whereKey($question->id)->exists(), 403);
        $data = $request->validated('questions')[0];
        $this->assertChapterAllowed($request->user(), (int) $data['chapter_id']);

        DB::transaction(function () use ($question, $data) {
            $question = Question::whereKey($question->id)->lockForUpdate()->firstOrFail();
            SavedQuestionSnapshot::preserveLegacyPapers(array_filter([$question->id, $question->parent_question_id, ...$question->parts()->pluck('id')->all()]));
            $question->update([
                'chapter_id' => $data['chapter_id'], 'type' => $data['type'], 'source' => $data['source'] ?? $question->source,
                'text_en' => $data['text_en'] ?? null, 'text_ur' => $data['text_ur'] ?? null,
                'diagram' => $data['diagram'] ?? null,
                'correct_answer' => $data['type'] === 'truefalse' ? ($data['correct_answer'] ?? null) : null,
            ]);
            $this->syncMcq($question, $data);
            if ($question->source === 'past_paper') {
                $question->pastPaperTag()->updateOrCreate([], ['board_name' => $data['board_name'] ?? $question->pastPaperTag?->board_name]);
            } else {
                $question->pastPaperTag()->delete();
            }
            foreach ($data['parts'] ?? [] as $part) {
                abort_unless(filled($part['text_en'] ?? null) || filled($part['text_ur'] ?? null), 422, 'Enter text for each question part.');
                $child = $question->parts()->whereKey($part['id'])->first();
                abort_unless($child, 422, 'Invalid question part.');
                $child->update(['text_en' => $part['text_en'] ?? null, 'text_ur' => $part['text_ur'] ?? null]);
            }
        });

        return response()->json(['message' => 'Question updated. Saved papers are unchanged.']);
    }

    public function destroy(Request $request, Question $question): JsonResponse
    {
        abort_unless($this->managedQuestions($request->user())->whereKey($question->id)->exists(), 403);
        $request->validate(['confirmed' => ['required', 'accepted']]);
        DB::transaction(function () use ($question) {
            $question = Question::whereKey($question->id)->lockForUpdate()->firstOrFail();
            $ids = array_filter([$question->id, $question->parent_question_id, ...$question->parts()->pluck('id')->all()]);
            SavedQuestionSnapshot::preserveLegacyPapers($ids);
            $question->parts()->delete();
            $question->delete();
        });

        return response()->json(['message' => 'Question permanently deleted. Saved papers are unchanged.']);
    }

    public function store(StoreSchoolQuestionRequest $request): JsonResponse
    {
        $institutionId = $request->user()?->institution_id;
        abort_unless($institutionId, 403);

        $rows = $request->validated('questions');
        $created = [];
        $seen = [];

        DB::transaction(function () use ($request, $institutionId, $rows, &$created, &$seen) {
            foreach ($rows as $data) {
                $this->assertChapterAllowed($request->user(), (int) $data['chapter_id']);

                $key = hash('sha256', json_encode([
                    (int) $data['chapter_id'], $data['type'],
                    mb_strtolower(trim((string) ($data['text_en'] ?? ''))), trim((string) ($data['text_ur'] ?? '')),
                    $data['diagram'] ?? null, $data['mcq'] ?? null, $data['source'] ?? 'additional', $data['board_name'] ?? null,
                ]));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $question = Question::create([
                    'institution_id' => $institutionId,
                    'submitted_by' => $request->user()->id,
                    'contributor_board_id' => $request->user()->institution?->board_id,
                    'chapter_id' => $data['chapter_id'],
                    'type' => $data['type'],
                    'source' => $data['source'] ?? 'additional',
                    'text_en' => $data['text_en'] ?? null,
                    'text_ur' => $data['text_ur'] ?? null,
                    'diagram' => $data['diagram'] ?? null,
                    'is_active' => true,
                    'correct_answer' => ($data['type'] ?? null) === 'truefalse'
                        ? ($data['correct_answer'] ?? null)
                        : null,
                ]);

                $this->syncMcq($question, $data);
                if ($question->source === 'past_paper') {
                    $question->pastPaperTag()->create(['board_name' => $data['board_name'], 'year' => null]);
                }
                $created[] = $question->fresh(['mcqOptions', 'pastPaperTag', 'parts', 'chapter.subject.grade']);
            }
        });

        if ($created === []) {
            return response()->json(['message' => 'No new questions to add.'], 422);
        }

        return response()->json([
            'questions' => $created,
            'question' => $created[0] ?? null,
        ], 201);
    }

    protected function assertChapterAllowed($user, int $chapterId): void
    {
        $chapter = Chapter::query()->with('subject.grade')->find($chapterId);
        abort_unless($chapter, 422);

        if ($user?->hasRole('institution_admin') || ! $user?->hasRole('teacher')) {
            return;
        }

        $gradeNumber = $chapter->subject?->grade?->number;
        $allowedGrades = $user->teacherPermission?->allowed_grades;
        if (is_array($allowedGrades) && count($allowedGrades) && $gradeNumber && ! in_array($gradeNumber, $allowedGrades, true)) {
            abort(403);
        }

        $allowedSubjects = $user->teacherPermission?->allowed_subjects;
        $subjectId = $chapter->subject_id;
        if (is_array($allowedSubjects) && count($allowedSubjects) && ! in_array($subjectId, $allowedSubjects, true)) {
            abort(403);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncMcq(Question $question, array $data): void
    {
        if ($question->type === 'mcq') {
            McqOption::updateOrCreate(
                ['question_id' => $question->id],
                [
                    'option_a_en' => $data['mcq']['option_a_en'] ?? '',
                    'option_b_en' => $data['mcq']['option_b_en'] ?? '',
                    'option_c_en' => $data['mcq']['option_c_en'] ?? '',
                    'option_d_en' => $data['mcq']['option_d_en'] ?? '',
                    'correct_option' => $data['mcq']['correct_option'] ?? 'a',
                    'option_diagrams' => $data['mcq']['option_diagrams'] ?? null,
                ]
            );

            return;
        }

        McqOption::query()->where('question_id', $question->id)->delete();
    }
}
