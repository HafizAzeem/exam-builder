<?php

namespace Tests\Feature;

use App\Models\AIImport;
use App\Models\Board;
use App\Models\Chapter;
use App\Models\Grade;
use App\Models\PreferredQuestionSite;
use App\Models\Subject;
use App\Models\User;
use App\Services\PastPaperCollector\Providers\FreeWebSearchProvider;
use App\Services\PastPaperCollector\UrlSafetyService;
use App\Services\QuestionAssistant\AssistantSourceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantSourceReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_question_pages_outrank_syllabus_indexes_and_generic_instructions_do_not_dominate_query(): void
    {
        $grade = Grade::create(['number' => 9, 'label_en' => 'Class 9', 'is_active' => true]);
        Board::updateOrCreate(['name' => 'Lahore Board'], ['region' => 'Punjab', 'is_active' => true]);
        $subject = Subject::create(['grade_id' => $grade->id, 'name_en' => 'Mathematics', 'is_active' => true]);
        $chapters = collect(['Real Numbers', 'Logarithms', 'Sets', 'Factorization', 'Equations'])->map(fn ($name, $index) => Chapter::create(['subject_id' => $subject->id, 'number' => $index + 1, 'title_en' => $name]));
        $import = new AIImport(['user_id' => User::factory()->create()->id, 'grade_id' => $grade->id, 'subject_id' => $subject->id, 'board' => 'Lahore Board', 'meta' => ['chapter_ids' => $chapters->pluck('id')->all(), 'instructions' => 'latest syllabus', 'curriculum_year' => 2026]]);
        PreferredQuestionSite::create(['name' => 'Questions', 'domain' => 'questions.example', 'is_active' => true]);
        $search = $this->mock(FreeWebSearchProvider::class);
        $search->shouldReceive('isConfigured')->andReturn(true);
        $search->shouldReceive('search')->twice()->withArgs(function ($query, $count, $options) {
            $this->assertStringContainsString('Mathematics', $query);
            $this->assertStringContainsString('questions', $query);
            $this->assertStringNotContainsString('latest syllabus', $query);
            $this->assertStringNotContainsString('Factorization', $query);

            return $count <= 10 && $options['assistant'] === true;
        })->andReturn([
            ['url' => 'https://questions.example/kpk-test', 'title' => 'KPK Class 9 Mathematics MCQs', 'snippet' => 'Exercise questions'],
            ['url' => 'https://board.edu.pk/syllabus', 'title' => 'Latest syllabus 2026', 'snippet' => 'Syllabus notice'],
            ['url' => 'https://school.edu.pk/course', 'title' => 'Course syllabus', 'snippet' => 'Video lectures'],
            ['url' => 'https://www.questions.example/video', 'title' => 'Video lectures', 'snippet' => 'Course introduction'],
            ['url' => 'https://www.questions.example/exercise', 'title' => 'Class 9 Mathematics short questions exercise', 'snippet' => 'Solve the following exercise questions'],
        ]);
        $this->mock(UrlSafetyService::class)->shouldReceive('assertSafe')->andReturnUsing(fn ($url) => $url);
        Http::fake(fn () => Http::response('<html><body>Navigation<main>Solve x+2=3.'.str_repeat(' More source text.', 1500).'</main></body></html>', 200, ['Content-Type' => 'text/html']));
        $sources = app(AssistantSourceReader::class)->sources($import, microtime(true) + 120, fn () => null);
        $this->assertSame('https://www.questions.example/exercise', $sources[0]['url']);
        $this->assertCount(2, $sources);
        $this->assertSame(10000, mb_strlen($sources[0]['text']));
        $this->assertStringStartsWith('Solve x+2=3.', $sources[0]['text']);
        Http::assertSentCount(2);
    }

    public function test_inspected_matching_pages_work_when_search_index_has_no_results(): void
    {
        $grade = Grade::create(['number' => 9, 'label_en' => 'Class 9', 'is_active' => true]);
        $subject = Subject::create(['grade_id' => $grade->id, 'name_en' => 'Mathematics', 'is_active' => true]);
        $chapter = Chapter::create(['subject_id' => $subject->id, 'number' => 1, 'title_en' => 'Real Numbers']);
        Board::updateOrCreate(['name' => 'Lahore Board'], ['region' => 'Punjab', 'is_active' => true]);
        $site = PreferredQuestionSite::create(['name' => 'PrepSure', 'domain' => 'prepsure.pk', 'is_active' => true]);
        $import = new AIImport(['grade_id' => $grade->id, 'subject_id' => $subject->id, 'board' => 'Lahore Board', 'meta' => ['chapter_ids' => [$chapter->id]]]);
        $this->mock(FreeWebSearchProvider::class)->shouldReceive('isConfigured')->andReturn(true);
        app(FreeWebSearchProvider::class)->shouldReceive('search')->twice()->andReturn([]);
        $this->mock(UrlSafetyService::class)->shouldReceive('assertSafe')->andReturnUsing(fn ($url) => $url);
        Http::fake(['www.prepsure.pk/*' => Http::response('<html><main><span class="sr-only" aria-hidden="true">Is 0 a rational number? Explain.</span><div>Solution: Yes, because 0/1=0.</div></main></html>', 200, ['Content-Type' => 'text/html'])]);
        $sources = app(AssistantSourceReader::class)->sources($import, microtime(true) + 120, fn () => null);
        $this->assertCount(1, $sources);
        $this->assertStringContainsString('prepsure.pk', $sources[0]['url']);
        $this->assertSame('Is 0 a rational number? Explain.', $sources[0]['text']);
        $site->update(['is_active' => false]);
        app(FreeWebSearchProvider::class)->shouldReceive('search')->twice()->andReturn([]);
        $this->expectException(\RuntimeException::class);
        app(AssistantSourceReader::class)->sources($import, microtime(true) + 120, fn () => null);
    }
}
