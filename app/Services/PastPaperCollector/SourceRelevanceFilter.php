<?php

namespace App\Services\PastPaperCollector;

use App\Models\AIPaperCollection;
use Illuminate\Support\Str;

class SourceRelevanceFilter
{
    /**
     * @var list<string>
     */
    protected array $positiveKeywords = [
        'past paper',
        'pastpaper',
        'bise',
        'board paper',
        'annual',
        'exam',
        'mcq',
        'subjective',
        'objective',
        'paper pdf',
        'question paper',
    ];

    /**
     * @var list<string>
     */
    protected array $negativeKeywords = [
        'job',
        'vacancy',
        'admission form',
        'result gazette',
        'dating',
        'casino',
        'login',
        'signup',
        'shopping',
        'ppsc',
        'spsc',
        'css past',
        'pms past',
    ];

    /**
     * @var list<string>
     */
    protected array $knownBoardTokens = [
        'lahore',
        'gujranwala',
        'faisalabad',
        'multan',
        'rawalpindi',
        'sargodha',
        'sahiwal',
        'bahawalpur',
        'dg khan',
        'dgkhan',
        'd.g. khan',
        'd.g khan',
    ];

    /**
     * @param  array{title:string,url:string,snippet:?string}  $result
     */
    public function isRelevant(array $result, AIPaperCollection $collection): bool
    {
        $haystack = Str::lower(trim(
            ($result['title'] ?? '').' '.($result['snippet'] ?? '').' '.($result['url'] ?? '')
        ));

        foreach ($this->negativeKeywords as $bad) {
            if (str_contains($haystack, $bad)) {
                return false;
            }
        }

        $score = 0;
        foreach ($this->positiveKeywords as $good) {
            if (str_contains($haystack, $good)) {
                $score++;
            }
        }

        $subject = Str::lower((string) ($collection->subject?->name_en ?? ''));
        $board = Str::lower((string) $collection->board);
        $year = (string) $collection->year;

        if ($subject && str_contains($haystack, $subject)) {
            $score += 2;
        }

        $selectedBoardMatched = $board !== '' && $this->matchesBoard($haystack, $board);
        if ($selectedBoardMatched) {
            $score += 2;
        } elseif ($board !== '' && $this->mentionsOtherBoard($haystack, $board)) {
            return false;
        }

        if ($year && str_contains($haystack, $year)) {
            $score += 2;
        }

        $path = Str::lower(parse_url($result['url'] ?? '', PHP_URL_PATH) ?: '');
        if (str_ends_with($path, '.pdf') || str_contains($haystack, 'pdf')) {
            $score += 1;
        }

        return $score >= 2;
    }

    protected function matchesBoard(string $haystack, string $board): bool
    {
        foreach ($this->boardTokens($board) as $token) {
            if ($token !== '' && str_contains($haystack, $token)) {
                return true;
            }
        }

        $collapsedBoard = $this->collapsed($board);
        $collapsedHaystack = $this->collapsed($haystack);

        return $collapsedBoard !== '' && str_contains($collapsedHaystack, $collapsedBoard);
    }

    protected function mentionsOtherBoard(string $haystack, string $selectedBoard): bool
    {
        $selectedTokens = $this->boardTokens($selectedBoard);
        $collapsedSelected = $this->collapsed($selectedBoard);

        foreach ($this->knownBoardTokens as $token) {
            if (! str_contains($haystack, $token) && ! str_contains($this->collapsed($haystack), $this->collapsed($token))) {
                continue;
            }

            $isSelected = in_array($token, $selectedTokens, true)
                || ($collapsedSelected !== '' && str_contains($collapsedSelected, $this->collapsed($token)));

            if (! $isSelected) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function boardTokens(string $board): array
    {
        $board = Str::lower(trim($board));
        $withoutBoard = trim((string) preg_replace('/\s+board$/', '', $board));
        $withoutBise = trim((string) preg_replace('/^bise\s+/', '', $withoutBoard));

        return array_values(array_unique(array_filter([
            $board,
            $withoutBoard,
            $withoutBise,
            $withoutBise !== '' ? 'bise '.$withoutBise : '',
            $withoutBise !== '' ? $withoutBise.' board' : '',
        ])));
    }

    protected function collapsed(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', Str::lower($value));
    }
}
