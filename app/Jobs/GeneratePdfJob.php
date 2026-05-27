<?php

namespace App\Jobs;

use App\Models\SavedPaper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GeneratePdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SavedPaper $paper,
        public string $printUrl,
    ) {}

    public function handle(): void
    {
        $outputDir = "papers/{$this->paper->institution_id}";
        $outputPath = "{$outputDir}/{$this->paper->id}.pdf";

        Storage::disk('public')->makeDirectory($outputDir);

        $script = base_path('puppeteer/generate.js');
        $fullPath = Storage::disk('public')->path($outputPath);

        if (! file_exists($script)) {
            Log::warning('Puppeteer script missing; PDF generation skipped.', [
                'paper_id' => $this->paper->id,
            ]);

            return;
        }

        $cmd = sprintf(
            'node %s --url=%s --output=%s',
            escapeshellarg($script),
            escapeshellarg($this->printUrl),
            escapeshellarg($fullPath),
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            Log::error('PDF generation failed', ['output' => $output, 'paper_id' => $this->paper->id]);
        }
    }
}
