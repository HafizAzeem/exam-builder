<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolQuestionBankTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSchoolAdmin(?Institution $institution = null): User
    {
        Role::findOrCreate('institution_admin', 'web');
        $institution ??= Institution::query()->create([
            'name' => 'Test School',
            'expiry_date' => now()->addYear(),
            'license_type' => 'full',
        ]);

        $user = User::factory()->create([
            'institution_id' => $institution->id,
        ]);
        $user->assignRole('institution_admin');

        return $user;
    }

    /**
     * @return array{grade: Grade, subject: Subject, chapter: Chapter}
     */
    protected function seedEnglish(): array
    {
        $grade = Grade::query()->create(['number' => 9, 'label_en' => 'Class 9', 'label_ur' => null, 'is_active' => true]);
        $subject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'English',
            'name_ur' => null,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $chapter = Chapter::query()->create([
            'subject_id' => $subject->id,
            'number' => 1,
            'title_en' => 'Unit 1',
            'title_ur' => null,
            'is_active' => true,
        ]);

        return compact('grade', 'subject', 'chapter');
    }

    public function test_school_admin_can_create_own_question_for_paper(): void
    {
        $admin = $this->makeSchoolAdmin();
        ['chapter' => $chapter] = $this->seedEnglish();

        $this->actingAs($admin)
            ->postJson(route('questions.store'), [
                'chapter_id' => $chapter->id,
                'type' => 'short',
                'text_en' => 'Write two uses of water.',
            ])
            ->assertCreated()
            ->assertJsonPath('questions.0.text_en', 'Write two uses of water.')
            ->assertJsonPath('questions.0.type', 'short')
            ->assertJsonPath('questions.0.institution_id', $admin->institution_id);

        $this->assertDatabaseHas('questions', [
            'institution_id' => $admin->institution_id,
            'text_en' => 'Write two uses of water.',
            'type' => 'short',
            'source' => 'additional',
        ]);
    }

    public function test_school_can_create_mcq(): void
    {
        $admin = $this->makeSchoolAdmin();
        ['chapter' => $chapter] = $this->seedEnglish();

        $this->actingAs($admin)
            ->postJson(route('questions.store'), [
                'chapter_id' => $chapter->id,
                'type' => 'mcq',
                'text_en' => 'Capital of Pakistan?',
                'mcq' => [
                    'option_a_en' => 'Karachi',
                    'option_b_en' => 'Islamabad',
                    'option_c_en' => 'Lahore',
                    'option_d_en' => 'Peshawar',
                    'correct_option' => 'b',
                ],
            ])
            ->assertCreated();

        $question = Question::query()->where('text_en', 'Capital of Pakistan?')->first();
        $this->assertNotNull($question);
        $this->assertSame($admin->institution_id, $question->institution_id);
        $this->assertSame('b', $question->mcqOptions?->correct_option);
    }

    public function test_my_questions_page_is_removed(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('questions.index'));

        $admin = $this->makeSchoolAdmin();
        $this->actingAs($admin)
            ->get('/questions')
            ->assertStatus(405);
    }

    public function test_school_can_bulk_create_questions_and_skip_duplicates(): void
    {
        $admin = $this->makeSchoolAdmin();
        ['chapter' => $chapter] = $this->seedEnglish();

        $this->actingAs($admin)
            ->postJson(route('questions.store'), [
                'questions' => [
                    [
                        'chapter_id' => $chapter->id,
                        'type' => 'short',
                        'text_en' => 'What is a noun?',
                    ],
                    [
                        'chapter_id' => $chapter->id,
                        'type' => 'short',
                        'text_en' => 'What is a noun?',
                    ],
                    [
                        'chapter_id' => $chapter->id,
                        'type' => 'short',
                        'text_en' => 'What is a verb?',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'questions');

        $this->assertSame(2, Question::query()->where('institution_id', $admin->institution_id)->count());
    }
}
