<?php

namespace App\Jobs;

use App\Models\AIImport;
use App\Services\AIImport\AIImportService;
use App\Services\AIImport\DuplicateDetectionService;
use App\Services\AIImport\GeminiService;
use App\Services\AIImport\QuestionParserService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ChunkProcessingJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public int $importId,
        public int $chunkIndex,
        public string $chunkText,
        public ?string $chunkTitle = null,
    ) {}

    public function handle(
        GeminiService $gemini,
        QuestionParserService $parser,
        AIImportService $imports,
        DuplicateDetectionService $duplicates,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $import = AIImport::find($this->importId);
        if (! $import) {
            return;
        }

        try {
            $result = $gemini->extractQuestions(
                $import,
                $this->chunkText,
                $this->chunkIndex,
                $this->chunkTitle,
            );

            $created = $parser->storeStagingQuestions($import, $result['questions']);
            $duplicates->scanMany($created);

            $import->increment('processed_chunks');
            $fresh = $import->fresh();
            $imports->updateProgress($fresh, (int) $fresh->processed_chunks);
            $fresh->refreshCounters();
        } catch (Throwable $e) {
            $import->increment('failed_count');
            throw $e;
        }
    }
}
