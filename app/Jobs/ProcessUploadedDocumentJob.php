<?php

namespace App\Jobs;

use App\Models\AIImport;
use App\Models\AISetting;
use App\Services\AIImport\AIImportService;
use App\Services\AIImport\DocumentChunkerService;
use App\Services\AIImport\DocumentTextExtractionService;
use App\Services\AIImport\DuplicateDetectionService;
use App\Services\AIImport\GeminiService;
use App\Services\AIImport\QuestionParserService;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Throwable;

class ProcessUploadedDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public AIImport $import,
    ) {}

    public function handle(
        DocumentTextExtractionService $extractor,
        DocumentChunkerService $chunker,
        AIImportService $imports,
    ): void {
        $imports->markStatus($this->import, 'extracting');

        try {
            $text = $extractor->extract($this->import);

            if (trim($text) === '') {
                throw new \RuntimeException('No text could be extracted from the uploaded file.');
            }

            $chunks = $chunker->chunk($text);

            $this->import->update([
                'status' => 'processing',
                'total_chunks' => count($chunks),
                'processed_chunks' => 0,
                'progress_percent' => 0,
            ]);
            $this->import->broadcastProgress();

            $jobs = [];
            foreach ($chunks as $chunk) {
                $jobs[] = new ChunkProcessingJob(
                    $this->import->id,
                    $chunk['index'],
                    $chunk['text'],
                    $chunk['title'] ?? null,
                );
            }

            $settings = AISetting::current();

            if (! $settings->enable_queue) {
                foreach ($jobs as $job) {
                    $job->handle(
                        app(GeminiService::class),
                        app(QuestionParserService::class),
                        app(AIImportService::class),
                        app(DuplicateDetectionService::class),
                    );
                }

                $this->finalize();

                return;
            }

            $importId = $this->import->id;

            Bus::batch($jobs)
                ->name("ai-import-{$importId}")
                ->then(function (Batch $batch) use ($importId) {
                    $import = AIImport::find($importId);
                    if (! $import) {
                        return;
                    }

                    $import->refreshCounters();
                    app(AIImportService::class)->markStatus($import, 'review');
                })
                ->catch(function (Batch $batch, Throwable $e) use ($importId) {
                    $import = AIImport::find($importId);
                    if ($import) {
                        app(AIImportService::class)->markStatus($import, 'failed', $e->getMessage());
                    }
                })
                ->dispatch();
        } catch (Throwable $e) {
            $imports->markStatus($this->import, 'failed', $e->getMessage());
            throw $e;
        }
    }

    protected function finalize(): void
    {
        $this->import->refreshCounters();
        app(AIImportService::class)->markStatus($this->import->fresh(), 'review');
    }
}
