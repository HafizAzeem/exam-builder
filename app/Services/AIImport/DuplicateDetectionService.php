<?php

namespace App\Services\AIImport;

use App\Models\AIImportQuestion;
use App\Models\AISetting;
use App\Models\Question;
use Illuminate\Support\Str;

class DuplicateDetectionService
{
    public function markIfDuplicate(AIImportQuestion $staging): bool
    {
        $match = $this->findDuplicateMatch($staging);

        if (! $match) {
            $staging->update([
                'is_duplicate' => false,
                'duplicate_of_question_id' => null,
                'duplicate_score' => null,
                'duplicate_diff' => null,
            ]);

            return false;
        }

        $staging->update([
            'is_duplicate' => true,
            'duplicate_of_question_id' => $match['question']->id,
            'duplicate_score' => $match['score'],
            'duplicate_diff' => $match['diff'],
            'status' => $staging->status === 'approved' ? 'duplicate' : $staging->status,
        ]);

        return true;
    }

    public function findDuplicate(AIImportQuestion $staging): ?Question
    {
        return $this->findDuplicateMatch($staging)['question'] ?? null;
    }

    /**
     * @return array{question:Question,score:float,diff:array<string,mixed>}|null
     */
    public function findDuplicateMatch(AIImportQuestion $staging): ?array
    {
        $threshold = (float) AISetting::current()->duplicate_similarity_threshold;
        $needleEn = $this->normalize($staging->text_en);
        $needleUr = $this->normalize($staging->text_ur);

        if ($needleEn === '' && $needleUr === '') {
            return null;
        }

        $query = Question::query()
            ->with(['pastPaperTag', 'mcqOptions', 'chapter:id,number,title_en', 'topic:id,title_en'])
            ->where('type', $staging->type)
            ->whereNull('parent_question_id');

        if ($staging->chapter_id) {
            $query->where('chapter_id', $staging->chapter_id);
        }

        if ($staging->topic_id) {
            $query->where(function ($q) use ($staging) {
                $q->where('topic_id', $staging->topic_id)
                    ->orWhereNull('topic_id');
            });
        }

        $import = $staging->relationLoaded('import') ? $staging->import : $staging->import()->first();
        if ($import && $import->book_type === 'past_paper') {
            $query->where('source', 'past_paper')
                ->whereHas('pastPaperTag', function ($q) use ($import) {
                    if ($import->board) {
                        $q->where('board_name', $import->board);
                    }
                    if ($import->year) {
                        $q->where('year', $import->year);
                    }
                });
        }

        $candidates = $query->limit(200)->get();
        $best = null;

        foreach ($candidates as $candidate) {
            $score = $this->similarityScore($staging, $candidate, $needleEn, $needleUr);
            if ($score < $threshold) {
                continue;
            }

            if (! $best || $score > $best['score']) {
                $best = [
                    'question' => $candidate,
                    'score' => round($score, 4),
                    'diff' => $this->buildDiff($staging, $candidate, $score),
                ];
            }
        }

        return $best;
    }

    /**
     * @param  iterable<AIImportQuestion>  $questions
     */
    public function scanMany(iterable $questions): int
    {
        $count = 0;
        foreach ($questions as $question) {
            if ($this->markIfDuplicate($question)) {
                $count++;
            }
        }

        return $count;
    }

    protected function similarityScore(
        AIImportQuestion $staging,
        Question $candidate,
        string $needleEn,
        string $needleUr,
    ): float {
        $en = $this->normalize($candidate->text_en);
        $ur = $this->normalize($candidate->text_ur);

        $scores = [];

        if ($needleEn && $en) {
            if ($needleEn === $en) {
                return 1.0;
            }
            similar_text($needleEn, $en, $percent);
            $scores[] = $percent / 100;
        }

        if ($needleUr && $ur) {
            if ($needleUr === $ur) {
                return 1.0;
            }
            similar_text($needleUr, $ur, $percent);
            $scores[] = $percent / 100;
        }

        return $scores === [] ? 0.0 : max($scores);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildDiff(AIImportQuestion $staging, Question $candidate, float $score): array
    {
        return [
            'score' => round($score, 4),
            'fields' => [
                'text_en' => [
                    'staging' => $staging->text_en,
                    'existing' => $candidate->text_en,
                    'changed' => $this->normalize($staging->text_en) !== $this->normalize($candidate->text_en),
                ],
                'text_ur' => [
                    'staging' => $staging->text_ur,
                    'existing' => $candidate->text_ur,
                    'changed' => $this->normalize($staging->text_ur) !== $this->normalize($candidate->text_ur),
                ],
                'type' => [
                    'staging' => $staging->type,
                    'existing' => $candidate->type,
                    'changed' => $staging->type !== $candidate->type,
                ],
                'chapter_id' => [
                    'staging' => $staging->chapter_id,
                    'existing' => $candidate->chapter_id,
                    'changed' => $staging->chapter_id !== $candidate->chapter_id,
                ],
                'topic_id' => [
                    'staging' => $staging->topic_id,
                    'existing' => $candidate->topic_id,
                    'changed' => $staging->topic_id !== $candidate->topic_id,
                ],
                'difficulty' => [
                    'staging' => $staging->difficulty,
                    'existing' => $candidate->difficulty,
                    'changed' => $staging->difficulty !== $candidate->difficulty,
                ],
                'estimated_marks' => [
                    'staging' => $staging->estimated_marks,
                    'existing' => $candidate->estimated_marks,
                    'changed' => $staging->estimated_marks !== $candidate->estimated_marks,
                ],
            ],
            'existing_question_id' => $candidate->id,
        ];
    }

    protected function normalize(?string $value): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));
    }
}
