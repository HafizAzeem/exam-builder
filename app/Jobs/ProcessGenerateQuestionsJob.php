<?php

namespace App\Jobs;

use App\Contracts\AIImport\QuestionAiService;
use App\Models\AIImport;
use App\Models\AISetting;
use App\Services\AIImport\AIImportService;
use App\Services\AIImport\DuplicateDetectionService;
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
        QuestionAiService $questionAi,
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
                $created = $parser->storeStagingQuestions(
                    $this->import,
                    $parser->takeRequestedCounts($webQuestions, is_array($counts) ? $counts : [])
                );
                $duplicates->scanMany($created);
            }

            $this->import->update([
                'processed_chunks' => 1,
                'progress_percent' => 55,
            ]);
            $this->import->broadcastProgress();
            $this->import->refreshCounters();

            $stillCounts = $this->remainingCounts($this->import, is_array($counts) ? $counts : []);
            $stillNeed = array_sum($stillCounts);

            // 2) Fill remaining with LLM generation (exercise / past-paper style).
            if ($stillNeed > 0) {
                $meta = $this->import->meta ?? [];
                $meta['counts'] = $stillCounts;
                $this->import->update(['meta' => $meta]);

                $result = $questionAi->generateQuestions($this->import->fresh());
                $generated = $parser->storeStagingQuestions(
                    $this->import,
                    $parser->takeRequestedCounts($result['questions'] ?? [], $stillCounts)
                );
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
            report($e);
            $imports->markStatus(
                $this->import,
                'failed',
                'No questions were produced. Try different chapters or counts.'
            );
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $requested
     * @return array<string, int>
     */
    protected function remainingCounts(AIImport $import, array $requested): array
    {
        $have = $import->questions()
            ->where('is_duplicate', false)
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $remaining = [];
        foreach (['mcq', 'short', 'long', 'fill', 'truefalse'] as $type) {
            $remaining[$type] = max(0, (int) ($requested[$type] ?? 0) - (int) ($have[$type] ?? 0));
        }

        return $remaining;
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
