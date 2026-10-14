<?php

namespace Tests\Unit\AIImport;

use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Models\AIImport;
use App\Models\AISetting;
use App\Models\Grade;
use App\Models\PreferredQuestionSite;
use App\Models\Subject;
use App\Models\User;
use App\Services\AIImport\TeacherPreferredWebHarvestService;
use App\Services\PastPaperCollector\Providers\FreeWebSearchProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FreeWebSearchProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_langsearch_parses_results_and_filters_domains(): void
    {
        AISetting::current()->update([
            'langsearch_api_key' => 'ls_test_key',
            'preferred_text_provider' => 'groq',
            'groq_api_key' => 'gsk_unused_for_search',
        ]);

        Http::fake([
            'api.langsearch.com/*' => Http::response([
                'data' => [
                    'webPages' => [
                        'value' => [
                            [
                                'name' => 'Class 9 Physics MCQ',
                                'url' => 'https://ilmkidunya.com/physics-mcq',
                                'snippet' => 'Exercise questions',
                            ],
                        ],
                    ],
                ],
            ], 200),
            'api.groq.com/*' => Http::response(['error' => 'should not be called'], 500),
        ]);

        $provider = app(WebSearchProvider::class);
        $this->assertInstanceOf(FreeWebSearchProvider::class, $provider);
        $this->assertSame('langsearch', $provider->name());

        $results = $provider->search('Class 9 Physics MCQ', 5, [
            'include_domains' => ['ilmkidunya.com'],
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('https://ilmkidunya.com/physics-mcq', $results[0]['url']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.langsearch.com'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.groq.com'));
    }

    public function test_searxng_is_used_when_langsearch_key_missing(): void
    {
        AISetting::current()->update([
            'langsearch_api_key' => null,
            'searxng_url' => 'http://127.0.0.1:8080',
        ]);

        Http::fake([
            '127.0.0.1:8080/*' => Http::response([
                'results' => [
                    ['title' => 'Paper', 'url' => 'https://example.edu/p.pdf', 'content' => 'BISE'],
                ],
            ], 200),
        ]);

        $provider = app(FreeWebSearchProvider::class);
        $this->assertSame('searxng', $provider->name());
        $results = $provider->search('Lahore Board Physics', 3);
        $this->assertSame('https://example.edu/p.pdf', $results[0]['url']);
    }

    public function test_harvest_skips_search_without_calling_groq_when_free_search_missing(): void
    {
        AISetting::current()->update([
            'groq_api_key' => 'gsk_generate_only',
            'preferred_text_provider' => 'groq',
            'langsearch_api_key' => null,
            'searxng_url' => null,
        ]);

        PreferredQuestionSite::query()->create([
            'name' => 'IlmKiDunya',
            'domain' => 'ilmkidunya.com',
            'is_active' => true,
            'priority' => 10,
            'source_types' => PreferredQuestionSite::SOURCE_TYPES,
        ]);

        $user = User::factory()->create();
        $grade = Grade::query()->create(['number' => 9, 'label_en' => 'Class 9', 'label_ur' => null]);
        $subject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'Physics',
            'name_ur' => null,
            'sort_order' => 1,
        ]);
        $import = AIImport::query()->create([
            'user_id' => $user->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'book_type' => 'text_book',
            'language' => 'english',
            'original_filename' => 'generate',
            'stored_path' => 'pending',
            'status' => 'processing',
            'meta' => ['counts' => ['mcq' => 5], 'content_source' => 'exercise'],
        ]);

        Http::fake();

        $questions = app(TeacherPreferredWebHarvestService::class)->harvest($import);

        $this->assertSame([], $questions);
        Http::assertNothingSent();
    }
}
