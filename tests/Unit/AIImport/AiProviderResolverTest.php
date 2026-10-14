<?php

namespace Tests\Unit\AIImport;

use App\Contracts\AIImport\QuestionAiService;
use App\Contracts\PastPaperCollector\OcrProvider;
use App\Contracts\PastPaperCollector\QuestionProcessingProvider;
use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Models\AISetting;
use App\Services\AIImport\AiProviderResolver;
use App\Services\AIImport\Providers\GeminiQuestionAiService;
use App\Services\AIImport\Providers\GroqQuestionAiService;
use App\Services\PastPaperCollector\Providers\GeminiOcrProvider;
use App\Services\PastPaperCollector\Providers\GeminiQuestionProcessingProvider;
use App\Services\PastPaperCollector\Providers\FreeWebSearchProvider;
use App\Services\PastPaperCollector\Providers\GeminiWebSearchProvider;
use App\Services\PastPaperCollector\Providers\GroqOcrProvider;
use App\Services\PastPaperCollector\Providers\GroqQuestionProcessingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AiProviderResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function settings(array $overrides = []): AISetting
    {
        $settings = AISetting::current();
        $settings->update(array_merge([
            'gemini_enabled' => true,
            'groq_enabled' => true,
            'preferred_text_provider' => AISetting::PROVIDER_GROQ,
            'fallback_text_provider' => AISetting::PROVIDER_GEMINI,
            'gemini_api_key' => null,
            'groq_api_key' => null,
        ], $overrides));

        return $settings->fresh();
    }

    public function test_primary_groq_on_with_key_resolves_groq(): void
    {
        $settings = $this->settings([
            'groq_api_key' => 'gsk_primary_key_value',
            'gemini_api_key' => 'gemini-also-present',
        ]);

        $this->assertSame(AISetting::PROVIDER_GROQ, $settings->resolveActiveTextProvider());
        $this->assertInstanceOf(GroqQuestionAiService::class, app(QuestionAiService::class));
        $this->assertInstanceOf(GroqOcrProvider::class, app(OcrProvider::class));
        $this->assertInstanceOf(FreeWebSearchProvider::class, app(WebSearchProvider::class));
        $this->assertFalse(app(WebSearchProvider::class)->isConfigured());
        $this->assertInstanceOf(GroqQuestionProcessingProvider::class, app(QuestionProcessingProvider::class));
    }

    public function test_primary_groq_off_falls_back_to_gemini(): void
    {
        $settings = $this->settings([
            'groq_enabled' => false,
            'groq_api_key' => 'gsk_ignored',
            'gemini_api_key' => 'gemini-fallback-key',
        ]);

        $this->assertSame(AISetting::PROVIDER_GEMINI, $settings->resolveActiveTextProvider());
        $this->assertInstanceOf(GeminiQuestionAiService::class, app(QuestionAiService::class));
        $this->assertInstanceOf(GeminiOcrProvider::class, app(OcrProvider::class));
        $this->assertInstanceOf(GeminiWebSearchProvider::class, app(WebSearchProvider::class));
    }

    public function test_primary_groq_on_without_key_falls_back_to_gemini(): void
    {
        $settings = $this->settings([
            'groq_enabled' => true,
            'groq_api_key' => null,
            'gemini_api_key' => 'gemini-fallback-key',
        ]);

        $this->assertSame(AISetting::PROVIDER_GEMINI, $settings->resolveActiveTextProvider());
        $this->assertSame(AISetting::PROVIDER_GEMINI, app(AiProviderResolver::class)->resolvedId());
    }

    public function test_both_off_throws(): void
    {
        $settings = $this->settings([
            'groq_enabled' => false,
            'gemini_enabled' => false,
            'groq_api_key' => 'gsk_present',
            'gemini_api_key' => 'gemini-present',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No AI text provider available');
        $settings->resolveActiveTextProvider();
    }

    public function test_both_missing_keys_throws(): void
    {
        $settings = $this->settings([
            'groq_api_key' => null,
            'gemini_api_key' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No AI text provider available');
        $settings->resolveActiveTextProvider();
    }

    public function test_fallback_none_and_primary_unusable_throws(): void
    {
        $settings = $this->settings([
            'preferred_text_provider' => AISetting::PROVIDER_GROQ,
            'fallback_text_provider' => AISetting::FALLBACK_NONE,
            'groq_api_key' => null,
            'gemini_api_key' => 'gemini-present-but-not-fallback',
        ]);

        $this->assertNull($settings->tryResolveActiveTextProvider());
        $this->expectException(RuntimeException::class);
        $settings->resolveActiveTextProvider();
    }

    public function test_to_public_array_never_exposes_raw_keys(): void
    {
        $settings = $this->settings([
            'gemini_api_key' => 'secret-gemini-key-value',
            'groq_api_key' => 'gsk_secret_groq_key_value',
        ]);

        $public = $settings->toPublicArray();

        $this->assertTrue($public['has_gemini_api_key']);
        $this->assertTrue($public['has_groq_api_key']);
        $this->assertNotNull($public['gemini_key_masked']);
        $this->assertNotNull($public['groq_key_masked']);
        $this->assertSame(AISetting::PROVIDER_GROQ, $public['active_text_provider']);
        $this->assertArrayNotHasKey('gemini_api_key', $public);
        $this->assertArrayNotHasKey('groq_api_key', $public);
        $this->assertStringNotContainsString('secret-gemini-key-value', json_encode($public));
        $this->assertStringNotContainsString('gsk_secret_groq_key_value', json_encode($public));
    }
}
