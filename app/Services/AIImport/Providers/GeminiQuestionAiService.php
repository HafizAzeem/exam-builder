<?php

namespace App\Services\AIImport\Providers;

use App\Contracts\AIImport\QuestionAiService;
use App\Models\AIImport;
use App\Models\AISetting;
use App\Services\AIImport\GeminiService;

class GeminiQuestionAiService implements QuestionAiService
{
    public function __construct(
        protected GeminiService $gemini,
    ) {}

    public function name(): string
    {
        return AISetting::PROVIDER_GEMINI;
    }

    public function extractQuestions(
        AIImport $import,
        string $chunkText,
        int $chunkIndex,
        ?string $chunkTitle = null,
    ): array {
        return $this->gemini->extractQuestions($import, $chunkText, $chunkIndex, $chunkTitle);
    }

    public function generateQuestions(AIImport $import): array
    {
        return $this->gemini->generateQuestions($import);
    }
}
