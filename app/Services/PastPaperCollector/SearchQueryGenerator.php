<?php

namespace App\Services\PastPaperCollector;

use App\Models\AIPaperCollection;
use App\Models\Grade;
use App\Support\CurriculumLookup;

class SearchQueryGenerator
{
    /**
     * @return list<string>
     */
    public function generate(AIPaperCollection $collection): array
    {
        $collection->loadMissing(['grade', 'subject']);

        $board = trim((string) $collection->board) ?: (CurriculumLookup::defaultBoardName() ?: 'Lahore Board');
        $grade = $this->gradeLabel($collection->grade);
        $subject = $collection->subject?->name_en ?? 'Subject';
        $year = (string) $collection->year;
        $session = $collection->session ? ucfirst($collection->session) : null;
        $paperType = $collection->paper_type;
        $language = $collection->language;
        $country = $collection->country ?: 'Pakistan';

        if (filled($collection->keywords_override)) {
            return array_values(array_unique(array_filter([
                trim((string) $collection->keywords_override),
                trim((string) $collection->keywords_override).' PDF',
                trim((string) $collection->keywords_override).' past paper',
            ])));
        }

        $bise = $this->bisePhrase($board);

        $queries = [
            "{$board} {$grade} {$subject} {$year}".($session ? " {$session}" : '').' Paper PDF',
            "{$board} {$subject} Class {$grade} Annual {$year}",
            "{$bise} {$grade} {$subject} Past Paper {$year}",
            "{$subject} {$grade} {$board} {$year} Objective Paper",
            "{$subject} {$grade} {$board} {$year} Subjective Paper",
            "{$board} {$grade} {$subject} {$year} {$country}",
            "{$bise} {$subject} {$grade} {$year} past paper PDF",
            "{$subject} {$grade} class {$board} {$year} past paper",
        ];

        if ($paperType === 'objective') {
            $queries[] = "{$board} {$grade} {$subject} {$year} MCQ Paper";
        } elseif ($paperType === 'subjective') {
            $queries[] = "{$board} {$grade} {$subject} {$year} Subjective Questions";
        }

        if ($session) {
            $queries[] = "{$bise} {$subject} {$grade} {$year} {$session} session";
        }

        if ($language && strtolower($language) !== 'english') {
            $queries[] = "{$board} {$grade} {$subject} {$year} {$language}";
        }

        return array_values(array_unique(array_filter(array_map('trim', $queries))));
    }

    protected function gradeLabel(?Grade $grade): string
    {
        if (! $grade) {
            return '9';
        }

        $number = (string) $grade->number;

        return match ($number) {
            '5' => '5th',
            '6' => '6th',
            '7' => '7th',
            '8' => '8th',
            '9' => '9th',
            '10' => '10th',
            '11' => '11th',
            '12' => '12th',
            default => $number,
        };
    }

    protected function bisePhrase(string $board): string
    {
        $name = trim($board);
        if ($name === '') {
            return 'BISE';
        }

        if (preg_match('/^bise\b/i', $name)) {
            return $name;
        }

        $withoutBoard = trim((string) preg_replace('/\s+board$/i', '', $name));

        return 'BISE '.$withoutBoard;
    }
}
