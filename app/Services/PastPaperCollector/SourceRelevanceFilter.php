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
        'lahore board',
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
        if ($board && str_contains($haystack, 'lahore')) {
            $score += 1;
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
}
