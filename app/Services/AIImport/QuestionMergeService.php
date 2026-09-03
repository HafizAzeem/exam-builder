<?php

namespace App\Services\AIImport;

use App\Models\AIImport;
use App\Models\AIImportQuestion;
use App\Models\PastPaperTag;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

class QuestionMergeService
{
    public function __construct(
        protected DuplicateDetectionService $duplicates,
    ) {}

    /**
     * Explicitly merge a reviewed staging question into an existing production question.
     */
    public function merge(AIImportQuestion $staging, Question $existing, AIImport $import): Question
    {
        return DB::transaction(function () use ($staging, $existing, $import) {
            $existing->update([
                'chapter_id' => $staging->chapter_id ?: $existing->chapter_id,
                'topic_id' => $staging->topic_id ?: $existing->topic_id,
                'type' => $staging->type ?: $existing->type,
                'text_en' => $staging->text_en ?: $existing->text_en,
                'text_ur' => $staging->text_ur ?: $existing->text_ur,
                'difficulty' => $staging->difficulty ?: $existing->difficulty,
                'estimated_marks' => $staging->estimated_marks ?: $existing->estimated_marks,
                'has_parts' => (is_array($staging->parts) && count($staging->parts) > 0)
                    || (bool) $existing->has_parts,
            ]);

            if ($existing->type === 'mcq' && is_array($staging->mcq_options)) {
                $mcq = $staging->mcq_options;
                $existing->mcqOptions()->updateOrCreate(
                    ['question_id' => $existing->id],
                    [
                        'option_a_en' => $mcq['option_a_en'] ?? '',
                        'option_a_ur' => $mcq['option_a_ur'] ?? null,
                        'option_b_en' => $mcq['option_b_en'] ?? '',
                        'option_b_ur' => $mcq['option_b_ur'] ?? null,
                        'option_c_en' => $mcq['option_c_en'] ?? '',
                        'option_c_ur' => $mcq['option_c_ur'] ?? null,
                        'option_d_en' => $mcq['option_d_en'] ?? '',
                        'option_d_ur' => $mcq['option_d_ur'] ?? null,
                        'correct_option' => $mcq['correct_option'] ?? 'a',
                    ]
                );
            }

            if ($existing->source === 'past_paper' || $staging->source === 'past_paper') {
                PastPaperTag::updateOrCreate(
                    ['question_id' => $existing->id],
                    [
                        'board_name' => $import->board ?: 'Unknown Board',
                        'year' => $import->year ?: (int) date('Y'),
                        'session' => $import->session,
                    ]
                );
                if ($existing->source !== 'past_paper') {
                    $existing->update(['source' => 'past_paper']);
                }
            }

            $staging->update([
                'status' => 'imported',
                'is_duplicate' => true,
                'duplicate_of_question_id' => $existing->id,
                'review_notes' => trim(($staging->review_notes ? $staging->review_notes."\n" : '').'Merged into question #'.$existing->id),
                'duplicate_diff' => array_merge($staging->duplicate_diff ?? [], [
                    'merged_at' => now()->toIso8601String(),
                    'merged_into' => $existing->id,
                ]),
            ]);

            return $existing->fresh(['mcqOptions', 'pastPaperTag']);
        });
    }
}
