<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class Class10SubjectsSeeder extends Seeder
{
    public function run(): void
    {
        $grade = Grade::query()->where('number', 10)->first();
        if (! $grade) {
            return;
        }

        $grade->update(['is_active' => true]);

        $subjects = [
            ['en' => 'Mathematics', 'ur' => 'ریاضی'],
            ['en' => 'Physics', 'ur' => 'طبیعیات'],
            ['en' => 'Chemistry', 'ur' => 'کیمسٹری'],
            ['en' => 'Computer Science', 'ur' => 'کمپیوٹر سائنس', 'aliases' => ['Computer']],
            ['en' => 'English', 'ur' => 'انگریزی'],
            ['en' => 'Urdu', 'ur' => 'اردو'],
            ['en' => 'Pakistan Studies', 'ur' => 'مطالعہ پاکستان', 'aliases' => ['Pak Studies']],
            ['en' => 'Biology', 'ur' => 'حیاتیات'],
            ['en' => 'Islamiyat', 'ur' => 'اسلامیات', 'aliases' => ['Islamiat']],
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
