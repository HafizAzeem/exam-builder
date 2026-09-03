<?php

namespace App\Jobs;

use App\Models\AIImport;
use App\Services\AIImport\AIImportService;
use App\Services\AIImport\QuestionImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportApprovedQuestionsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public AIImport $import,
    ) {}

    public function handle(QuestionImportService $importer, AIImportService $imports): void
    {
        $imports->markStatus($this->import, 'importing');

        try {
            $importer->importApproved($this->import);
            $this->import->refreshCounters();

            $pending = $this->import->questions()->where('status', 'approved')->count();
            $imports->markStatus(
                $this->import->fresh(),
                $pending > 0 ? 'review' : 'completed'
            );
        } catch (Throwable $e) {
            $imports->markStatus($this->import, 'failed', $e->getMessage());
            throw $e;
        }
    }
}
