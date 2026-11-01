<?php

namespace App\Jobs;

use App\Models\AssistantRequest;
use App\Services\QuestionAssistant\AssistantBudgetService;
use App\Services\QuestionAssistant\QuestionAssistantService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessQuestionAssistantJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $requestId) {}

    public function handle(QuestionAssistantService $assistant): void
    {
        $request = AssistantRequest::find($this->requestId);
        if ($request) {
            $assistant->process($request);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $request = AssistantRequest::find($this->requestId);
        if ($request) {
            app(AssistantBudgetService::class)->fail($request, 'Assistant processing stopped. Your request has been refunded.');
        }
    }
}
