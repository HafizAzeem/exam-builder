<?php

namespace App\Services\AIImport;

use App\Models\AIImport;
use App\Models\AIImportQuestion;
use App\Models\McqOption;
use App\Models\PastPaperTag;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

class QuestionImportService
{
    public function __construct(
        protected DuplicateDetectionService $duplicates,
    ) {}

    /**
     * @return array{imported:int, duplicates:int, failed:int}
     */
    public function importApproved(AIImport $import): array
    {
        $stats = ['imported' => 0, 'duplicates' => 0, 'failed' => 0];

        $questions = $import->questions()
            ->where('status', 'approved')
            ->where('is_duplicate', false)
            ->whereNotNull('chapter_id')
            ->orderBy('id')
            ->get();

        foreach ($questions as $staging) {
            try {
                if ($this->duplicates->markIfDuplicate($staging->fresh())) {
                    $stats['duplicates']++;

                    continue;
                }

                DB::transaction(function () use ($staging, $import) {
                    $this->importOne($staging, $import);
                });

                $stats['imported']++;
            } catch (\Throwable $e) {
                $staging->update([
                    'status' => 'failed',
                    'review_notes' => $e->getMessage(),
                ]);
                $stats['failed']++;
            }
        }

        $import->refreshCounters();

        return $stats;
    }

    protected function importOne(AIImportQuestion $staging, AIImport $import): Question
    {
        $parts = is_array($staging->parts) ? $staging->parts : [];

        $question = Question::create([
            'chapter_id' => $staging->chapter_id,
            'topic_id' => $staging->topic_id,
            'type' => $staging->type,
            'source' => $staging->source,
            'text_en' => $staging->text_en,
            'text_ur' => $staging->text_ur,
            'has_parts' => count($parts) > 0,
            'is_active' => true,
            'difficulty' => $staging->difficulty,
            'estimated_marks' => $staging->estimated_marks,
        ]);

        if ($question->type === 'mcq' && is_array($staging->mcq_options)) {
            $mcq = $staging->mcq_options;
            McqOption::create([
                'question_id' => $question->id,
                'option_a_en' => $mcq['option_a_en'] ?? '',
                'option_a_ur' => $mcq['option_a_ur'] ?? null,
                'option_b_en' => $mcq['option_b_en'] ?? '',
                'option_b_ur' => $mcq['option_b_ur'] ?? null,
                'option_c_en' => $mcq['option_c_en'] ?? '',
                'option_c_ur' => $mcq['option_c_ur'] ?? null,
                'option_d_en' => $mcq['option_d_en'] ?? '',
                'option_d_ur' => $mcq['option_d_ur'] ?? null,
                'correct_option' => $mcq['correct_option'] ?? 'a',
            ]);
        }

        if ($question->source === 'past_paper') {
            PastPaperTag::create([
                'question_id' => $question->id,
                'board_name' => $import->board ?: 'Unknown Board',
                'year' => $import->year ?: (int) date('Y'),
                'session' => $import->session,
            ]);
        }

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            Question::create([
                'chapter_id' => $question->chapter_id,
                'topic_id' => $question->topic_id,
                'type' => $question->type,
                'source' => $question->source,
                'text_en' => $part['text_en'] ?? null,
                'text_ur' => $part['text_ur'] ?? null,
                'parent_question_id' => $question->id,
                'is_active' => true,
            ]);
        }

        $staging->update(['status' => 'imported']);

        return $question;
    }
}
