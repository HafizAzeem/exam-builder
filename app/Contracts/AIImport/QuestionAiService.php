<?php

namespace App\Contracts\AIImport;

use App\Models\AIImport;
use App\Models\AIImportLog;

interface QuestionAiService
{
    /**
     * @return array{questions: list<array<string, mixed>>, log: AIImportLog}
     */
    public function extractQuestions(
        AIImport $import,
        string $chunkText,
        int $chunkIndex,
        ?string $chunkTitle = null,
    ): array;

    /**
     * @return array{questions: list<array<string, mixed>>, log: AIImportLog}
     */
    public function generateQuestions(AIImport $import): array;

    public function name(): string;
}
