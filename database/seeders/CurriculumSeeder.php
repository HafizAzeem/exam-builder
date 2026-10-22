<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Seeds PECTAA-listed textbook subjects for Grades 1–4.
 *
 * Chapter names are intentionally left to the detailed chapter seeders: the
 * PECTAA e-book catalogue confirms book/subject availability, but is not a
 * chapter list. Older generic "Subject Chapter N" rows are disabled below.
 * Catalogue: https://pectaa.edu.pk/curriculum-compliance/
 */
class CurriculumSeeder extends Seeder
{
    /** @var array<int, list<string>> */
    private const SUBJECTS = [
        1 => [
            'Tajveedi Qaida', 'Urdu', 'English', 'Mathematics', 'Waqfiyat e Aama',
            'Islamiyat', 'Akhlaqiat', 'Buddhism', 'Sikhism', 'Sanatan Dharam',
            'Zoroastrian Religion', 'Mashi Taleem',
        ],
        2 => [
            'Nazra Quran', 'Urdu', 'English', 'Mathematics', 'Waqfiyat e Aama',
            'Islamiyat', 'Akhlaqiat', 'Buddhism', 'Sikhism', 'Sanatan Dharam',
            'Zoroastrian Religion', 'Mashi Taleem',
        ],
        3 => [
            'Nazra Quran', 'Urdu', 'English', 'Mathematics', 'Waqfiyat e Aama',
            'Islamiyat', 'Akhlaqiat', 'Buddhism', 'Sikhism', 'Sanatan Dharam',
            'Zoroastrian Religion', 'Mashi Taleem',
        ],
        4 => [
            'Nazra Quran', 'Urdu', 'English', 'Mathematics', 'General Science',
            'Islamiyat', 'Social Studies', 'Akhlaqiat', 'Zoroastrian Religion',
            'Mashi Taleem',
        ],
    ];

    public function run(): void
    {
        foreach (self::SUBJECTS as $gradeNumber => $names) {
            $grade = Grade::query()->where('number', $gradeNumber)->first();
            if (! $grade) {
                continue;
            }

            $grade->update(['is_active' => true]);
            $keepIds = [];

            foreach ($names as $sort => $name) {
                $subject = Subject::query()->firstOrCreate(
                    ['grade_id' => $grade->id, 'name_en' => $name],
                    ['name_ur' => $name, 'sort_order' => $sort + 1, 'is_active' => true]
                );

                $subject->update([
                    'sort_order' => $sort + 1,
                    'is_active' => true,
                ]);
                $keepIds[] = $subject->id;

                // The old seeder generated fictitious chapter names. Preserve
                // their rows/IDs for existing references, but keep them out of
                // current chapter selection until official book contents are
                // extracted and verified.
                $subject->chapters()
                    ->where('title_en', 'like', $name.' Chapter %')
                    ->update(['is_active' => false]);
            }

            // Retire only the broad placeholder subjects from the former
            // seeder. Preserve any subjects added by an administrator or
            // another curriculum package.
            Subject::query()
                ->where('grade_id', $grade->id)
                ->whereIn('name_en', ['English', 'Urdu', 'Islamiyat', 'Pakistan Studies', 'General Science', 'Mathematics'])
                ->whereNotIn('id', $keepIds)
                ->update(['is_active' => false]);
        }
    }
}
