<?php

namespace Tests\Feature;

use App\Models\AIImport;
use App\Models\AIImportQuestion;
use App\Models\Board;
use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\AIImport\QuestionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherAIReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_question_on_a_shared_past_paper_is_attributed_to_the_actual_contributor(): void
    {
        $original = $this->makeTeacher();
        $data = $this->seedReview($original);
        $data['import']->update(['book_type' => 'past_paper']);
        $contributor = $this->makeTeacher();
        $board = Board::create(['name' => 'Punjab', 'is_active' => true]);
        $school = Institution::create(['name' => 'Contributor School', 'board_id' => $board->id, 'expiry_date' => now()->addYear(), 'license_type' => 'full']);
        $contributor->update(['institution_id' => $school->id]);
        $this->actingAs($contributor)->postJson(route('builder.ai.manual-question', $data['import']), [
            'chapter_id' => $data['chapterA']->id, 'type' => 'short', 'text_en' => 'Describe the present continuous tense.',
        ])->assertCreated();
        $staging = AIImportQuestion::where('text_en', 'Describe the present continuous tense.')->firstOrFail();
        $result = app(QuestionImportService::class)->importSelected($data['import'], [$staging->id]);
        $this->assertSame(1, $result['imported']);
        $question = Question::findOrFail($result['question_ids'][0]);
        $this->assertSame($contributor->id, $question->submitted_by);
        $this->assertSame($school->id, $question->institution_id);
        $this->assertSame($board->id, $question->contributor_board_id);
    }

    protected function makeTeacher(): User
    {
        Role::findOrCreate('teacher', 'web');
        $user = User::factory()->create();
        $user->assignRole('teacher');

        return $user;
    }

    /**
     * @return array{grade: Grade, subject: Subject, chapterA: Chapter, chapterB: Chapter, import: AIImport, staging: AIImportQuestion}
     */
    protected function seedReview(User $user): array
    {
        $grade = Grade::query()->create(['number' => 9, 'label_en' => 'Class 9', 'label_ur' => null, 'is_active' => true]);
        $subject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'English',
            'name_ur' => null,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $chapterA = Chapter::query()->create([
            'subject_id' => $subject->id,
            'number' => 1,
            'title_en' => 'Unit 1',
            'title_ur' => null,
            'is_active' => true,
        ]);
        $chapterB = Chapter::query()->create([
            'subject_id' => $subject->id,
            'number' => 2,
            'title_en' => 'Unit 2',
            'title_ur' => null,
            'is_active' => true,
        ]);

        $import = AIImport::query()->create([
            'user_id' => $user->id,
            'mode' => 'paste',
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'book_type' => 'additional_questions',
            'language' => 'english',
            'original_filename' => 'paste.txt',
            'stored_path' => 'n/a',
            'status' => 'review',
            'meta' => ['chapter_ids' => [$chapterA->id, $chapterB->id]],
        ]);

        $staging = AIImportQuestion::query()->create([
            'ai_import_id' => $import->id,
            'chapter_id' => $chapterA->id,
            'match_status' => 'matched',
            'type' => 'short',
            'source' => 'additional',
            'text_en' => 'What is a noun?',
            'status' => 'pending',
        ]);

        return compact('grade', 'subject', 'chapterA', 'chapterB', 'import', 'staging');
    }

    public function test_teacher_can_reassign_chapter_on_review(): void
    {
        $user = $this->makeTeacher();
        $data = $this->seedReview($user);

        $this->actingAs($user)
            ->patch(route('builder.ai.questions.update', [$data['import']->id, $data['staging']->id]), [
                'chapter_id' => $data['chapterB']->id,
            ])
            ->assertOk()
            ->assertJsonPath('question.chapter_id', $data['chapterB']->id)
            ->assertJsonPath('question.match_status', 'manual');

        $this->assertDatabaseHas('ai_import_questions', [
            'id' => $data['staging']->id,
            'chapter_id' => $data['chapterB']->id,
            'match_status' => 'manual',
        ]);
    }

    public function test_teacher_cannot_assign_chapter_outside_import(): void
    {
        $user = $this->makeTeacher();
        $data = $this->seedReview($user);
        $other = Chapter::query()->create([
            'subject_id' => $data['subject']->id,
            'number' => 9,
            'title_en' => 'Not selected',
            'title_ur' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('builder.ai.questions.update', [$data['import']->id, $data['staging']->id]), [
                'chapter_id' => $other->id,
            ])
            ->assertStatus(422);
    }

    public function test_teacher_can_remove_review_question(): void
    {
        $user = $this->makeTeacher();
        $data = $this->seedReview($user);

        $this->actingAs($user)
            ->post(route('builder.ai.questions.remove', [$data['import']->id, $data['staging']->id]))
            ->assertOk();

        $this->assertDatabaseHas('ai_import_questions', [
            'id' => $data['staging']->id,
            'status' => 'rejected',
        ]);

        $this->actingAs($user)
            ->get(route('builder.ai.review', $data['import']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PaperBuilder/AI/Review')
                ->has('questions', 0)
            );
    }
}
