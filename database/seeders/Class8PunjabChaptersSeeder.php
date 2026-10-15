<?php

namespace Database\Seeders;

use App\Models\Grade;
use Database\Seeders\Concerns\SeedsSubjectChapters;
use Illuminate\Database\Seeder;

/**
 * Class 8 subjects + chapter titles (Punjab / PCTB–PECTAA, SNC 2022–24).
 * PEC papers: English, Urdu, Mathematics, General Science.
 */
class Class8PunjabChaptersSeeder extends Seeder
{
    use SeedsSubjectChapters;

    public function run(): void
    {
        $grade = Grade::query()->where('number', 8)->first();
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
                1 => ['en' => 'The Medina Charter', 'ur' => 'میثاق مدینہ'],
                2 => ['en' => 'The Caliph and the Gardener', 'ur' => 'خلیفہ اور مالی'],
                3 => ['en' => 'A Visit to Swat Valley', 'ur' => 'وادی سوات کا سفر'],
                4 => ['en' => 'Hope is the Thing with Feathers', 'ur' => 'امید پروں والی چیز ہے'],
                5 => ['en' => 'The Hospital Window', 'ur' => 'ہسپتال کی کھڑکی'],
                6 => ['en' => 'Major Shabir Sharif (Shaheed)', 'ur' => 'میجر شبیر شریف (شہید)'],
                7 => ['en' => 'If (Rudyard Kipling)', 'ur' => 'اگر'],
                8 => ['en' => 'Career Planning', 'ur' => 'کیریئر کی منصوبہ بندی'],
                9 => ['en' => 'Healthy Habits for Mind, Body and Soul', 'ur' => 'ذہن، جسم اور روح کی صحت مند عادتیں'],
                10 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
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
                8 => ['en' => 'Rights and Social Responsibilities', 'ur' => 'حقوق و فرائض'],
            ],
            'History' => [
                1 => ['en' => 'Decline of the Mughal Empire', 'ur' => 'مغلیہ سلطنت کا زوال'],
                2 => ['en' => 'The British in India', 'ur' => 'برصغیر میں انگریز'],
                3 => ['en' => 'War of Independence 1857', 'ur' => 'جنگ آزادی ۱۸۵۷'],
                4 => ['en' => 'Educational and Religious Reform Movements', 'ur' => 'تعلیمی و دینی اصلاحی تحریکیں'],
                5 => ['en' => 'Struggle for Pakistan', 'ur' => 'تحریک پاکستان'],
                6 => ['en' => 'Independence and Creation of Pakistan', 'ur' => 'آزادی اور قیام پاکستان'],
                7 => ['en' => 'Early Years of Pakistan', 'ur' => 'پاکستان کے ابتدائی سال'],
            ],
            'Geography' => [
                1 => ['en' => 'Landforms of Pakistan', 'ur' => 'پاکستان کی ارضی اشکال'],
                2 => ['en' => 'Climate of Pakistan', 'ur' => 'پاکستان کی آب و ہوا'],
                3 => ['en' => 'Natural Resources of Pakistan', 'ur' => 'پاکستان کے قدرتی وسائل'],
                4 => ['en' => 'Agriculture', 'ur' => 'زراعت'],
                5 => ['en' => 'Industry and Trade', 'ur' => 'صنعت اور تجارت'],
                6 => ['en' => 'Population of Pakistan', 'ur' => 'پاکستان کی آبادی'],
                7 => ['en' => 'Environmental Problems', 'ur' => 'ماحولیاتی مسائل'],
            ],
            'Mathematics' => [
                1 => ['en' => 'Real Numbers', 'ur' => 'حقیقی اعداد'],
                2 => ['en' => 'Estimation and Approximation', 'ur' => 'تخمینہ اور قریبی قیمت'],
                3 => ['en' => 'Square Roots and Cube Roots', 'ur' => 'مربع جذر اور مکعب جذر'],
                4 => ['en' => 'Sets', 'ur' => 'مجموعے'],
                5 => ['en' => 'Ratio, Rate and Proportion', 'ur' => 'تناسب، شرح اور نسبت'],
                6 => ['en' => 'Percentage and Financial Arithmetic', 'ur' => 'فی صد اور مالی حساب'],
                7 => ['en' => 'Number Sequence and Patterns', 'ur' => 'عددی تسلسل اور نمونہ'],
                8 => ['en' => 'Expansion and Factorization', 'ur' => 'توسیع اور تجزیہ'],
                9 => ['en' => 'Linear Equations and Inequalities', 'ur' => 'خطی مساوات اور عدم مساوات'],
                10 => ['en' => 'Mensuration', 'ur' => 'پیمائش'],
                11 => ['en' => 'Congruency, Similarity, Triangles and Transformation', 'ur' => 'مطابقت، مشابہت، مثلث اور تبدیلی'],
                12 => ['en' => 'Information Handling', 'ur' => 'معلومات کا انتظام'],
                13 => ['en' => 'Probability', 'ur' => 'احتمال'],
            ],
            'General Science' => [
                1 => ['en' => 'Ecology', 'ur' => 'ماحولیاتی نظام'],
                2 => ['en' => 'Human Nervous System', 'ur' => 'انسانی اعصابی نظام'],
                3 => ['en' => 'Variations, Heredity and Cell Division', 'ur' => 'تغیرات، وراثت اور خلیے کی تقسیم'],
                4 => ['en' => 'Biotechnology', 'ur' => 'بایو ٹیکنالوجی'],
                5 => ['en' => 'Periodic Table', 'ur' => 'دوری جدول'],
                6 => ['en' => 'Chemical Reactions', 'ur' => 'کیمیائی تعامل'],
                7 => ['en' => 'Acids, Bases and Salts', 'ur' => 'تیزاب، القلی اور نمکیات'],
                8 => ['en' => 'Force and Pressure', 'ur' => 'قوت اور دباؤ'],
                9 => ['en' => 'Reflection and Refraction of Light', 'ur' => 'روشنی کا انعکاس اور انکسار'],
                10 => ['en' => 'Electricity and Magnetism', 'ur' => 'بجلی اور مقناطیسیت'],
                11 => ['en' => 'Technology in Everyday Life', 'ur' => 'روزمرہ زندگی میں ٹیکنالوجی'],
                12 => ['en' => 'Our Universe', 'ur' => 'ہمارا کائنات'],
            ],
            'Computer Science' => [
                1 => ['en' => 'Emerging Technologies', 'ur' => 'ابھرتی ہوئی ٹیکنالوجیز'],
                2 => ['en' => 'Efficient Algorithms and Pseudocode', 'ur' => 'موثر الگورتھم اور سیوڈو کوڈ'],
                3 => ['en' => 'Nested Control Structures in Programming', 'ur' => 'پروگرامنگ میں اندرونی کنٹرول ڈھانچے'],
                4 => ['en' => 'Data and Databases', 'ur' => 'ڈیٹا اور ڈیٹا بیس'],
                5 => ['en' => 'Cyber Ethics and Online Safety', 'ur' => 'سائبر اخلاقیات اور آن لائن حفاظت'],
                6 => ['en' => 'Digital Entrepreneurship', 'ur' => 'ڈیجیٹل کاروبار'],
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
