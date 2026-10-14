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
            $prepared = $this->prepareRawQuestion($raw);
            if ($prepared === null) {
                continue;
            }

            $type = $prepared['type'];
            $textEn = $prepared['text_en'];
            $textUr = $prepared['text_ur'];
            $raw = $prepared['raw'];

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
                'mcq_options' => $prepared['mcq_options'],
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

    /**
     * Keep at most the requested number of each type (MCQ/short/long/…).
     *
     * @param  list<mixed>  $rawQuestions
     * @param  array<string, mixed>  $counts
     * @return list<array<string, mixed>>
     */
    public function takeRequestedCounts(array $rawQuestions, array $counts): array
    {
        $types = ['mcq', 'short', 'long', 'fill', 'truefalse'];
        $wanted = [];
        $anyLimit = false;
        foreach ($types as $type) {
            $wanted[$type] = max(0, (int) ($counts[$type] ?? 0));
            if ($wanted[$type] > 0) {
                $anyLimit = true;
            }
        }
        if (! $anyLimit) {
            return array_values(array_filter(array_map(
                fn ($raw) => is_array($raw) ? ($this->prepareRawQuestion($raw)['raw'] ?? null) : null,
                $rawQuestions
            )));
        }

        $taken = array_fill_keys($types, 0);
        $out = [];
        foreach ($rawQuestions as $raw) {
            $prepared = $this->prepareRawQuestion($raw);
            if ($prepared === null) {
                continue;
            }
            $type = $prepared['type'];
            if (($taken[$type] ?? 0) >= ($wanted[$type] ?? 0)) {
                continue;
            }
            $taken[$type]++;
            $out[] = $prepared['raw'];
        }

        return $out;
    }

    /**
     * @param  mixed  $raw
     * @return array{type:string,text_en:?string,text_ur:?string,mcq_options:?array,raw:array<string,mixed>}|null
     */
    public function prepareRawQuestion(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $type = $this->normalizeType($raw['type'] ?? null);
        if (! $type) {
            return null;
        }

        $textEn = $this->nullableString($raw['text_en'] ?? $raw['question'] ?? $raw['text'] ?? $raw['stem'] ?? null);
        $textUr = $this->nullableString($raw['text_ur'] ?? $raw['question_ur'] ?? null);
        if (! $textEn && ! $textUr) {
            return null;
        }

        $mcq = $type === 'mcq'
            ? $this->normalizeMcq($raw['mcq_options'] ?? $raw['options'] ?? $raw['choices'] ?? $raw)
            : null;

        if ($type === 'mcq' && ! $this->mcqHasFourOptions($mcq)) {
            $type = 'fill';
            $mcq = null;
        }

        if ($type !== 'mcq') {
            $mcq = null;
        }

        $raw['type'] = $type;
        $raw['text_en'] = $textEn;
        $raw['text_ur'] = $textUr;
        $raw['mcq_options'] = $mcq;

        return [
            'type' => $type,
            'text_en' => $textEn,
            'text_ur' => $textUr,
            'mcq_options' => $mcq,
            'raw' => $raw,
        ];
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
        $options = $this->coerceMcqOptions($options);
        if ($options === null) {
            return null;
        }

        $correct = strtolower((string) ($options['correct_option'] ?? $options['answer'] ?? $options['correct'] ?? 'a'));
        $correct = preg_replace('/[^a-d]/', '', $correct) ?? '';
        if (! in_array($correct, ['a', 'b', 'c', 'd'], true)) {
            $correct = 'a';
        }

        return [
            'option_a_en' => $this->nullableString($options['option_a_en'] ?? $options['a_en'] ?? $options['a'] ?? $options['A'] ?? null) ?? '',
            'option_a_ur' => $this->nullableString($options['option_a_ur'] ?? $options['a_ur'] ?? null),
            'option_b_en' => $this->nullableString($options['option_b_en'] ?? $options['b_en'] ?? $options['b'] ?? $options['B'] ?? null) ?? '',
            'option_b_ur' => $this->nullableString($options['option_b_ur'] ?? $options['b_ur'] ?? null),
            'option_c_en' => $this->nullableString($options['option_c_en'] ?? $options['c_en'] ?? $options['c'] ?? $options['C'] ?? null) ?? '',
            'option_c_ur' => $this->nullableString($options['option_c_ur'] ?? $options['c_ur'] ?? null),
            'option_d_en' => $this->nullableString($options['option_d_en'] ?? $options['d_en'] ?? $options['d'] ?? $options['D'] ?? null) ?? '',
            'option_d_ur' => $this->nullableString($options['option_d_ur'] ?? $options['d_ur'] ?? null),
            'correct_option' => $correct,
        ];
    }

    /**
     * @param  mixed  $options
     * @return array<string, mixed>|null
     */
    protected function coerceMcqOptions(mixed $options): ?array
    {
        if (! is_array($options) || $options === []) {
            return null;
        }

        if (isset($options['type'], $options['text_en']) || isset($options['type'], $options['question'])) {
            $nested = $options['mcq_options'] ?? $options['options'] ?? $options['choices'] ?? null;
            if (is_array($nested)) {
                $options = $nested;
            }
        }

        if (array_is_list($options)) {
            $letters = ['a', 'b', 'c', 'd'];
            $mapped = [];
            foreach (array_slice($options, 0, 4) as $i => $item) {
                $text = is_array($item)
                    ? ($item['text'] ?? $item['text_en'] ?? $item['en'] ?? $item['value'] ?? null)
                    : $item;
                $mapped['option_'.$letters[$i].'_en'] = $text;
            }

            return $mapped;
        }

        return $options;
    }

    protected function mcqHasFourOptions(?array $options): bool
    {
        if ($options === null) {
            return false;
        }

        foreach (['a', 'b', 'c', 'd'] as $letter) {
            $en = trim((string) ($options['option_'.$letter.'_en'] ?? ''));
            $ur = trim((string) ($options['option_'.$letter.'_ur'] ?? ''));
            if ($en === '' && $ur === '') {
                return false;
            }
        }

        return true;
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
