<?php

namespace App\Services\QuestionAssistant;

class QuestionOnlyPayload
{
    public function filter(array $questions, array $sources, int $limit): array
    {
        $out = [];
        $seen = [];
        foreach ($questions as $question) {
            if (! is_array($question) || ! in_array($question['type'] ?? '', ['mcq', 'short', 'long', 'fill', 'truefalse'], true)) {
                continue;
            }
            $en = $this->text($question['text_en'] ?? null);
            $ur = $this->text($question['text_ur'] ?? null);
            if (! $en && ! $ur) {
                continue;
            }
            $source = null;
            foreach ($sources as $candidate) {
                if (! empty($candidate['image']) || (($en && $this->contains($candidate['text'] ?? '', $en)) || ($ur && $this->contains($candidate['text'] ?? '', $ur)))) {
                    $source = $candidate;
                    break;
                }
            }
            if (! $source) {
                continue;
            }
            // Do not retain translations/other text that cannot be verified against this source.
            if (empty($source['image'])) {
                if ($en && ! $this->contains($source['text'], $en)) {
                    $en = null;
                }
                if ($ur && ! $this->contains($source['text'], $ur)) {
                    $ur = null;
                }
            }
            $key = $this->canonical(($en ?? '').($ur ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $item = ['type' => $question['type'], 'text_en' => $en, 'text_ur' => $ur, 'chapter_number' => isset($question['chapter_number']) ? (int) $question['chapter_number'] : null, 'parts' => [], 'source_url' => $source['url'] ?? null, 'source_published_at' => $source['published_at'] ?? null];
            foreach (array_slice(is_array($question['parts'] ?? null) ? $question['parts'] : [], 0, 10) as $part) {
                if (! is_array($part)) {
                    continue;
                }
                $safe = [];
                foreach (['text_en', 'text_ur'] as $field) {
                    $value = $this->text($part[$field] ?? null);
                    $safe[$field] = $value && (! empty($source['image']) || $this->contains($source['text'], $value)) ? $value : null;
                }
                if ($safe['text_en'] || $safe['text_ur']) {
                    $item['parts'][] = $safe;
                }
            }
            if ($item['type'] === 'mcq') {
                $options = $question['mcq_options'] ?? $question['options'] ?? [];
                if (! is_array($options)) {
                    continue;
                }
                $safe = [];
                $complete = true;
                foreach (['a', 'b', 'c', 'd'] as $letter) {
                    $present = false;
                    foreach (['en', 'ur'] as $lang) {
                        $field = "option_{$letter}_{$lang}";
                        $value = $this->text($options[$field] ?? null);
                        $safe[$field] = $value && (! empty($source['image']) || $this->contains($source['text'], $value)) ? $value : null;
                        $present = $present || (bool) $safe[$field];
                    }
                    $complete = $complete && $present;
                }
                if (! $complete) {
                    continue;
                }
                $item['mcq_options'] = $safe;
            }
            $seen[$key] = true;
            $out[] = $item;
            if (count($out) >= max(1, min(30, $limit))) {
                break;
            }
        }

        return $out;
    }

    public function text(mixed $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }
        // Vue renders this as escaped text. strip_tags would corrupt math such as x<2.
        $text = trim($text);
        $text = preg_split('/(?:^|\n|\s+)(?:[\[(]|\*{1,2})?\s*(?:Answer|Ans\.?|Solution|Explanation|Correct\s+(?:option|answer)|جواب|حل|وضاحت)(?:\*{1,2})?\s*[:：=—–]/iu', $text, 2)[0];
        $text = trim($text);

        return $text !== '' && mb_strlen($text) <= 5000 ? $text : null;
    }

    // Preserve operators, decimals and case: x+2 and x-2 are different questions.
    private function canonical(string $text): string
    {
        return preg_replace('/\s+/u', '', $text) ?? '';
    }

    private function contains(string $source, string $text): bool
    {
        $needle = $this->canonical($text);

        return $needle !== '' && str_contains($this->canonical($source), $needle);
    }
}
