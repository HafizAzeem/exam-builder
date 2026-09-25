<?php

namespace App\Services\AIImport;

use App\Models\AIImport;
use App\Models\AIImportQuestion;

class QuestionParserService
{
    public function __construct(
        protected CurriculumMatchService $matcher,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rawQuestions
     * @param  array<string, mixed>  $defaults
     * @return list<AIImportQuestion>
     */
    public function storeStagingQuestions(AIImport $import, array $rawQuestions, array $defaults = []): array
    {
        $source = $import->sourceForBookType();
        $created = [];

        foreach ($rawQuestions as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $type = $this->normalizeType($raw['type'] ?? null);
            if (! $type) {
                continue;
            }

            $textEn = $this->nullableString($raw['text_en'] ?? null);
            $textUr = $this->nullableString($raw['text_ur'] ?? null);

            if (! $textEn && ! $textUr) {
                continue;
            }

            $chapterNumber = isset($raw['chapter_number']) ? (int) $raw['chapter_number'] : null;
            $chapterTitle = $this->nullableString($raw['chapter_title'] ?? null);
            $topicTitle = $this->nullableString($raw['topic'] ?? $raw['topic_title'] ?? null);

            $match = $this->matcher->match(
                $import->subject_id,
                $chapterNumber,
                $chapterTitle,
                $topicTitle,
            );

            $created[] = AIImportQuestion::create([
                'ai_import_id' => $import->id,
                'ai_paper_source_id' => $defaults['ai_paper_source_id'] ?? null,
                'chapter_number' => $chapterNumber,
                'chapter_title' => $chapterTitle,
                'topic_title' => $topicTitle,
                'chapter_id' => $match['chapter_id'],
                'topic_id' => $match['topic_id'],
                'match_status' => $match['match_status'],
                'type' => $type,
                'source' => $defaults['source'] ?? $source,
                'text_en' => $textEn,
                'text_ur' => $textUr,
                'mcq_options' => $type === 'mcq' ? $this->normalizeMcq($raw['mcq_options'] ?? null) : null,
                'parts' => $this->normalizeParts($raw['parts'] ?? null),
                'status' => 'pending',
                'raw_payload' => $raw,
                'confidence_score' => $this->nullableFloat($raw['confidence_score'] ?? null),
                'difficulty' => $this->normalizeDifficulty($raw['difficulty'] ?? null),
                'estimated_marks' => isset($raw['estimated_marks']) ? (int) $raw['estimated_marks'] : null,
                'source_url' => $defaults['source_url'] ?? ($raw['source_url'] ?? null),
                'source_excerpt' => $defaults['source_excerpt'] ?? null,
            ]);
        }

        return $created;
    }

    protected function normalizeType(mixed $type): ?string
    {
        $type = strtolower(trim((string) $type));
        $map = [
            'mcq' => 'mcq',
            'multiple choice' => 'mcq',
            'short' => 'short',
            'short answer' => 'short',
            'long' => 'long',
            'long answer' => 'long',
            'fill' => 'fill',
            'fill in blank' => 'fill',
            'fill in the blank' => 'fill',
            'truefalse' => 'truefalse',
            'true/false' => 'truefalse',
            'true false' => 'truefalse',
        ];

        return $map[$type] ?? (in_array($type, ['mcq', 'short', 'long', 'fill', 'truefalse'], true) ? $type : null);
    }

    protected function normalizeDifficulty(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['easy', 'medium', 'hard'], true) ? $value : null;
    }

    protected function normalizeMcq(mixed $options): ?array
    {
        if (! is_array($options)) {
            return null;
        }

        return [
            'option_a_en' => $this->nullableString($options['option_a_en'] ?? $options['a_en'] ?? $options['a'] ?? null) ?? '',
            'option_a_ur' => $this->nullableString($options['option_a_ur'] ?? $options['a_ur'] ?? null),
            'option_b_en' => $this->nullableString($options['option_b_en'] ?? $options['b_en'] ?? $options['b'] ?? null) ?? '',
            'option_b_ur' => $this->nullableString($options['option_b_ur'] ?? $options['b_ur'] ?? null),
            'option_c_en' => $this->nullableString($options['option_c_en'] ?? $options['c_en'] ?? $options['c'] ?? null) ?? '',
            'option_c_ur' => $this->nullableString($options['option_c_ur'] ?? $options['c_ur'] ?? null),
            'option_d_en' => $this->nullableString($options['option_d_en'] ?? $options['d_en'] ?? $options['d'] ?? null) ?? '',
            'option_d_ur' => $this->nullableString($options['option_d_ur'] ?? $options['d_ur'] ?? null),
            'correct_option' => in_array(($options['correct_option'] ?? 'a'), ['a', 'b', 'c', 'd'], true)
                ? $options['correct_option']
                : 'a',
        ];
    }

    protected function normalizeParts(mixed $parts): ?array
    {
        if (! is_array($parts) || $parts === []) {
            return null;
        }

        $normalized = [];
        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }
            $normalized[] = [
                'text_en' => $this->nullableString($part['text_en'] ?? null),
                'text_ur' => $this->nullableString($part['text_ur'] ?? null),
            ];
        }

        return $normalized ?: null;
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(1, (float) $value));
    }
}
