<?php

namespace App\Jobs;

use App\Models\AIImport;
use App\Models\AIPaperCollection;
use App\Services\PastPaperCollector\PastPaperCollectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CollectPastPapersJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public AIPaperCollection $collection,
    ) {}

    public function handle(PastPaperCollectionService $service): void
    {
        $service->runCollection($this->collection->fresh());
    }

    public function failed(?Throwable $e = null): void
    {
        $collection = $this->collection->fresh();
        if (! $collection || in_array($collection->status, ['review', 'completed'], true)) {
            return;
        }

        $message = AIImport::teacherFacingError($e?->getMessage())
            ?: 'Web search is temporarily down. Wait a few minutes and try Get past paper again, or paste the paper text instead.';

        $collection->update([
            'status' => 'failed',
            'progress_stage' => 'failed',
            'progress_percent' => 100,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
        $collection->broadcastProgress();
        $collection->import?->update([
            'status' => 'failed',
            'error_message' => $message,
            'progress_percent' => 100,
        ]);
        $collection->import?->broadcastProgress();
    }
}
