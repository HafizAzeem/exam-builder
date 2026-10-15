<?php

namespace Database\Seeders;

use App\Models\Grade;
use Database\Seeders\Concerns\SeedsSubjectChapters;
use Illuminate\Database\Seeder;

/**
 * Class 7 subjects + chapter titles (Punjab / PCTB–PECTAA, SNC 2022–24).
 */
class Class7PunjabChaptersSeeder extends Seeder
{
    use SeedsSubjectChapters;

    public function run(): void
    {
        $grade = Grade::query()->where('number', 7)->first();
        if (! $grade) {
            return;
        }

        $grade->update(['is_active' => true]);

        $this->syncSubjects($grade, [
            ['en' => 'English', 'ur' => 'انگریزی'],
            ['en' => 'Urdu', 'ur' => 'اردو'],
            ['en' => 'Islamiyat', 'ur' => 'اسلامیات', 'aliases' => ['Islamiat']],
            ['en' => 'History', 'ur' => 'تاریخ'],
            ['en' => 'Geography', 'ur' => 'جغرافیہ'],
            ['en' => 'Mathematics', 'ur' => 'ریاضی'],
            ['en' => 'General Science', 'ur' => 'جنرل سائنس'],
            ['en' => 'Computer Science', 'ur' => 'کمپیوٹر سائنس', 'aliases' => ['Computer']],
            ['en' => 'Tarjuma-tul-Quran', 'ur' => 'ترجمۃ القرآن', 'aliases' => ['Tarjuma Tul Quran', 'Mutalea Quran']],
        ]);

        $map = [
            'English' => [
                1 => ['en' => 'The Last Sermon of Rasoolullah ﷺ', 'ur' => 'خطبہ حجۃ الوداع'],
                2 => ['en' => 'Martyrs of Pakistan', 'ur' => 'شہداء پاکستان'],
                3 => ['en' => 'Dignity of Labour', 'ur' => 'محنت کی عزت'],
                4 => ['en' => 'Pakistani Culture', 'ur' => 'پاکستانی ثقافت'],
                5 => ['en' => 'The Wise Old Woman', 'ur' => 'دانا بوڑھی عورت'],
                6 => ['en' => 'Environment', 'ur' => 'ماحول'],
                7 => ['en' => 'Our Natural Resources', 'ur' => 'ہمارے قدرتی وسائل'],
                8 => ['en' => 'Poetry Unit', 'ur' => 'نظم'],
                9 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Urdu' => [
                1 => ['en' => 'Hamd', 'ur' => 'حمد'],
                2 => ['en' => 'Naat', 'ur' => 'نعت'],
                3 => ['en' => 'Ghazal', 'ur' => 'غزل'],
                4 => ['en' => 'Nazm', 'ur' => 'نظم'],
                5 => ['en' => 'Quaid-e-Azam Muhammad Ali Jinnah', 'ur' => 'قائد اعظم محمد علی جناح'],
                6 => ['en' => 'Allama Iqbal', 'ur' => 'علامہ اقبال'],
                7 => ['en' => 'Nasr / Prose Lesson 1', 'ur' => 'نثر سبق ۱'],
                8 => ['en' => 'Nasr / Prose Lesson 2', 'ur' => 'نثر سبق ۲'],
                9 => ['en' => 'Drama / Play', 'ur' => 'ڈرامہ'],
                10 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Islamiyat' => [
                1 => ['en' => 'Articles of Faith (Imaniyat)', 'ur' => 'ایمانیات'],
                2 => ['en' => 'Worship (Ibadat)', 'ur' => 'عبادات'],
                3 => ['en' => 'Seerah of the Holy Prophet (PBUH)', 'ur' => 'سیرت النبی ﷺ'],
                4 => ['en' => 'Khulafa-e-Rashideen', 'ur' => 'خلفائے راشدین'],
                5 => ['en' => 'Islamic Ethics and Character', 'ur' => 'اسلامی اخلاق و کردار'],
                6 => ['en' => 'Selected Ayat', 'ur' => 'منتخب آیات'],
                7 => ['en' => 'Selected Ahadith', 'ur' => 'منتخب احادیث'],
            ],
            'History' => [
                1 => ['en' => 'The Delhi Sultanate', 'ur' => 'سلطنت دہلی'],
                2 => ['en' => 'The Mughal Empire', 'ur' => 'مغلیہ سلطنت'],
                3 => ['en' => 'Regional Kingdoms', 'ur' => 'علاقائی سلطنتیں'],
                4 => ['en' => 'Society, Culture and Economy', 'ur' => 'معاشرت، ثقافت اور معیشت'],
                5 => ['en' => 'Arrival of the Europeans', 'ur' => 'یورپیوں کی آمد'],
            ],
            'Geography' => [
                1 => ['en' => 'Atmosphere', 'ur' => 'ماحول کرہ'],
                2 => ['en' => 'Temperature', 'ur' => 'درجہ حرارت'],
                3 => ['en' => 'Pressure and Winds', 'ur' => 'دباؤ اور ہوائیں'],
                4 => ['en' => 'Humidity and Precipitation', 'ur' => 'نمی اور بارش'],
                5 => ['en' => 'Natural Disasters', 'ur' => 'قدرتی آفات'],
                6 => ['en' => 'Water Resources', 'ur' => 'آبی وسائل'],
            ],
            'Mathematics' => [
                1 => ['en' => 'Rational Numbers and Decimals', 'ur' => 'نسبتی اعداد اور اعشاریے'],
                2 => ['en' => 'Simplification', 'ur' => 'سادہ کرنا'],
                3 => ['en' => 'Sets', 'ur' => 'مجموعے'],
                4 => ['en' => 'Rate, Ratio and Proportion', 'ur' => 'شرح، تناسب اور نسبت'],
                5 => ['en' => 'Financial Arithmetic', 'ur' => 'مالی حساب'],
                6 => ['en' => 'Squares and Square Roots', 'ur' => 'مربع اور مربع جذر'],
                7 => ['en' => 'Number Sequence and Patterns', 'ur' => 'عددی تسلسل اور نمونہ'],
                8 => ['en' => 'Algebraic Expressions', 'ur' => 'الجبرائی اظہار'],
                9 => ['en' => 'Linear Equations', 'ur' => 'خطی مساوات'],
                10 => ['en' => 'Distance, Speed and Time', 'ur' => 'فاصلہ، رفتار اور وقت'],
                11 => ['en' => 'Perimeter and Area', 'ur' => 'محیط اور رقبہ'],
                12 => ['en' => 'Practical Geometry', 'ur' => 'عملی ہندسہ'],
                13 => ['en' => 'Angle Properties of Polygons and Transformation', 'ur' => 'کثیر الاضلاع کے زاویے اور تبدیلی'],
                14 => ['en' => 'Statistics and Probability', 'ur' => 'شماریات اور احتمال'],
            ],
            'General Science' => [
                1 => ['en' => 'Plant Systems', 'ur' => 'پودوں کے نظام'],
                2 => ['en' => 'Human Respiratory and Circulatory Systems', 'ur' => 'انسانی نظام تنفس اور دوران خون'],
                3 => ['en' => 'Immunity and Diseases', 'ur' => 'مدافعتی قوت اور بیماریاں'],
                4 => ['en' => 'Structure of an Atom', 'ur' => 'ایٹم کی ساخت'],
                5 => ['en' => 'Physical and Chemical Changes', 'ur' => 'طبیعی اور کیمیائی تبدیلیاں'],
                6 => ['en' => 'Chemical Bonds', 'ur' => 'کیمیائی بندش'],
                7 => ['en' => 'Solutions', 'ur' => 'محلول'],
                8 => ['en' => 'Force and Motion', 'ur' => 'قوت اور حرکت'],
                9 => ['en' => 'Waves and Energy', 'ur' => 'امواج اور توانائی'],
                10 => ['en' => 'Heat and Temperature', 'ur' => 'حرارت اور درجہ حرارت'],
                11 => ['en' => 'Technology in Everyday Life', 'ur' => 'روزمرہ زندگی میں ٹیکنالوجی'],
                12 => ['en' => 'Earth and Space', 'ur' => 'زمین اور خلا'],
            ],
            'Computer Science' => [
                1 => ['en' => 'Computer Hardware and Software', 'ur' => 'کمپیوٹر ہارڈ ویئر اور سافٹ ویئر'],
                2 => ['en' => 'Computational Thinking', 'ur' => 'کمپیوٹیشنل سوچ'],
                3 => ['en' => 'Algorithms: Conditions and Loops', 'ur' => 'الگورتھم: شرائط اور حلقے'],
                4 => ['en' => 'Programming with Scratch', 'ur' => 'اسکریچ کے ساتھ پروگرامنگ'],
                5 => ['en' => 'Computer Networks and Internet', 'ur' => 'کمپیوٹر نیٹ ورکس اور انٹرنیٹ'],
                6 => ['en' => 'Cyber Safety and Digital Citizenship', 'ur' => 'سائبر حفاظت اور ڈیجیٹل شہریت'],
            ],
            'Tarjuma-tul-Quran' => [
                1 => ['en' => 'Selected Surahs – Part A', 'ur' => 'منتخب سورتیں – حصہ الف'],
                2 => ['en' => 'Selected Surahs – Part B', 'ur' => 'منتخب سورتیں – حصہ ب'],
                3 => ['en' => 'Selected Ayat – Ethics and Character', 'ur' => 'منتخب آیات – اخلاق'],
                4 => ['en' => 'Selected Ayat – Social Teachings', 'ur' => 'منتخب آیات – معاشرتی تعلیمات'],
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
