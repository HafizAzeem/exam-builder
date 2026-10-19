<?php

namespace App\Http\Controllers\PaperBuilder;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaperBuilder\StoreSchoolQuestionRequest;
use App\Models\Chapter;
use App\Models\McqOption;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SchoolQuestionBankController extends Controller
{
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

                $key = mb_strtolower(trim((string) ($data['text_en'] ?? '')).'|'.trim((string) ($data['text_ur'] ?? '')));
                if ($key === '|' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $question = Question::create([
                    'institution_id' => $institutionId,
                    'chapter_id' => $data['chapter_id'],
                    'type' => $data['type'],
                    'source' => 'additional',
                    'text_en' => $data['text_en'] ?? null,
                    'text_ur' => $data['text_ur'] ?? null,
                    'is_active' => true,
                    'correct_answer' => ($data['type'] ?? null) === 'truefalse'
                        ? ($data['correct_answer'] ?? null)
                        : null,
                ]);

                $this->syncMcq($question, $data);
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

        if (! $user?->hasRole('teacher')) {
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
                ]
            );

            return;
        }

        McqOption::query()->where('question_id', $question->id)->delete();
    }
}
