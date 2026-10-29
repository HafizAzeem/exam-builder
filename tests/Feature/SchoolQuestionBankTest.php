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
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolQuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_contribution_keeps_provenance_for_parent_and_parts_and_reuse(): void
    {
        $owner = $this->makeSchoolAdmin();
        $board = Board::create(['name' => 'Sindh', 'is_active' => true]);
        $owner->institution->update(['board_id' => $board->id]);
        ['chapter' => $chapter, 'grade' => $grade, 'subject' => $subject] = $this->seedEnglish();
        $import = AIImport::create([
            'user_id' => $owner->id, 'grade_id' => $grade->id, 'subject_id' => $subject->id,
            'book_type' => 'additional_questions', 'original_filename' => 'paste.txt', 'stored_path' => 'paste.txt',
        ]);
        $staging = AIImportQuestion::create([
            'ai_import_id' => $import->id, 'chapter_id' => $chapter->id, 'type' => 'long', 'source' => 'additional',
            'text_en' => 'Explain the following grammar rules.', 'status' => 'approved',
            'parts' => [['text_en' => 'Describe the simple present tense.']],
        ]);
        $service = app(QuestionImportService::class);
        $result = $service->importSelected($import, [$staging->id]);
        $this->assertSame(1, $result['imported']);
        $question = Question::findOrFail($result['question_ids'][0]);
        $this->assertSame($owner->id, $question->submitted_by);
        $this->assertSame($owner->institution_id, $question->institution_id);
        $this->assertSame($board->id, $question->parts->first()->contributor_board_id);
        $other = $this->makeSchoolAdmin();
        $otherImport = $import->replicate();
        $otherImport->user_id = $other->id;
        $otherImport->save();
        $reuse = AIImportQuestion::create([
            'ai_import_id' => $otherImport->id, 'chapter_id' => $chapter->id, 'type' => 'long', 'source' => 'additional',
            'text_en' => $question->text_en, 'status' => 'duplicate', 'is_duplicate' => true, 'duplicate_of_question_id' => $question->id,
        ]);
        $service->importSelected($otherImport, [$reuse->id]);
        $this->assertSame($owner->id, $question->fresh()->submitted_by);
        $this->assertSame(2, Question::count());
    }

    public function test_contributions_are_attributed_and_shared_only_with_the_same_curriculum_book(): void
    {
        $owner = $this->makeSchoolAdmin();
        $board = Board::create(['name' => 'Punjab', 'is_active' => true]);
        $owner->institution->update(['board_id' => $board->id]);
        ['chapter' => $chapter] = $this->seedEnglish();
        $this->actingAs($owner)->postJson('/questions', [
            'chapter_id' => $chapter->id, 'type' => 'short', 'text_en' => 'Define a pronoun.',
            'submitted_by' => 999, 'contributor_board_id' => 999,
        ])->assertCreated();
        $question = Question::firstOrFail();
        $this->assertSame($owner->id, $question->submitted_by);
        $this->assertSame($board->id, $question->contributor_board_id);
        $other = $this->makeSchoolAdmin();
        $this->actingAs($other)->getJson('/api/builder/questions/all?'.http_build_query(['chapter_ids' => [$chapter->id]]))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $question->id)->assertJsonMissingPath('0.submitter.email');
        $differentBook = Subject::create(['grade_id' => $chapter->subject->grade_id, 'name_en' => 'English (other book)', 'is_active' => true]);
        $otherChapter = Chapter::create(['subject_id' => $differentBook->id, 'number' => 1, 'title_en' => 'Other book', 'is_active' => true]);
        $this->getJson('/api/builder/questions/all?'.http_build_query(['chapter_ids' => [$otherChapter->id]]))->assertOk()->assertJsonCount(0);
        $this->assertSame($owner->id, $question->fresh()->submitted_by);
    }

    public function test_teacher_batch_questions_keep_the_school_board_hidden_and_ignore_submitted_board_ids(): void
    {
        $teacher = $this->makeSchoolAdmin();
        $teacher->syncRoles(Role::findOrCreate('teacher', 'web'));
        $board = Board::create(['name' => 'Peshawar Board', 'is_active' => true]);
        $otherBoard = Board::firstOrCreate(['name' => 'Lahore Board'], ['is_active' => true]);
        $teacher->institution->update(['board_id' => $board->id]);
        ['chapter' => $chapter] = $this->seedEnglish();

        $response = $this->actingAs($teacher)->postJson('/questions', [
            'board_id' => $otherBoard->id,
            'contributor_board_id' => $otherBoard->id,
            'questions' => array_map(fn ($text) => [
                'chapter_id' => $chapter->id, 'type' => 'short', 'text_en' => $text,
                'board_id' => $otherBoard->id, 'contributor_board_id' => $otherBoard->id,
            ], ['Define a noun.', 'Define a verb.']),
        ])->assertCreated()->assertJsonCount(2, 'questions');

        foreach ($response->json('questions') as $index => $saved) {
            $response->assertJsonMissingPath("questions.$index.contributor_board_id")
                ->assertJsonMissingPath("questions.$index.submitted_by");
            $question = Question::findOrFail($saved['id']);
            $this->assertSame($board->id, $question->contributor_board_id);
            $this->assertSame($teacher->id, $question->submitted_by);
            $this->assertSame($teacher->institution_id, $question->institution_id);
        }
    }

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

    public function test_my_questions_management_page_is_available(): void
    {
        $this->assertTrue(Route::has('questions.index'));
        $this->actingAs($this->makeSchoolAdmin())->get('/questions')->assertOk();
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
