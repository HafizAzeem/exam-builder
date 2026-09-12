<?php

namespace Tests\Unit\PastPaperCollector;

use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Models\AIImport;
use App\Models\AIImportQuestion;
use App\Models\AISetting;
use App\Models\Chapter;
use App\Models\Grade;
use App\Models\PastPaperTag;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\AIImport\DuplicateDetectionService;
use App\Services\AIImport\QuestionImportService;
use App\Services\AIImport\QuestionMergeService;
use App\Services\AIImport\QuestionParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DuplicateAndImportTest extends TestCase
{
    use RefreshDatabase;

    protected function seedCurriculum(): array
    {
        $user = User::factory()->create();
        $grade = Grade::query()->create(['number' => 9, 'label_en' => 'Class 9', 'label_ur' => null]);
        $subject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'Physics',
            'name_ur' => null,
            'sort_order' => 1,
        ]);
        $chapter = Chapter::query()->create([
            'subject_id' => $subject->id,
            'number' => 1,
            'title_en' => 'Physical Quantities',
            'title_ur' => null,
        ]);

        $import = AIImport::query()->create([
            'user_id' => $user->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'book_type' => 'past_paper',
            'board' => 'Lahore Board',
            'year' => 2020,
            'session' => 'morning',
            'language' => 'english',
            'original_filename' => 'web-collection',
            'stored_path' => 'pending',
            'status' => 'review',
        ]);

        return compact('user', 'grade', 'subject', 'chapter', 'import');
    }

    public function test_parser_stores_confidence_and_source_defaults(): void
    {
        ['import' => $import, 'chapter' => $chapter] = $this->seedCurriculum();

        $created = app(QuestionParserService::class)->storeStagingQuestions($import, [[
            'type' => 'short',
            'text_en' => 'Define force.',
            'chapter_number' => 1,
            'chapter_title' => 'Physical Quantities',
            'topic' => null,
            'confidence_score' => 0.91,
            'difficulty' => 'easy',
            'estimated_marks' => 2,
        ]], [
            'source_url' => 'https://example.com/paper.pdf',
            'source_excerpt' => 'Define force excerpt',
        ]);

        $this->assertCount(1, $created);
        $this->assertSame($chapter->id, $created[0]->chapter_id);
        $this->assertSame(0.91, (float) $created[0]->confidence_score);
        $this->assertSame('easy', $created[0]->difficulty);
        $this->assertSame(2, $created[0]->estimated_marks);
        $this->assertSame('https://example.com/paper.pdf', $created[0]->source_url);
        $this->assertSame('past_paper', $created[0]->source);
    }

    public function test_fuzzy_duplicate_detection_flags_similar_questions(): void
    {
        ['import' => $import, 'chapter' => $chapter] = $this->seedCurriculum();
        AISetting::current()->update(['duplicate_similarity_threshold' => 0.8]);

        $existing = Question::query()->create([
            'chapter_id' => $chapter->id,
            'type' => 'short',
            'source' => 'past_paper',
            'text_en' => 'Define force and give an example.',
            'is_active' => true,
        ]);
        PastPaperTag::query()->create([
            'question_id' => $existing->id,
            'board_name' => 'Lahore Board',
            'year' => 2020,
            'session' => 'morning',
        ]);

        $staging = AIImportQuestion::query()->create([
            'ai_import_id' => $import->id,
            'chapter_id' => $chapter->id,
            'type' => 'short',
            'source' => 'past_paper',
            'text_en' => 'Define force and give an example.',
            'status' => 'pending',
            'match_status' => 'matched',
        ]);

        $isDup = app(DuplicateDetectionService::class)->markIfDuplicate($staging->fresh());

        $this->assertTrue($isDup);
        $this->assertTrue($staging->fresh()->is_duplicate);
        $this->assertSame($existing->id, $staging->fresh()->duplicate_of_question_id);
        $this->assertNotNull($staging->fresh()->duplicate_score);
    }

    public function test_merge_updates_existing_question_and_marks_imported(): void
    {
        ['import' => $import, 'chapter' => $chapter] = $this->seedCurriculum();

        $existing = Question::query()->create([
            'chapter_id' => $chapter->id,
            'type' => 'short',
            'source' => 'past_paper',
            'text_en' => 'Old text',
            'is_active' => true,
        ]);

        $staging = AIImportQuestion::query()->create([
            'ai_import_id' => $import->id,
            'chapter_id' => $chapter->id,
            'type' => 'short',
            'source' => 'past_paper',
            'text_en' => 'Merged text',
            'difficulty' => 'medium',
            'estimated_marks' => 3,
            'status' => 'pending',
            'match_status' => 'matched',
            'is_duplicate' => true,
            'duplicate_of_question_id' => $existing->id,
        ]);

        app(QuestionMergeService::class)->merge($staging, $existing, $import);

        $this->assertSame('Merged text', $existing->fresh()->text_en);
        $this->assertSame('medium', $existing->fresh()->difficulty);
        $this->assertSame(3, $existing->fresh()->estimated_marks);
        $this->assertSame('imported', $staging->fresh()->status);
        $this->assertNotNull($existing->fresh()->pastPaperTag);
    }

    public function test_import_writes_difficulty_and_past_paper_tag(): void
    {
        ['import' => $import, 'chapter' => $chapter] = $this->seedCurriculum();

        AIImportQuestion::query()->create([
            'ai_import_id' => $import->id,
            'chapter_id' => $chapter->id,
            'type' => 'short',
            'source' => 'past_paper',
            'text_en' => 'What is velocity?',
            'difficulty' => 'hard',
            'estimated_marks' => 5,
            'status' => 'approved',
            'match_status' => 'matched',
            'is_duplicate' => false,
        ]);

        $stats = app(QuestionImportService::class)->importApproved($import->fresh());

        $this->assertSame(1, $stats['imported']);
        $question = Question::query()->where('text_en', 'What is velocity?')->first();
        $this->assertNotNull($question);
        $this->assertSame('hard', $question->difficulty);
        $this->assertSame(5, $question->estimated_marks);
        $this->assertSame('past_paper', $question->source);
        $this->assertSame('Lahore Board', $question->pastPaperTag->board_name);
    }

    public function test_gemini_web_search_provider_parses_results(): void
    {
        AISetting::current()->update([
            'model_name' => 'gemini-3.5-flash-lite',
            'gemini_api_key' => 'test-gemini-key',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'results' => [
                                            [
                                                'title' => 'Physics Paper',
                                                'url' => 'https://example.com/a.pdf',
                                                'snippet' => 'Past paper',
                                            ],
                                            [
                                                'title' => 'Physics Paper Dup',
                                                'url' => 'https://example.com/a.pdf',
                                                'snippet' => 'Past paper',
                                            ],
                                        ],
                                    ]),
                                ],
                            ],
                        ],
                        'groundingMetadata' => [
                            'groundingChunks' => [
                                [
                                    'web' => [
                                        'uri' => 'https://example.com/b.pdf',
                                        'title' => 'Another Paper',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $results = app(WebSearchProvider::class)
            ->search('Lahore Board Physics', 5);

        $this->assertSame('gemini', app(WebSearchProvider::class)->name());
        $this->assertCount(2, $results);
        $this->assertSame('https://example.com/b.pdf', $results[0]['url']);
        $this->assertSame('https://example.com/a.pdf', $results[1]['url']);
    }
}
