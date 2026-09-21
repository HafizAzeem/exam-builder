<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PaperPdfStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $paperId,
        public string $status,
        public ?string $pdfUrl = null,
        public ?string $message = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('paper.'.$this->paperId)];
    }

    public function broadcastAs(): string
    {
        return 'pdf.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'paper_id' => $this->paperId,
            'status' => $this->status,
            'pdf_url' => $this->pdfUrl,
            'message' => $this->message,
        ];
    }
}
