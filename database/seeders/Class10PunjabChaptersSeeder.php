<?php

namespace Database\Seeders;

use App\Models\Grade;
use Database\Seeders\Concerns\SeedsSubjectChapters;
use Illuminate\Database\Seeder;

/**
 * Class 10 subjects + real chapter titles (Punjab / Lahore Board, PCTB–PECTAA).
 */
class Class10PunjabChaptersSeeder extends Seeder
{
    use SeedsSubjectChapters;

    public function run(): void
    {
        $grade = Grade::query()->where('number', 10)->first();
        if (! $grade) {
            return;
        }

        $grade->update(['is_active' => true]);

        $this->syncSubjects($grade, [
            ['en' => 'English', 'ur' => 'انگریزی'],
            ['en' => 'Urdu', 'ur' => 'اردو'],
            ['en' => 'Islamiyat', 'ur' => 'اسلامیات', 'aliases' => ['Islamiat']],
            ['en' => 'Pakistan Studies', 'ur' => 'مطالعہ پاکستان', 'aliases' => ['Pak Studies']],
            ['en' => 'Mathematics', 'ur' => 'ریاضی'],
            ['en' => 'Physics', 'ur' => 'طبیعیات'],
            ['en' => 'Chemistry', 'ur' => 'کیمسٹری'],
            ['en' => 'Biology', 'ur' => 'حیاتیات'],
            ['en' => 'Computer Science', 'ur' => 'کمپیوٹر سائنس', 'aliases' => ['Computer']],
            ['en' => 'Tarjuma-tul-Quran', 'ur' => 'ترجمۃ القرآن', 'aliases' => ['Tarjuma Tul Quran']],
        ]);

        $map = [
            'English' => [
                1 => ['en' => 'Greed Caused Great Loss / Prose Unit 1', 'ur' => 'نثر سبق ۱'],
                2 => ['en' => 'A World Without Books', 'ur' => 'کتابوں کے بغیر دنیا'],
                3 => ['en' => 'Little by Little One Walks Far!', 'ur' => 'تھوڑا تھوڑا کرکے دور تک پہنچا جاتا ہے'],
                4 => ['en' => 'Selecting the Right Career', 'ur' => 'صحیح پیشے کا انتخاب'],
                5 => ['en' => 'Poetry Unit', 'ur' => 'نظم'],
                6 => ['en' => 'The rain / Loveliest of Trees', 'ur' => 'بارش / خوبصورت درخت'],
                7 => ['en' => 'Review / Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Urdu' => [
                1 => ['en' => 'Hamd', 'ur' => 'حمد'],
                2 => ['en' => 'Naat', 'ur' => 'نعت'],
                3 => ['en' => 'Prose Lesson 1', 'ur' => 'نثر سبق ۱'],
                4 => ['en' => 'Prose Lesson 2', 'ur' => 'نثر سبق ۲'],
                5 => ['en' => 'Prose Lesson 3', 'ur' => 'نثر سبق ۳'],
                6 => ['en' => 'Ghazal', 'ur' => 'غزل'],
                7 => ['en' => 'Nazm', 'ur' => 'نظم'],
                8 => ['en' => 'Drama', 'ur' => 'ڈرامہ'],
                9 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Islamiyat' => [
                1 => ['en' => 'Selected Quranic Ayat', 'ur' => 'منتخب قرآنی آیات'],
                2 => ['en' => 'Hadith', 'ur' => 'احادیث'],
                3 => ['en' => 'Life of the Holy Prophet (PBUH)', 'ur' => 'سیرت النبی ﷺ'],
                4 => ['en' => 'Islamic Society and Ethics', 'ur' => 'اسلامی معاشرت و اخلاق'],
                5 => ['en' => 'Rights of People', 'ur' => 'حقوق العباد'],
            ],
            'Pakistan Studies' => [
                1 => ['en' => 'History of Pakistan (1947–onwards)', 'ur' => 'پاکستان کی تاریخ'],
                2 => ['en' => 'Geography of Pakistan', 'ur' => 'پاکستان کا جغرافیہ'],
                3 => ['en' => 'Resources of Pakistan', 'ur' => 'پاکستان کے وسائل'],
                4 => ['en' => 'Constitutional Development', 'ur' => 'آئینی ارتقا'],
                5 => ['en' => 'Foreign Policy of Pakistan', 'ur' => 'پاکستان کی خارجہ پالیسی'],
                6 => ['en' => 'Pakistan and the Muslim World', 'ur' => 'پاکستان اور عالم اسلام'],
            ],
            'Mathematics' => [
                1 => ['en' => 'Quadratic Equations', 'ur' => 'مربعی مساوات'],
                2 => ['en' => 'Theory of Quadratic Equations', 'ur' => 'مربعی مساوات کا نظریہ'],
                3 => ['en' => 'Variations', 'ur' => 'تغیرات'],
                4 => ['en' => 'Partial Fractions', 'ur' => 'جزوی کسرے'],
                5 => ['en' => 'Sets and Functions', 'ur' => 'مجموعے اور تفاعل'],
                6 => ['en' => 'Basic Statistics', 'ur' => 'بنیادی شماریات'],
                7 => ['en' => 'Introduction to Trigonometry', 'ur' => 'مثلثیات کا تعارف'],
                8 => ['en' => 'Projection of a Straight Line / Trigonometric Identities', 'ur' => 'مثلثیاتی شناختیں'],
                9 => ['en' => 'Chords of a Circle', 'ur' => 'دائرے کے وتر'],
                10 => ['en' => 'Tangent to a Circle', 'ur' => 'دائرے کا مماس'],
                11 => ['en' => 'Chords and Arcs', 'ur' => 'وتر اور قوس'],
                12 => ['en' => 'Angle in a Segment of a Circle', 'ur' => 'دائرے کے قطعے میں زاویہ'],
                13 => ['en' => 'Practical Geometry – Circles', 'ur' => 'عملی ہندسہ – دائرے'],
            ],
            'Physics' => [
                1 => ['en' => 'Thermal Physics', 'ur' => 'حرارتی طبیعیات'],
                2 => ['en' => 'Transfer of Thermal Energy', 'ur' => 'حرارتی توانائی کی منتقلی'],
                3 => ['en' => 'Waves', 'ur' => 'امواج'],
                4 => ['en' => 'Sound', 'ur' => 'آواز'],
                5 => ['en' => 'Light', 'ur' => 'روشنی'],
                6 => ['en' => 'Electrostatics', 'ur' => 'برقی سکونیات'],
                7 => ['en' => 'Electricity', 'ur' => 'بجلی'],
                8 => ['en' => 'Electromagnetism', 'ur' => 'برق مقناطیسیت'],
                9 => ['en' => 'Electromagnetic Induction and Electromagnetic Waves', 'ur' => 'برق مقناطیسی شمولیت و امواج'],
                10 => ['en' => 'Electronics', 'ur' => 'الیکٹرانکس'],
                11 => ['en' => 'Atomic and Nuclear Physics', 'ur' => 'ایٹمی و جوہری طبیعیات'],
                12 => ['en' => 'Space and Environment', 'ur' => 'خلا اور ماحول'],
            ],
            'Chemistry' => [
                1 => ['en' => 'Chemical Equilibrium', 'ur' => 'کیمیائی توازن'],
                2 => ['en' => 'Acid, Base and Salt', 'ur' => 'تیزاب، قاعدہ اور نمک'],
                3 => ['en' => 'Organic Chemistry', 'ur' => 'عضوی کیمیا'],
                4 => ['en' => 'Hydrocarbons', 'ur' => 'ہائیڈرو کاربن'],
                5 => ['en' => 'Biochemistry', 'ur' => 'حیاتی کیمیا'],
                6 => ['en' => 'Environmental Chemistry I – Atmosphere', 'ur' => 'ماحولیاتی کیمیا ۱'],
                7 => ['en' => 'Environmental Chemistry II – Water', 'ur' => 'ماحولیاتی کیمیا ۲ – پانی'],
                8 => ['en' => 'Chemical Industries', 'ur' => 'کیمیائی صنعتیں'],
            ],
            'Biology' => [
                1 => ['en' => 'Gaseous Exchange', 'ur' => 'گیسوں کا تبادلہ'],
                2 => ['en' => 'Homeostasis', 'ur' => 'ہومیوسٹیسیس'],
                3 => ['en' => 'Coordination and Control', 'ur' => 'اشتراک و کنٹرول'],
                4 => ['en' => 'Support and Movement', 'ur' => 'سہارا اور حرکت'],
                5 => ['en' => 'Reproduction', 'ur' => 'تولید'],
                6 => ['en' => 'Inheritance', 'ur' => 'وراثت'],
                7 => ['en' => 'Man and His Environment', 'ur' => 'انسان اور اس کا ماحول'],
                8 => ['en' => 'Biotechnology', 'ur' => 'بایو ٹیکنالوجی'],
                9 => ['en' => 'Pharmacology', 'ur' => 'فارماکالوجی'],
            ],
            'Computer Science' => [
                1 => ['en' => 'Introduction to Systems Development Life Cycle', 'ur' => 'نظام ترقیاتی دور کا تعارف'],
                2 => ['en' => 'Object Oriented Programming Concepts', 'ur' => 'آبجیکٹ اورینٹڈ پروگرامنگ'],
                3 => ['en' => 'Data and Privacy', 'ur' => 'ڈیٹا اور رازداری'],
                4 => ['en' => 'Data Communication', 'ur' => 'ڈیٹا مواصلات'],
                5 => ['en' => 'Computer Networks', 'ur' => 'کمپیوٹر نیٹ ورکس'],
                6 => ['en' => 'Applications of Computer Science', 'ur' => 'کمپیوٹر سائنس کے اطلاقات'],
            ],
            'Tarjuma-tul-Quran' => [
                1 => ['en' => 'Selected Surahs – Part A', 'ur' => 'منتخب سورتیں – حصہ الف'],
                2 => ['en' => 'Selected Surahs – Part B', 'ur' => 'منتخب سورتیں – حصہ ب'],
                3 => ['en' => 'Selected Ayat – Social and Moral Teachings', 'ur' => 'منتخب آیات – اخلاقی تعلیمات'],
                4 => ['en' => 'Selected Ayat – Rights and Duties', 'ur' => 'منتخب آیات – حقوق و فرائض'],
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
