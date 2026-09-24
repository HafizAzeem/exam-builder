<?php

namespace App\Jobs;

use App\Models\AIImport;
use App\Models\AISetting;
use App\Services\AIImport\AIImportService;
use App\Services\AIImport\DuplicateDetectionService;
use App\Services\AIImport\GeminiService;
use App\Services\AIImport\QuestionParserService;
use App\Services\AIImport\TeacherPreferredWebHarvestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessGenerateQuestionsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        public AIImport $import,
    ) {}

    public function handle(
        GeminiService $gemini,
        QuestionParserService $parser,
        AIImportService $imports,
        DuplicateDetectionService $duplicates,
        TeacherPreferredWebHarvestService $webHarvest,
    ): void {
        $imports->markStatus($this->import, 'processing');

        try {
            $this->import->update([
                'total_chunks' => 2,
                'processed_chunks' => 0,
                'progress_percent' => 5,
            ]);
            $this->import->broadcastProgress();

            $counts = $this->import->meta['counts'] ?? [];
            $needed = max(1, array_sum(array_map('intval', is_array($counts) ? $counts : [])));

            // 1) Preferred education websites first (hidden default).
            $webQuestions = [];
            try {
                $this->import->update(['progress_percent' => 15]);
                $this->import->broadcastProgress();
                $webQuestions = $webHarvest->harvest($this->import);
            } catch (Throwable $e) {
                report($e);
            }

            $created = [];
            if ($webQuestions !== []) {
                $created = $parser->storeStagingQuestions($this->import, array_slice($webQuestions, 0, $needed + 10));
                $duplicates->scanMany($created);
            }

            $this->import->update([
                'processed_chunks' => 1,
                'progress_percent' => 55,
            ]);
            $this->import->broadcastProgress();
            $this->import->refreshCounters();

            $have = $this->import->questions()->where('is_duplicate', false)->count();
            $stillNeed = max(0, $needed - $have);

            // 2) Fill remaining with LLM generation (exercise / past-paper style).
            if ($stillNeed > 0) {
                $meta = $this->import->meta ?? [];
                $meta['counts'] = $this->scaleCounts($counts, $stillNeed, $needed);
                $this->import->update(['meta' => $meta]);

                $result = $gemini->generateQuestions($this->import->fresh());
                $generated = $parser->storeStagingQuestions($this->import, $result['questions']);
                $duplicates->scanMany($generated);
                $created = array_merge($created, $generated);
            }

            $imports->constrainChapters($this->import);
            $this->import->update([
                'processed_chunks' => 2,
                'progress_percent' => 100,
            ]);
            $this->import->refreshCounters();

            $finalCount = $this->import->questions()->where('is_duplicate', false)->count();
            if ($finalCount < 1) {
                $imports->markStatus(
                    $this->import->fresh(),
                    'failed',
                    'No questions were produced. Try different chapters or counts.'
                );

                return;
            }

            $imports->markStatus($this->import->fresh(), 'review');
        } catch (Throwable $e) {
            $imports->markStatus($this->import, 'failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $counts
     * @return array<string, int>
     */
    protected function scaleCounts(array $counts, int $stillNeed, int $originalTotal): array
    {
        $types = ['mcq', 'short', 'long', 'fill', 'truefalse'];
        $scaled = [];
        $assigned = 0;

        foreach ($types as $type) {
            $orig = (int) ($counts[$type] ?? 0);
            if ($originalTotal < 1 || $orig < 1) {
                $scaled[$type] = 0;

                continue;
            }
            $n = (int) max(0, round(($orig / $originalTotal) * $stillNeed));
            $scaled[$type] = $n;
            $assigned += $n;
        }

        // Distribute remainder to the largest original bucket.
        while ($assigned < $stillNeed) {
            $best = 'mcq';
            $bestOrig = -1;
            foreach ($types as $type) {
                $orig = (int) ($counts[$type] ?? 0);
                if ($orig > $bestOrig) {
                    $bestOrig = $orig;
                    $best = $type;
                }
            }
            $scaled[$best]++;
            $assigned++;
        }

        return $scaled;
    }

    public static function dispatchFor(AIImport $import): void
    {
        $settings = AISetting::current();

        if ($settings->enable_queue) {
            static::dispatch($import);
        } else {
            static::dispatchSync($import);
        }
    }
}
