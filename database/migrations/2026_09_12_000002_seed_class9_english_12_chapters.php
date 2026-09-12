<?php

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Ensure Class 9 English has 12 PCTB-aligned chapters.
     */
    public function up(): void
    {
        $grade = Grade::query()->where('number', 9)->first();
        if (! $grade) {
            return;
        }

        $subject = Subject::query()
            ->where('grade_id', $grade->id)
            ->where('name_en', 'English')
            ->first();

        if (! $subject) {
            return;
        }

        $titles = [
            1 => 'The Saviour of Mankind',
            2 => 'Patriotism',
            3 => 'Daffodils',
            4 => 'Hazrat Asma (R.A.)',
            5 => 'Women Empowerment through Entrepreneurship',
            6 => 'The Value of Time',
            7 => 'If',
            8 => 'The Impact of Globalisation on Culture and Economy',
            9 => 'Quality Education: A Key to Success',
            10 => 'The Silent Predator and the Majestic Prey — Snow Leopard and Markhor',
            11 => 'The Dear Departed',
            12 => 'English Grammar and Composition',
        ];

        foreach ($titles as $number => $titleEn) {
            $chapter = Chapter::query()->updateOrCreate(
                [
                    'subject_id' => $subject->id,
                    'number' => $number,
                ],
                [
                    'title_en' => $titleEn,
                    'title_ur' => "انگریزی باب {$number}",
                ]
            );

            $topicCount = $number === 1 ? 4 : 3;
            for ($t = 1; $t <= $topicCount; $t++) {
                Topic::query()->updateOrCreate(
                    [
                        'chapter_id' => $chapter->id,
                        'code' => "{$number}.{$t}",
                    ],
                    [
                        'title_en' => "Topic {$number}.{$t}: Key Concepts",
                        'title_ur' => "انگریزی موضوع {$number}.{$t}",
                        'sort_order' => $t,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        // Keep curriculum data; no rollback of chapter titles.
    }
};
