<?php

namespace App\Services\PastPaperCollector;

use App\Models\AISetting;

class PaperAwareChunkerService
{
    /**
     * Prefer splitting on question numbers / sections for past papers.
     *
     * @return list<array{index:int, title:?string, text:string}>
     */
    public function chunk(string $text, ?int $chunkSize = null): array
    {
        $text = trim(preg_replace("/\r\n?/", "\n", $text) ?? $text);
        $chunkSize = $chunkSize ?: AISetting::current()->chunk_size;

        $sections = $this->splitByQuestionMarkers($text);

        if (count($sections) <= 1 && mb_strlen($text) > $chunkSize) {
            return $this->splitBySize($text, $chunkSize);
        }

        $chunks = [];
        $buffer = '';
        $bufferTitle = null;
        $index = 0;

        foreach ($sections as $section) {
            $sectionText = trim($section['text']);
            if ($sectionText === '') {
                continue;
            }

            $candidate = $buffer === '' ? $sectionText : $buffer."\n\n".$sectionText;
            if (mb_strlen($candidate) <= $chunkSize) {
                $buffer = $candidate;
                $bufferTitle ??= $section['title'];

                continue;
            }

            if ($buffer !== '') {
                $chunks[] = [
                    'index' => $index++,
                    'title' => $bufferTitle,
                    'text' => $buffer,
                ];
            }

            if (mb_strlen($sectionText) <= $chunkSize) {
                $buffer = $sectionText;
                $bufferTitle = $section['title'];
            } else {
                foreach ($this->splitBySize($sectionText, $chunkSize) as $piece) {
                    $chunks[] = [
                        'index' => $index++,
                        'title' => $section['title'],
                        'text' => $piece['text'],
                    ];
                }
                $buffer = '';
                $bufferTitle = null;
            }
        }

        if ($buffer !== '') {
            $chunks[] = [
                'index' => $index,
                'title' => $bufferTitle,
                'text' => $buffer,
            ];
        }

        return $chunks ?: [[
            'index' => 0,
            'title' => null,
            'text' => $text,
        ]];
    }

    /**
     * @return list<array{title:?string, text:string}>
     */
    protected function splitByQuestionMarkers(string $text): array
    {
        $pattern = '/(?=(?:^|\n)\s*(?:Q(?:uestion)?\.?\s*\d+|سوال\s*[\d۰-۹]+|\d+\s*[\).]|SECTION\s+[A-Z]|حصہ\s+[ا-ےA-Z])\b)/iu';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);

        if (! $parts || count($parts) <= 1) {
            return [['title' => null, 'text' => $text]];
        }

        $sections = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $firstLine = strtok($part, "\n") ?: null;
            $sections[] = [
                'title' => $firstLine ? trim($firstLine) : null,
                'text' => $part,
            ];
        }

        return $sections;
    }

    /**
     * @return list<array{index:int, title:?string, text:string}>
     */
    protected function splitBySize(string $text, int $chunkSize): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        $offset = 0;
        $index = 0;

        while ($offset < $length) {
            $slice = mb_substr($text, $offset, $chunkSize);
            $chunks[] = [
                'index' => $index++,
                'title' => null,
                'text' => $slice,
            ];
            $offset += $chunkSize;
        }

        return $chunks;
    }
}
