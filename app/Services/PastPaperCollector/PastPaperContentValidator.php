<?php

namespace App\Services\PastPaperCollector;

use App\Models\AIPaperCollection;
use Illuminate\Support\Str;

/**
 * Hard check that extracted document text matches the requested paper.
 */
class PastPaperContentValidator
{
    public function matches(string $text, AIPaperCollection $collection): bool
    {
        $collection->loadMissing(['grade', 'subject']);
        $haystack = Str::lower($text);

        if (mb_strlen(trim($text)) < 40) {
            return false;
        }

        $year = (string) $collection->year;
        if ($year !== '' && ! str_contains($haystack, $year)) {
            // Many papers bury the year in a stamp/header OCR miss — require subject+board instead.
            // Fail only when an obviously different year appears.
            if (preg_match_all('/\b(19|20)\d{2}\b/', $haystack, $years)) {
                $foundYears = array_unique($years[0]);
                $foundYears = array_values(array_filter($foundYears, fn ($y) => $y !== $year));
                if ($foundYears !== [] && ! in_array($year, $years[0], true)) {
                    return false;
                }
            }
        }

        $subject = Str::lower((string) ($collection->subject?->name_en ?? ''));
        if ($subject !== '' && ! $this->containsSubject($haystack, $subject)) {
            return false;
        }

        $board = Str::lower((string) $collection->board);
        if ($board !== '' && ! $this->containsBoard($haystack, $board)) {
            // Search snippets already filtered URLs; body may omit board name on inner pages.
            // Only reject when a clearly different Punjab board is named.
            if ($this->mentionsConflictingBoard($haystack, $board)) {
                return false;
            }
        }

        $gradeNumber = (string) ($collection->grade?->number ?? '');
        if ($gradeNumber !== '' && $this->mentionsConflictingClass($haystack, $gradeNumber)) {
            return false;
        }

        return true;
    }

    public function rejectionReason(string $text, AIPaperCollection $collection): string
    {
        return 'Extracted document does not match the requested board/class/subject/year.';
    }

    protected function containsSubject(string $haystack, string $subject): bool
    {
        if (str_contains($haystack, $subject)) {
            return true;
        }

        $aliases = [
            'computer science' => ['computer', 'ics', 'comp science'],
            'pakistan studies' => ['pak studies', 'pak. studies', 'mutalia pakistan'],
            'islamiyat' => ['islamiat', 'islamic studies'],
            'mathematics' => ['maths', 'math'],
            'general mathematics' => ['general maths', 'general math'],
            'health and physical education' => ['physical education', 'health & physical'],
        ];

        foreach ($aliases[$subject] ?? [] as $alias) {
            if (str_contains($haystack, $alias)) {
                return true;
            }
        }

        return false;
    }

    protected function containsBoard(string $haystack, string $board): bool
    {
        if (str_contains($haystack, $board)) {
            return true;
        }

        $token = trim(Str::before($board, ' board'));
        if ($token !== '' && str_contains($haystack, $token)) {
            return true;
        }

        return str_contains($haystack, 'bise') || str_contains($haystack, 'punjab');
    }

    protected function mentionsConflictingBoard(string $haystack, string $requestedBoard): bool
    {
        $boards = [
            'lahore', 'gujranwala', 'rawalpindi', 'faisalabad', 'multan',
            'sargodha', 'sahiwal', 'bahawalpur', 'dg khan', 'dera ghazi',
            'karachi', 'hyderabad', 'sukkur', 'peshawar', 'malakand', 'federal',
        ];

        $requested = Str::lower(trim(Str::before($requestedBoard, ' board')));
        foreach ($boards as $board) {
            if ($board === $requested || str_contains($requested, $board)) {
                continue;
            }
            if (str_contains($haystack, $board.' board') || str_contains($haystack, 'bise '.$board)) {
                return true;
            }
        }

        return false;
    }

    protected function mentionsConflictingClass(string $haystack, string $gradeNumber): bool
    {
        $others = array_values(array_filter(
            ['9', '10', '11', '12'],
            fn ($n) => $n !== $gradeNumber
        ));

        $mentionsRequested = (bool) preg_match(
            '/\b(class\s*)?'.$gradeNumber.'(th|st|nd|rd)?\b|\b'.$gradeNumber.'th\s*class\b/i',
            $haystack
        );

        foreach ($others as $other) {
            $otherMentioned = (bool) preg_match(
                '/\b(class\s*)?'.$other.'(th|st|nd|rd)?\b|\b'.$other.'th\s*class\b/i',
                $haystack
            );
            if ($otherMentioned && ! $mentionsRequested) {
                return true;
            }
        }

        return false;
    }
}
