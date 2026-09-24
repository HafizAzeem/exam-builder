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
        $questions = $import->questions()
            ->where('status', 'approved')
            ->where('is_duplicate', false)
            ->whereNotNull('chapter_id')
            ->orderBy('id')
            ->get();

        $result = $this->importStagingCollection($import, $questions);

        return [
            'imported' => $result['imported'],
            'duplicates' => $result['duplicates'],
            'failed' => $result['failed'],
        ];
    }

    /**
     * @param  list<int>  $stagingIds
     * @return array{imported:int, duplicates:int, failed:int, question_ids: list<int>}
     */
    public function importSelected(AIImport $import, array $stagingIds): array
    {
        $ids = collect($stagingIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        $questions = $import->questions()
            ->whereIn('id', $ids ?: [0])
            ->whereIn('status', ['approved', 'pending', 'duplicate'])
            ->where(function ($q) {
                $q->where('is_duplicate', false)
                    ->orWhereNotNull('duplicate_of_question_id');
            })
            ->whereNotNull('chapter_id')
            ->orderBy('id')
            ->get();

        // Teacher checklist: treat selected pending rows as approved before insert.
        foreach ($questions as $staging) {
            if ($staging->status === 'pending') {
                $staging->update(['status' => 'approved']);
                $staging->status = 'approved';
            }
        }

        return $this->importStagingCollection($import, $questions);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AIImportQuestion>  $questions
     * @return array{imported:int, duplicates:int, failed:int, question_ids: list<int>}
     */
    protected function importStagingCollection(AIImport $import, $questions): array
    {
        $stats = [
            'imported' => 0,
            'duplicates' => 0,
            'failed' => 0,
            'question_ids' => [],
        ];

        foreach ($questions as $staging) {
            try {
                if ($staging->imported_question_id) {
                    $stats['question_ids'][] = (int) $staging->imported_question_id;
                    $stats['imported']++;

                    continue;
                }

                // Already in bank: attach existing question to the paper only (no re-insert).
                if ($staging->is_duplicate && $staging->duplicate_of_question_id) {
                    $stats['question_ids'][] = (int) $staging->duplicate_of_question_id;
                    $stats['duplicates']++;
                    $staging->update([
                        'status' => 'imported',
                        'imported_question_id' => (int) $staging->duplicate_of_question_id,
                    ]);

                    continue;
                }

                if ($this->duplicates->markIfDuplicate($staging->fresh())) {
                    $fresh = $staging->fresh();
                    if ($fresh?->duplicate_of_question_id) {
                        $stats['question_ids'][] = (int) $fresh->duplicate_of_question_id;
                        $stats['duplicates']++;
                        $fresh->update([
                            'status' => 'imported',
                            'imported_question_id' => (int) $fresh->duplicate_of_question_id,
                        ]);
                    } else {
                        $stats['duplicates']++;
                    }

                    continue;
                }

                $question = DB::transaction(function () use ($staging, $import) {
                    return $this->importOne($staging, $import);
                });

                $stats['imported']++;
                $stats['question_ids'][] = $question->id;
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

        $staging->update([
            'status' => 'imported',
            'imported_question_id' => $question->id,
        ]);

        return $question;
    }
}
