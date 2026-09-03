<?php

namespace App\Jobs;

use App\Models\AIPaperSource;
use App\Services\PastPaperCollector\PastPaperCollectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPaperSourceJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public AIPaperSource $source,
    ) {}

    public function handle(PastPaperCollectionService $service): void
    {
        $source = $this->source->fresh();
        if (! $source || ! in_array($source->status, ['discovered', 'failed', 'ocr_required'], true)) {
            return;
        }

        $service->processSource($source);
    }
}
