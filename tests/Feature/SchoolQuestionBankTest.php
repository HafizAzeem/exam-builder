<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Board;
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
    public function test_each_question_keeps_its_selected_source_and_can_be_filtered(): void
    {
        $admin = $this->makeSchoolAdmin();
        ['chapter' => $chapter] = $this->seedEnglish();
        Board::firstOrCreate(['name' => 'Lahore Board'], ['is_active' => true]);
        $rows = array_map(fn ($source) => [
            'chapter_id' => $chapter->id,
            'type' => 'short',
            'source' => $source,
            'text_en' => 'Question from '.$source,
            'board_name' => $source === 'past_paper' ? 'Lahore Board' : null,
        ], ['exercise', 'additional', 'past_paper']);

        $this->actingAs($admin)->postJson(route('questions.store'), ['questions' => $rows])
            ->assertCreated()->assertJsonPath('questions.0.source', 'exercise')
            ->assertJsonPath('questions.1.source', 'additional')
            ->assertJsonPath('questions.2.source', 'past_paper');

        foreach (['exercise', 'additional', 'past_paper'] as $source) {
            $this->getJson('/api/builder/questions/all?'.http_build_query([
                'chapter_ids' => [$chapter->id], 'type' => 'short', 'sources' => [$source],
            ]))->assertOk()->assertJsonCount(1)->assertJsonPath('0.source', $source);
        }
    }

    public function test_single_question_source_is_saved_and_invalid_source_is_rejected(): void
    {
        $admin = $this->makeSchoolAdmin();
        ['chapter' => $chapter] = $this->seedEnglish();
        $question = ['chapter_id' => $chapter->id, 'type' => 'short', 'text_en' => 'Define a noun.', 'source' => 'exercise'];
        $this->actingAs($admin)->postJson(route('questions.store'), $question)
            ->assertCreated()->assertJsonPath('question.source', 'exercise');
        $question['source'] = 'unknown';
        $this->postJson(route('questions.store'), $question)->assertUnprocessable()->assertJsonValidationErrors('questions.0.source');
        $this->assertSame(1, Question::count());
    }

    public function test_past_paper_board_is_saved_without_inventing_a_year_and_can_be_filtered(): void
    {
        $admin = $this->makeSchoolAdmin();
        ['chapter' => $chapter] = $this->seedEnglish();
        Board::firstOrCreate(['name' => 'Lahore Board'], ['is_active' => true]);
        $this->actingAs($admin)->postJson(route('questions.store'), [
            'chapter_id' => $chapter->id, 'type' => 'short', 'source' => 'past_paper',
            'board_name' => 'Lahore Board', 'text_en' => 'Define a pronoun.',
        ])->assertCreated()->assertJsonPath('question.past_paper_tag.board_name', 'Lahore Board')
            ->assertJsonPath('question.past_paper_tag.year', null);
        $this->getJson('/api/builder/questions/all?'.http_build_query([
            'chapter_ids' => [$chapter->id], 'sources' => ['past_paper'], 'board_name' => 'Lahore Board',
        ]))->assertOk()->assertJsonCount(1)->assertJsonPath('0.past_paper_tag.board_name', 'Lahore Board');
    }

    public function test_past_paper_requires_an_active_board_and_other_sources_do_not_keep_board_tags(): void
    {
        $admin = $this->makeSchoolAdmin();
        ['chapter' => $chapter] = $this->seedEnglish();
        Board::create(['name' => 'Inactive Board', 'is_active' => false]);
        $question = ['chapter_id' => $chapter->id, 'type' => 'short', 'source' => 'past_paper', 'text_en' => 'What is grammar?'];
        $this->actingAs($admin)->postJson(route('questions.store'), $question)->assertUnprocessable()->assertJsonValidationErrors('questions.0.board_name');
        foreach (['Inactive Board', 'Unknown Board'] as $board) {
            $this->postJson(route('questions.store'), $question + ['board_name' => $board])->assertUnprocessable()->assertJsonValidationErrors('questions.0.board_name');
        }
        $question['source'] = 'exercise';
        $this->postJson(route('questions.store'), $question + ['board_name' => 'Inactive Board'])->assertCreated()->assertJsonPath('question.past_paper_tag', null);
        $this->assertDatabaseCount('past_paper_tags', 0);
    }

}
