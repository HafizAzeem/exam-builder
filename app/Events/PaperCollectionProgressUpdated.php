<?php

namespace App\Events;

use App\Models\AIPaperCollection;
use App\Services\PastPaperCollector\PastPaperCollectionService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaperCollectionProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AIPaperCollection $collection) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('paper-collection.'.$this->collection->id)];
    }

    public function broadcastAs(): string
    {
        return 'progress.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return app(PastPaperCollectionService::class)->statusPayload($this->collection);
    }
}
