<?php

namespace App\Services\AIImport;

use App\Contracts\AIImport\QuestionAiService;
use App\Contracts\PastPaperCollector\OcrProvider;
use App\Contracts\PastPaperCollector\QuestionProcessingProvider;
use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Models\AISetting;
use App\Services\AIImport\Providers\GeminiQuestionAiService;
use App\Services\AIImport\Providers\GroqQuestionAiService;
use App\Services\PastPaperCollector\Providers\GeminiOcrProvider;
use App\Services\PastPaperCollector\Providers\GeminiQuestionProcessingProvider;
use App\Services\PastPaperCollector\Providers\FreeWebSearchProvider;
use App\Services\PastPaperCollector\Providers\GeminiWebSearchProvider;
use App\Services\PastPaperCollector\Providers\GroqOcrProvider;
use App\Services\PastPaperCollector\Providers\GroqQuestionProcessingProvider;

class AiProviderResolver
{
    public function activeTextProvider(): string
    {
        return AISetting::current()->resolveActiveTextProvider();
    }

    public function tryActiveTextProvider(): ?string
    {
        return AISetting::current()->tryResolveActiveTextProvider();
    }

    /**
     * Bindable implementation for this request: primary if usable, else fallback.
     * When neither is usable, Gemini is bound so OCR/unit tests still resolve a provider.
     *
     * Runtime retry of a failed primary against fallback is left to the job/service
     * catch path (resolve-once per request keeps bindings cheap and predictable).
     */
    public function resolvedId(): string
    {
        return $this->tryActiveTextProvider() ?: AISetting::PROVIDER_GEMINI;
    }

    public function questionAiService(): QuestionAiService
    {
        return $this->resolvedId() === AISetting::PROVIDER_GROQ
            ? app(GroqQuestionAiService::class)
            : app(GeminiQuestionAiService::class);
    }

    public function questionProcessingProvider(): QuestionProcessingProvider
    {
        return $this->resolvedId() === AISetting::PROVIDER_GROQ
            ? app(GroqQuestionProcessingProvider::class)
            : app(GeminiQuestionProcessingProvider::class);
    }

    public function ocrProvider(): OcrProvider
    {
        return $this->resolvedId() === AISetting::PROVIDER_GROQ
            ? app(GroqOcrProvider::class)
            : app(GeminiOcrProvider::class);
    }

    public function webSearchProvider(): WebSearchProvider
    {
        $free = app(FreeWebSearchProvider::class);
        if ($free->isConfigured()) {
            return $free;
        }

        // Groq browser_search is never bound: it 429s the free tier before generate.
        if ($this->tryActiveTextProvider() === AISetting::PROVIDER_GEMINI) {
            return app(GeminiWebSearchProvider::class);
        }

        return $free;
    }
}
