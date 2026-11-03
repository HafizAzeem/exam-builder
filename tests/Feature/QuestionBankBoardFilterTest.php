<?php

namespace Tests\Feature;

use App\Models\{Board, Chapter, Grade, Institution, Question, Subject, User};
use App\Services\QuestionBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankBoardFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_board_comes_from_the_authenticated_teachers_school(): void
    {
        $board = Board::create(['name' => 'School Board', 'is_active' => true]);
        $school = Institution::create(['name' => 'School', 'board_id' => $board->id, 'expiry_date' => now()->addYear()]);
        $this->actingAs(User::factory()->create(['institution_id' => $school->id]))
            ->getJson('/api/builder/past-paper-filters')->assertOk()->assertJsonPath('default_board', 'School Board');
    }

    public function test_board_and_year_filters_preserve_shared_questions_in_mixed_sources(): void
    {
        $board = Board::create(['name' => 'Board A', 'is_active' => true]);
        $other = Board::create(['name' => 'Board B', 'is_active' => true]);
        $grade = Grade::create(['number' => 9, 'label_en' => 'Class 9', 'is_active' => true]);
        $subject = Subject::create(['grade_id' => $grade->id, 'name_en' => 'Physics', 'is_active' => true]);
        $chapter = Chapter::create(['subject_id' => $subject->id, 'number' => 1, 'title_en' => 'Motion', 'is_active' => true]);
        $create = fn ($attrs = []) => Question::create(array_merge(['chapter_id' => $chapter->id, 'source' => 'exercise', 'type' => 'short', 'text_en' => 'Question', 'is_active' => true], $attrs));
        $common = $create();
        $local = $create(['contributor_board_id' => $board->id]);
        $create(['contributor_board_id' => $other->id]);
        $paper = $create(['source' => 'past_paper']);
        $paper->pastPaperTag()->create(['board_name' => 'Board A', 'year' => 2025]);
        $wrong = $create(['source' => 'past_paper']);
        $wrong->pastPaperTag()->create(['board_name' => 'Board B', 'year' => 2025]);
        $ids = app(QuestionBankService::class)->fetchAll([$chapter->id], 'short', ['exercise', 'past_paper'], null, 500, [], 'Board A', 2025)->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$common->id, $local->id, $paper->id], $ids);
        $this->assertCount(5, app(QuestionBankService::class)->fetchAll([$chapter->id], 'short', ['exercise', 'past_paper']));
    }
}
