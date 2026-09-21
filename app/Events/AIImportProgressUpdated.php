<?php

namespace App\Events;

use App\Models\AIImport;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AIImportProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AIImport $import) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('ai-import.'.$this->import->id)];
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
        return $this->import->statusPayload();
    }
}
