<?php

namespace App\Contracts\PastPaperCollector;

use App\Models\AIImport;
use App\Models\AIImportLog;

interface QuestionProcessingProvider
{
    /**
     * @return array{questions: list<array<string, mixed>>, log: AIImportLog}
     */
    public function extractQuestions(
        AIImport $import,
        string $chunkText,
        int $chunkIndex,
        ?string $chunkTitle = null,
        array $extraMeta = [],
    ): array;

    public function name(): string;

    public function isConfigured(): bool;
}
