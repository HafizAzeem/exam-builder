<?php

namespace App\Services;

use Illuminate\Support\Collection;

class BubbleSheetService
{
    /**
     * @param  Collection<int, mixed>  $mcqQuestions
     * @return array<int, array{number: int, options: array<string>}>
     */
    public function generate(Collection $mcqQuestions): array
    {
        $rows = [];
        $number = 1;

        foreach ($mcqQuestions as $question) {
            $rows[] = [
                'number' => $number++,
                'options' => ['A', 'B', 'C', 'D'],
            ];
        }

        return $rows;
    }
}
