<?php

namespace Database\Seeders;

use App\Models\Grade;
use Database\Seeders\Concerns\SeedsSubjectChapters;
use Illuminate\Database\Seeder;

/**
 * Class 6 subjects + chapter titles (Punjab / PCTB–PECTAA, SNC 2022–24).
 */
class Class6PunjabChaptersSeeder extends Seeder
{
    use SeedsSubjectChapters;

    public function run(): void
    {
        $grade = Grade::query()->where('number', 6)->first();
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
                1 => ['en' => 'Dedicated to Humanity – Abdul Sattar Edhi', 'ur' => 'انسانیت کے لیے وقف – عبدالستار ایدھی'],
                2 => ['en' => 'Stories of All Time', 'ur' => 'ہمیشہ کی کہانیاں'],
                3 => ['en' => 'Health and Safety', 'ur' => 'صحت اور حفاظت'],
                4 => ['en' => 'Sports and Games', 'ur' => 'کھیل'],
                5 => ['en' => 'Hazrat Muhammad ﷺ', 'ur' => 'حضرت محمد ﷺ'],
                6 => ['en' => 'Pride of Pakistan', 'ur' => 'پاکستان کا فخر'],
                7 => ['en' => 'Professions and Dignity of Labour', 'ur' => 'پیشے اور محنت کی عزت'],
                8 => ['en' => 'Self, People and Places', 'ur' => 'خود، لوگ اور مقامات'],
                9 => ['en' => 'Science and Technology', 'ur' => 'سائنس اور ٹیکنالوجی'],
                10 => ['en' => 'Importance of Education', 'ur' => 'تعلیم کی اہمیت'],
                11 => ['en' => 'Poetry Unit', 'ur' => 'نظم'],
                12 => ['en' => 'Good Citizen', 'ur' => 'اچھا شہری'],
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
                4 => ['en' => 'Islamic Ethics and Character', 'ur' => 'اسلامی اخلاق و کردار'],
                5 => ['en' => 'Selected Ayat', 'ur' => 'منتخب آیات'],
                6 => ['en' => 'Selected Ahadith', 'ur' => 'منتخب احادیث'],
                7 => ['en' => 'Rights and Social Responsibilities', 'ur' => 'حقوق و فرائض'],
            ],
            'History' => [
                1 => ['en' => 'Studying History', 'ur' => 'تاریخ کا مطالعہ'],
                2 => ['en' => 'Ancient Civilizations', 'ur' => 'قدیم تہذیبیں'],
                3 => ['en' => 'Indus Valley Civilization', 'ur' => 'وادی سندھ کی تہذیب'],
                4 => ['en' => 'The Aryans', 'ur' => 'آریا'],
                5 => ['en' => 'Buddhism and Related Kingdoms', 'ur' => 'بدھ مت اور متعلقہ سلطنتیں'],
                6 => ['en' => 'Alexander and the Mauryans', 'ur' => 'سکندر اور موریہ سلطنت'],
                7 => ['en' => 'Arrival of Muslims in South Asia', 'ur' => 'جنوبی ایشیا میں مسلمانوں کی آمد'],
            ],
            'Geography' => [
                1 => ['en' => 'Globe and Maps', 'ur' => 'کرہ ارض اور نقشے'],
                2 => ['en' => 'Latitudes and Longitudes', 'ur' => 'عرض البلد اور طول البلد'],
                3 => ['en' => 'Continents and Oceans', 'ur' => 'براعظم اور سمندر'],
                4 => ['en' => 'Weather and Climate', 'ur' => 'موسم اور آب و ہوا'],
                5 => ['en' => 'Natural Vegetation', 'ur' => 'قدرتی نباتات'],
                6 => ['en' => 'Population', 'ur' => 'آبادی'],
            ],
            'Mathematics' => [
                1 => ['en' => 'Factors and Multiples', 'ur' => 'عوامل اور مضرب'],
                2 => ['en' => 'Integers', 'ur' => 'صحیح اعداد'],
                3 => ['en' => 'Laws of Integers', 'ur' => 'صحیح اعداد کے قوانین'],
                4 => ['en' => 'Rate, Ratio and Percentage', 'ur' => 'شرح، تناسب اور فی صد'],
                5 => ['en' => 'Sets', 'ur' => 'مجموعے'],
                6 => ['en' => 'Algebraic Expressions', 'ur' => 'الجبرائی اظہار'],
                7 => ['en' => 'Linear Equations', 'ur' => 'خطی مساوات'],
                8 => ['en' => 'Surface Area and Volume', 'ur' => 'سطحی رقبہ اور حجم'],
                9 => ['en' => 'Symmetry', 'ur' => 'توازن'],
                10 => ['en' => 'Geometrical Constructions', 'ur' => 'ہندسی تعمیر'],
                11 => ['en' => 'Data Management', 'ur' => 'معلومات کا انتظام'],
                12 => ['en' => 'Probability', 'ur' => 'احتمال'],
            ],
            'General Science' => [
                1 => ['en' => 'Cellular Organization', 'ur' => 'سیلولر تنظیم'],
                2 => ['en' => 'Reproduction in Plants', 'ur' => 'پودوں میں تولید'],
                3 => ['en' => 'Balanced Diet', 'ur' => 'متوازن خوراک'],
                4 => ['en' => 'Human Digestive System', 'ur' => 'انسانی نظام انہضام'],
                5 => ['en' => 'Matter as Particles', 'ur' => 'مادہ بطور ذرات'],
                6 => ['en' => 'Elements and Compounds', 'ur' => 'عناصر اور مرکبات'],
                7 => ['en' => 'Mixtures', 'ur' => 'مخلوطات'],
                8 => ['en' => 'Energy', 'ur' => 'توانائی'],
                9 => ['en' => 'Electricity', 'ur' => 'بجلی'],
                10 => ['en' => 'Magnetism', 'ur' => 'مقناطیسیت'],
                11 => ['en' => 'Technology in Everyday Life', 'ur' => 'روزمرہ زندگی میں ٹیکنالوجی'],
                12 => ['en' => 'Solar System', 'ur' => 'نظام شمسی'],
            ],
            'Computer Science' => [
                1 => ['en' => 'ICT Fundamentals', 'ur' => 'آئی سی ٹی کی بنیادی باتیں'],
                2 => ['en' => 'Digital Skills and Applications', 'ur' => 'ڈیجیٹل مہارتیں اور اطلاقات'],
                3 => ['en' => 'Algorithmic Thinking and Problem Solving', 'ur' => 'الگورتھمی سوچ اور مسئلہ حل'],
                4 => ['en' => 'Introduction to Programming (Scratch)', 'ur' => 'پروگرامنگ کا تعارف (اسکریچ)'],
                5 => ['en' => 'Digital Citizenship and Online Safety', 'ur' => 'ڈیجیٹل شہریت اور آن لائن حفاظت'],
                6 => ['en' => 'Entrepreneurship in the Digital Age', 'ur' => 'ڈیجیٹل دور میں کاروبار'],
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
