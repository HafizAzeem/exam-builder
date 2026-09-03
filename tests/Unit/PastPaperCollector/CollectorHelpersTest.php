<?php

namespace Tests\Unit\PastPaperCollector;

use App\Models\AIPaperCollection;
use App\Models\Grade;
use App\Models\Subject;
use App\Services\PastPaperCollector\PaperAwareChunkerService;
use App\Services\PastPaperCollector\SearchQueryGenerator;
use App\Services\PastPaperCollector\SourceFetchService;
use App\Services\PastPaperCollector\SourceRelevanceFilter;
use App\Services\PastPaperCollector\UrlSafetyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectorHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_query_generator_creates_variations(): void
    {
        $grade = Grade::query()->create([
            'number' => 9,
            'label_en' => 'Class 9',
            'label_ur' => null,
        ]);
        $subject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'Physics',
            'name_ur' => null,
            'sort_order' => 1,
        ]);

        $collection = new AIPaperCollection([
            'board' => 'Lahore Board',
            'year' => 2020,
            'session' => 'morning',
            'paper_type' => 'complete',
            'language' => 'english',
            'country' => 'Pakistan',
        ]);
        $collection->setRelation('grade', $grade);
        $collection->setRelation('subject', $subject);

        $queries = app(SearchQueryGenerator::class)->generate($collection);

        $this->assertNotEmpty($queries);
        $this->assertTrue(collect($queries)->contains(fn ($q) => str_contains($q, 'Lahore Board')));
        $this->assertTrue(collect($queries)->contains(fn ($q) => str_contains($q, 'Physics')));
        $this->assertTrue(collect($queries)->contains(fn ($q) => str_contains($q, '2020')));
        $this->assertTrue(collect($queries)->contains(fn ($q) => str_contains($q, 'BISE Lahore')));
    }

    public function test_keywords_override_replaces_generated_queries(): void
    {
        $collection = new AIPaperCollection([
            'keywords_override' => 'custom physics paper',
            'board' => 'Lahore Board',
            'year' => 2020,
        ]);

        $queries = app(SearchQueryGenerator::class)->generate($collection);

        $this->assertSame('custom physics paper', $queries[0]);
        $this->assertTrue(collect($queries)->contains('custom physics paper PDF'));
    }

    public function test_url_safety_blocks_private_hosts(): void
    {
        $service = app(UrlSafetyService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->assertSafe('http://127.0.0.1/secret');
    }

    public function test_url_safety_normalizes_https_urls(): void
    {
        $normalized = app(UrlSafetyService::class)->normalize('https://Example.com/path/?q=1');
        $this->assertSame('https://example.com/path/?q=1', $normalized);
    }

    public function test_relevance_filter_requires_educational_signals(): void
    {
        $grade = Grade::query()->create(['number' => 9, 'label_en' => 'Class 9', 'label_ur' => null]);
        $subject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'Physics',
            'name_ur' => null,
            'sort_order' => 1,
        ]);

        $collection = new AIPaperCollection([
            'board' => 'Lahore Board',
            'year' => 2020,
        ]);
        $collection->setRelation('subject', $subject);

        $filter = app(SourceRelevanceFilter::class);

        $this->assertTrue($filter->isRelevant([
            'title' => 'BISE Lahore Physics Past Paper 2020 PDF',
            'url' => 'https://example.com/papers/physics-2020.pdf',
            'snippet' => 'Annual exam past paper',
        ], $collection));

        $this->assertFalse($filter->isRelevant([
            'title' => 'Casino login deals',
            'url' => 'https://example.com/casino',
            'snippet' => 'shopping offers',
        ], $collection));
    }

    public function test_paper_aware_chunker_splits_on_question_markers(): void
    {
        $text = "Q.1 What is force?\nAnswer space\n\nQ.2 Define velocity.\nMore text";
        $chunks = app(PaperAwareChunkerService::class)->chunk($text, 5000);

        $this->assertGreaterThanOrEqual(1, count($chunks));
        $this->assertStringContainsString('force', $chunks[0]['text']);
    }

    public function test_html_cleanup_strips_scripts_and_nav(): void
    {
        $html = <<<'HTML'
        <html><body>
            <nav>Menu</nav>
            <script>alert(1)</script>
            <article><h1>Past Paper</h1><p>Q1. What is energy?</p></article>
            <footer>Page 1</footer>
        </body></html>
        HTML;

        $text = app(SourceFetchService::class)->cleanText(
            (new \ReflectionClass(SourceFetchService::class))
                ->getMethod('extractHtml')
                ->invoke(app(SourceFetchService::class), $html)
        );

        $this->assertStringContainsString('What is energy', $text);
        $this->assertStringNotContainsString('alert(1)', $text);
        $this->assertStringNotContainsString('Menu', $text);
    }
}
