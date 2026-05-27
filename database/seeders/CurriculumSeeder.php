<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class CurriculumSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * Minimal but broad curriculum for dropdown testing.
         * Creates Subjects + Chapters for all 12 grades.
         */
        $subjectsByGrade = [
            'default' => [
                ['en' => 'English', 'ur' => 'انگریزی'],
                ['en' => 'Urdu', 'ur' => 'اردو'],
                ['en' => 'Islamiyat', 'ur' => 'اسلامیات'],
                ['en' => 'Pakistan Studies', 'ur' => 'مطالعہ پاکستان'],
                ['en' => 'General Science', 'ur' => 'جنرل سائنس'],
                ['en' => 'Mathematics', 'ur' => 'ریاضی'],
            ],
            9 => [
                ['en' => 'English', 'ur' => 'انگریزی'],
                ['en' => 'Urdu', 'ur' => 'اردو'],
                ['en' => 'Islamiyat', 'ur' => 'اسلامیات'],
                ['en' => 'Pakistan Studies', 'ur' => 'مطالعہ پاکستان'],
                ['en' => 'Mathematics', 'ur' => 'ریاضی'],
                ['en' => 'Physics', 'ur' => 'طبیعیات'],
                ['en' => 'Chemistry', 'ur' => 'کیمسٹری'],
                ['en' => 'Biology', 'ur' => 'حیاتیات'],
                ['en' => 'Computer Science', 'ur' => 'کمپیوٹر سائنس'],
                ['en' => 'Tarjuma-tul-Quran', 'ur' => 'ترجمۃ القرآن'],
            ],
            10 => [
                ['en' => 'English', 'ur' => 'انگریزی'],
                ['en' => 'Urdu', 'ur' => 'اردو'],
                ['en' => 'Islamiyat', 'ur' => 'اسلامیات'],
                ['en' => 'Pakistan Studies', 'ur' => 'مطالعہ پاکستان'],
                ['en' => 'Mathematics', 'ur' => 'ریاضی'],
                ['en' => 'Physics', 'ur' => 'طبیعیات'],
                ['en' => 'Chemistry', 'ur' => 'کیمسٹری'],
                ['en' => 'Biology', 'ur' => 'حیاتیات'],
                ['en' => 'Computer Science', 'ur' => 'کمپیوٹر سائنس'],
                ['en' => 'Tarjuma-tul-Quran', 'ur' => 'ترجمۃ القرآن'],
            ],
        ];

        /** @var \Illuminate\Support\Collection<int, Grade> $grades */
        $grades = Grade::query()->orderBy('number')->get();

        foreach ($grades as $grade) {
            $gradeSubjects = $subjectsByGrade[$grade->number] ?? $subjectsByGrade['default'];

            $sort = 1;
            foreach ($gradeSubjects as $s) {
                $subject = Subject::query()->firstOrCreate(
                    ['grade_id' => $grade->id, 'name_en' => $s['en']],
                    ['name_ur' => $s['ur'], 'sort_order' => $sort]
                );

                // Keep stable order if seeded previously.
                if (! $subject->sort_order) {
                    $subject->update(['sort_order' => $sort]);
                }

                $this->seedChaptersForSubject($grade->number, $subject);
                $sort++;
            }
        }
    }

    protected function seedChaptersForSubject(int $gradeNumber, Subject $subject): void
    {
        // A small, consistent chapter set per subject (enough for dropdowns and testing).
        $chapterCount = match ($subject->name_en) {
            'Mathematics' => 8,
            'Physics', 'Chemistry', 'Biology', 'Computer Science' => 6,
            default => ($gradeNumber <= 5 ? 10 : 7),
        };

        for ($i = 1; $i <= $chapterCount; $i++) {
            Chapter::query()->firstOrCreate(
                [
                    'subject_id' => $subject->id,
                    'number' => $i,
                ],
                [
                    'title_en' => "{$subject->name_en} Chapter {$i}",
                    'title_ur' => "{$subject->name_ur} باب {$i}",
                ]
            );
        }
    }
}

