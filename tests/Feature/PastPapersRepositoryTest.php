<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\PastPaperTag;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PastPapersRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(): User
    {
        Role::findOrCreate('teacher', 'web');
        $user = User::factory()->create();
        $user->assignRole('teacher');

        return $user;
    }

    /**
     * @return array{grade: Grade, subject: Subject, chapter: Chapter}
     */
    protected function seedEnglish(): array
    {
        $grade = Grade::query()->create(['number' => 9, 'label_en' => 'Class 9', 'label_ur' => null]);
        $subject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'English',
            'name_ur' => null,
            'sort_order' => 1,
        ]);
        $chapter = Chapter::query()->create([
            'subject_id' => $subject->id,
            'number' => 1,
            'title_en' => 'Unit 1',
            'title_ur' => null,
        ]);

        return compact('grade', 'subject', 'chapter');
    }

    protected function addTaggedQuestion(Chapter $chapter, string $text, string $board = 'Lahore Board', int $year = 2024, ?string $session = null): Question
    {
        $question = Question::query()->create([
            'chapter_id' => $chapter->id,
            'type' => 'fill',
            'source' => 'past_paper',
            'text_en' => $text,
            'is_active' => true,
        ]);

        PastPaperTag::query()->create([
            'question_id' => $question->id,
            'board_name' => $board,
            'year' => $year,
            'session' => $session,
        ]);

        return $question;
    }

    public function test_repository_groups_questions_from_one_pasted_paper(): void
    {
        $user = $this->makeUser();
        ['grade' => $grade, 'subject' => $subject, 'chapter' => $chapter] = $this->seedEnglish();

        $this->addTaggedQuestion($chapter, 'They --- hard all day.');
        $this->addTaggedQuestion($chapter, 'The girls are --- net ball.');
        $this->addTaggedQuestion($chapter, 'She --- sent him back.');
        $this->addTaggedQuestion($chapter, 'Physics fill from another paper.', 'Lahore Board', 2023);

        $this->actingAs($user)
            ->get(route('past-papers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PastPapers/Index')
                ->has('papers.data', 2)
                ->where('papers.data.0.board_name', 'Lahore Board')
                ->where('papers.data.0.year', 2024)
                ->where('papers.data.0.question_count', 3)
                ->where('papers.data.0.subject_name', 'English')
            );
    }

    public function test_show_renders_all_questions_for_one_paper(): void
    {
        $user = $this->makeUser();
        ['grade' => $grade, 'subject' => $subject, 'chapter' => $chapter] = $this->seedEnglish();

        $this->addTaggedQuestion($chapter, 'They --- hard all day.');
        $this->addTaggedQuestion($chapter, 'The girls are --- net ball.');

        $this->actingAs($user)
            ->get(route('past-papers.show', [
                'board' => 'Lahore Board',
                'year' => 2024,
                'session' => '',
                'grade_id' => $grade->id,
                'subject_id' => $subject->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PastPapers/Show')
                ->has('questions', 2)
                ->where('examMeta.class', 'Class 9')
                ->where('examMeta.subject', 'English')
                ->where('layout.header_template', 1)
            );
    }

    public function test_get_past_paper_form_redirects_to_paste(): void
    {
        $user = $this->makeUser();
        ['grade' => $grade, 'subject' => $subject] = $this->seedEnglish();

        $this->actingAs($user)
            ->get(route('builder.ai.extract-past-paper', [
                'grade' => $grade->id,
                'subject' => $subject->id,
            ]))
            ->assertRedirect();
    }
}
