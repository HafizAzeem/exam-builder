<?php

namespace App\Jobs;

use App\Models\AIPaperCollection;
use App\Services\PastPaperCollector\PastPaperCollectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
}
