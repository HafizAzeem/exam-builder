<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class Class9EnglishChaptersSeeder extends Seeder
{
    public function run(): void
    {
        $grade = Grade::query()->where('number', 9)->first();
        if (! $grade) {
            return;
        }

        $grade->update(['is_active' => true]);

        $subject = Subject::query()->firstOrCreate(
            ['grade_id' => $grade->id, 'name_en' => 'English'],
            [
                'name_ur' => 'انگریزی',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $subject->update(['is_active' => true]);

        $titles = [
            1 => 'The Saviour of Mankind (New)',
            2 => 'Patriotism (New)',
            3 => 'Daffodils (New)',
            4 => 'Hazrat Asma (New)',
            5 => 'Women Empowerment through Entrepreneurship (New)',
            6 => 'The Value of Time (New)',
            7 => 'If (New)',
            8 => 'The Impact of Globalisation on Culture and Economy (New)',
            9 => 'Quality Education: A Key to Success (New)',
            10 => 'The Silent Predator and the Majestic Prey – Snow Leopard and Markhor (New)',
            11 => 'The Dear Departed (New)',
        ];

        foreach ($titles as $number => $titleEn) {
            Chapter::query()->updateOrCreate(
                [
                    'subject_id' => $subject->id,
                    'number' => $number,
                ],
                [
                    'title_en' => $titleEn,
                    'title_ur' => "انگریزی باب {$number}",
                    'is_active' => true,
                ]
            );
        }

        Chapter::query()
            ->where('subject_id', $subject->id)
            ->where('number', '>', 11)
            ->update(['is_active' => false]);
    }
}
