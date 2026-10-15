<?php

namespace Database\Seeders;

use App\Models\Grade;
use Database\Seeders\Concerns\SeedsSubjectChapters;
use Illuminate\Database\Seeder;

/**
 * Class 5 subjects + chapter titles (Punjab / PCTB–PECTAA, SNC).
 * PEC papers: English, Urdu, Mathematics, General Science.
 */
class Class5PunjabChaptersSeeder extends Seeder
{
    use SeedsSubjectChapters;

    public function run(): void
    {
        $grade = Grade::query()->where('number', 5)->first();
        if (! $grade) {
            return;
        }

        $grade->update(['is_active' => true]);

        $this->syncSubjects($grade, [
            ['en' => 'English', 'ur' => 'انگریزی'],
            ['en' => 'Urdu', 'ur' => 'اردو'],
            ['en' => 'Islamiyat', 'ur' => 'اسلامیات', 'aliases' => ['Islamiat']],
            ['en' => 'Social Studies', 'ur' => 'معاشرتی علوم', 'aliases' => ['Pakistan Studies', 'Masharti Aloom']],
            ['en' => 'Mathematics', 'ur' => 'ریاضی'],
            ['en' => 'General Science', 'ur' => 'جنرل سائنس'],
        ]);

        $map = [
            'English' => [
                1 => ['en' => 'Patience', 'ur' => 'صبر'],
                2 => ['en' => 'Be Grateful', 'ur' => 'شکر گزار رہو'],
                3 => ['en' => 'Women as Role Models', 'ur' => 'خواتین بطور مثالی کردار'],
                4 => ['en' => 'Unforgettable Moments of My Life', 'ur' => 'میری زندگی کے ناقابل فراموش لمحات'],
                5 => ['en' => 'Amazing Planet', 'ur' => 'حیرت انگیز سیارہ'],
                6 => ['en' => 'A Fit and Healthy Life', 'ur' => 'تندرست اور صحت مند زندگی'],
                7 => ['en' => 'What Goes Around Comes Around', 'ur' => 'جیسا کروگے ویسا بھروگے'],
                8 => ['en' => "Do What's Right", 'ur' => 'صحیح کام کرو'],
                9 => ['en' => 'Patriotism', 'ur' => 'محب وطنی'],
                10 => ['en' => 'Eid-ul-Azha', 'ur' => 'عید الاضحیٰ'],
                11 => ['en' => "Let's Be Helpful", 'ur' => 'مددگار بنو'],
                12 => ['en' => 'The National Animal', 'ur' => 'قومی جانور'],
                13 => ['en' => 'When Something Went Wrong', 'ur' => 'جب کچھ غلط ہو گیا'],
                14 => ['en' => 'Together We Live', 'ur' => 'ہم مل کر رہتے ہیں'],
            ],
            'Urdu' => [
                1 => ['en' => 'Hamd', 'ur' => 'حمد'],
                2 => ['en' => 'Naat', 'ur' => 'نعت'],
                3 => ['en' => 'Nazm – Watan', 'ur' => 'نظم – وطن'],
                4 => ['en' => 'Quaid-e-Azam Muhammad Ali Jinnah', 'ur' => 'قائد اعظم محمد علی جناح'],
                5 => ['en' => 'Allama Iqbal', 'ur' => 'علامہ اقبال'],
                6 => ['en' => 'Nasr / Prose Lesson 1', 'ur' => 'نثر سبق ۱'],
                7 => ['en' => 'Nasr / Prose Lesson 2', 'ur' => 'نثر سبق ۲'],
                8 => ['en' => 'Akhlaqi Kahani', 'ur' => 'اخلاقی کہانی'],
                9 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Islamiyat' => [
                1 => ['en' => 'Articles of Faith (Imaniyat)', 'ur' => 'ایمانیات'],
                2 => ['en' => 'Worship (Ibadat)', 'ur' => 'عبادات'],
                3 => ['en' => 'Seerah of the Holy Prophet (PBUH)', 'ur' => 'سیرت النبی ﷺ'],
                4 => ['en' => 'Islamic Ethics and Character', 'ur' => 'اسلامی اخلاق و کردار'],
                5 => ['en' => 'Selected Ayat and Ahadith', 'ur' => 'منتخب آیات و احادیث'],
                6 => ['en' => 'Rights of Parents, Neighbours and Teachers', 'ur' => 'والدین، پڑوسیوں اور اساتذہ کے حقوق'],
            ],
            'Social Studies' => [
                1 => ['en' => 'Globe, Maps and Directions', 'ur' => 'کرہ ارض، نقشے اور سمتیں'],
                2 => ['en' => 'Climate and Weather', 'ur' => 'موسم اور آب و ہوا'],
                3 => ['en' => 'Natural Resources of Pakistan', 'ur' => 'پاکستان کے قدرتی وسائل'],
                4 => ['en' => 'Culture and Heritage of Pakistan', 'ur' => 'پاکستانی ثقافت و ورثہ'],
                5 => ['en' => 'Famous Personalities', 'ur' => 'مشاہیر'],
                6 => ['en' => 'Government and Citizenship', 'ur' => 'حکومت اور شہریت'],
                7 => ['en' => 'Rights and Responsibilities', 'ur' => 'حقوق و فرائض'],
                8 => ['en' => 'Economy and Occupations', 'ur' => 'معیشت اور پیشے'],
            ],
            'Mathematics' => [
                1 => ['en' => 'Whole Numbers and Operations', 'ur' => 'صحیح اعداد اور عملیات'],
                2 => ['en' => 'HCF and LCM', 'ur' => 'م.آ.ق اور م.آ.ض'],
                3 => ['en' => 'Fractions', 'ur' => 'کسریں'],
                4 => ['en' => 'Decimals and Percentage', 'ur' => 'اعشاریے اور فی صد'],
                5 => ['en' => 'Distance and Time', 'ur' => 'فاصلہ اور وقت'],
                6 => ['en' => 'Unitary Method', 'ur' => 'وحدانی طریقہ'],
                7 => ['en' => 'Geometry', 'ur' => 'ہندسہ'],
                8 => ['en' => 'Perimeter and Area', 'ur' => 'محیط اور رقبہ'],
                9 => ['en' => 'Data Handling', 'ur' => 'معلومات کا انتظام'],
            ],
            'General Science' => [
                1 => ['en' => 'Classification of Living Organisms', 'ur' => 'جانداروں کی درجہ بندی'],
                2 => ['en' => 'Microorganisms', 'ur' => 'خرد حیاتیات'],
                3 => ['en' => 'Flowers and Seeds', 'ur' => 'پھول اور بیج'],
                4 => ['en' => 'Environmental Pollution', 'ur' => 'ماحولیاتی آلودگی'],
                5 => ['en' => 'Physical and Chemical Changes of Matter', 'ur' => 'مادے کی طبیعی اور کیمیائی تبدیلیاں'],
                6 => ['en' => 'Light and Sound', 'ur' => 'روشنی اور آواز'],
                7 => ['en' => 'Electricity and Magnetism', 'ur' => 'بجلی اور مقناطیسیت'],
                8 => ['en' => 'Structure of the Earth', 'ur' => 'زمین کی ساخت'],
                9 => ['en' => 'Space and Satellites', 'ur' => 'خلا اور سیارچے'],
                10 => ['en' => 'Technology in Everyday Life', 'ur' => 'روزمرہ زندگی میں ٹیکنالوجی'],
            ],
        ];

        foreach ($map as $subjectName => $chapters) {
            $subject = $this->subjectByName($grade, $subjectName);
            if ($subject) {
                $this->syncChapters($subject, $chapters);
            }
        }
    }
}
