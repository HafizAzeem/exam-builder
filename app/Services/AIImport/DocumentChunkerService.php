<?php

namespace App\Services\AIImport;

use App\Models\AISetting;

class DocumentChunkerService
{
    /**
     * @return list<array{index:int, title:?string, text:string}>
     */
    public function chunk(string $text, ?int $chunkSize = null): array
    {
        $text = trim(preg_replace("/\r\n?/", "\n", $text) ?? $text);
        $chunkSize = $chunkSize ?: AISetting::current()->chunk_size;

        $sections = $this->splitByChapter($text);

        if (count($sections) <= 1 && mb_strlen($text) > $chunkSize) {
            return $this->splitBySize($text, $chunkSize);
        }

        $chunks = [];
        $index = 0;

        foreach ($sections as $section) {
            $sectionText = trim($section['text']);
            if ($sectionText === '') {
                continue;
            }

            if (mb_strlen($sectionText) <= $chunkSize) {
                $chunks[] = [
                    'index' => $index++,
                    'title' => $section['title'],
                    'text' => $sectionText,
                ];

                continue;
            }

            foreach ($this->splitBySize($sectionText, $chunkSize) as $piece) {
                $chunks[] = [
                    'index' => $index++,
                    'title' => $section['title'],
                    'text' => $piece['text'],
                ];
            }
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
    protected function splitByChapter(string $text): array
    {
        $pattern = '/(?=(?:^|\n)\s*(?:Chapter|CHAPTER|باب)\s*[\d۰-۹]+[^\n]*)/u';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

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
