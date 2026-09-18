<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class Class11SubjectsSeeder extends Seeder
{
    public function run(): void
    {
        $grade = Grade::query()->where('number', 11)->first();
        if (! $grade) {
            return;
        }

        $grade->update(['is_active' => true]);

        $subjects = [
            ['en' => 'Physics', 'ur' => 'طبیعیات'],
            ['en' => 'Chemistry', 'ur' => 'کیمسٹری'],
            ['en' => 'Computer Science', 'ur' => 'کمپیوٹر سائنس', 'aliases' => ['Computer']],
            ['en' => 'Biology', 'ur' => 'حیاتیات'],
            ['en' => 'English', 'ur' => 'انگریزی'],
            ['en' => 'Urdu', 'ur' => 'اردو'],
            ['en' => 'Islamiyat', 'ur' => 'اسلامیات', 'aliases' => ['Islamiat']],
            ['en' => 'Tarjuma-tul-Quran', 'ur' => 'ترجمۃ القرآن', 'aliases' => ['Tarjuma Tul Quran']],
        ];

        $keepIds = [];
        $sort = 1;

        foreach ($subjects as $item) {
            $names = array_merge([$item['en']], $item['aliases'] ?? []);

            $subject = Subject::query()
                ->where('grade_id', $grade->id)
                ->whereIn('name_en', $names)
                ->first();

            if (! $subject) {
                $subject = Subject::query()->create([
                    'grade_id' => $grade->id,
                    'name_en' => $item['en'],
                    'name_ur' => $item['ur'],
                    'sort_order' => $sort,
                    'is_active' => true,
                ]);
            } else {
                $subject->update([
                    'name_en' => $item['en'],
                    'name_ur' => $item['ur'],
                    'sort_order' => $sort,
                    'is_active' => true,
                ]);
            }

            $keepIds[] = $subject->id;
            $sort++;
        }

        Subject::query()
            ->where('grade_id', $grade->id)
            ->whereNotIn('id', $keepIds)
            ->update(['is_active' => false]);
    }
}
