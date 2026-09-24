<?php

namespace Database\Seeders;

use App\Models\Grade;
use Database\Seeders\Concerns\SeedsSubjectChapters;
use Illuminate\Database\Seeder;

/**
 * Class 11 (1st Year) subjects + real chapter titles — Punjab / Lahore (PECTAA 2025–26).
 */
class Class11PunjabChaptersSeeder extends Seeder
{
    use SeedsSubjectChapters;

    public function run(): void
    {
        $grade = Grade::query()->where('number', 11)->first();
        if (! $grade) {
            return;
        }

        $grade->update(['is_active' => true]);

        $this->syncSubjects($grade, [
            ['en' => 'English', 'ur' => 'انگریزی'],
            ['en' => 'Urdu', 'ur' => 'اردو'],
            ['en' => 'Islamiyat', 'ur' => 'اسلامیات', 'aliases' => ['Islamiat']],
            ['en' => 'Mathematics', 'ur' => 'ریاضی'],
            ['en' => 'Physics', 'ur' => 'طبیعیات'],
            ['en' => 'Chemistry', 'ur' => 'کیمسٹری'],
            ['en' => 'Biology', 'ur' => 'حیاتیات'],
            ['en' => 'Computer Science', 'ur' => 'کمپیوٹر سائنس', 'aliases' => ['Computer']],
            ['en' => 'Tarjuma-tul-Quran', 'ur' => 'ترجمۃ القرآن', 'aliases' => ['Tarjuma Tul Quran']],
        ]);

        $map = [
            'English' => [
                1 => ['en' => 'Button Button', 'ur' => 'بٹن بٹن'],
                2 => ['en' => 'Clearing in the Sky', 'ur' => 'آسمان میں کھلی جگہ'],
                3 => ['en' => 'Dark They Were, and Golden-Eyed', 'ur' => 'ڈارک دی ویر'],
                4 => ['en' => 'The Piece of String', 'ur' => 'رسی کا ٹکڑا'],
                5 => ['en' => 'The Reward', 'ur' => 'انعام'],
                6 => ['en' => 'Poetry Selections', 'ur' => 'منتخب نظمیں'],
                7 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Urdu' => [
                1 => ['en' => 'Hamd', 'ur' => 'حمد'],
                2 => ['en' => 'Naat', 'ur' => 'نعت'],
                3 => ['en' => 'Nasr Selections', 'ur' => 'نثر کے اسباق'],
                4 => ['en' => 'Ghazal', 'ur' => 'غزل'],
                5 => ['en' => 'Nazm', 'ur' => 'نظم'],
                6 => ['en' => 'Drama', 'ur' => 'ڈرامہ'],
                7 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Islamiyat' => [
                1 => ['en' => 'Fundamentals of Islam', 'ur' => 'اسلام کی بنیادی تعلیمات'],
                2 => ['en' => 'Quran and Hadith', 'ur' => 'قرآن و حدیث'],
                3 => ['en' => 'Seerah and Islamic History', 'ur' => 'سیرت و اسلامی تاریخ'],
                4 => ['en' => 'Islamic Ethics', 'ur' => 'اسلامی اخلاق'],
                5 => ['en' => 'Contemporary Issues in Islamic Perspective', 'ur' => 'عصری مسائل کا اسلامی حل'],
            ],
            'Mathematics' => [
                1 => ['en' => 'Number Systems', 'ur' => 'عددی نظام'],
                2 => ['en' => 'Sets, Functions and Groups', 'ur' => 'مجموعے، تفاعل اور گروپ'],
                3 => ['en' => 'Matrices and Determinants', 'ur' => 'میٹرکس اور تعیین کنندہ'],
                4 => ['en' => 'Quadratic Equations', 'ur' => 'مربعی مساوات'],
                5 => ['en' => 'Partial Fractions', 'ur' => 'جزوی کسرے'],
                6 => ['en' => 'Sequences and Series', 'ur' => 'تسلسل اور سلسلے'],
                7 => ['en' => 'Permutation, Combination and Probability', 'ur' => 'ترتیب، انتخاب اور احتمال'],
                8 => ['en' => 'Mathematical Induction and Binomial Theorem', 'ur' => 'ریاضیاتی استقراء اور دو جملی قضیہ'],
                9 => ['en' => 'Fundamentals of Trigonometry', 'ur' => 'مثلثیات کی بنیادی باتیں'],
                10 => ['en' => 'Trigonometric Identities', 'ur' => 'مثلثیاتی شناختیں'],
                11 => ['en' => 'Trigonometric Functions and their Graphs', 'ur' => 'مثلثیاتی تفاعل اور خط'],
                12 => ['en' => 'Application of Trigonometry', 'ur' => 'مثلثیات کے اطلاقات'],
                13 => ['en' => 'Inverse Trigonometric Functions', 'ur' => 'معکوس مثلثیاتی تفاعل'],
                14 => ['en' => 'Solutions of Triangles', 'ur' => 'مثلثوں کے حل'],
            ],
            'Physics' => [
                1 => ['en' => 'Measurements', 'ur' => 'پیمائش'],
                2 => ['en' => 'Vectors and Equilibrium', 'ur' => 'سمتیے اور توازن'],
                3 => ['en' => 'Motion and Force', 'ur' => 'حرکت اور قوت'],
                4 => ['en' => 'Work and Energy', 'ur' => 'کام اور توانائی'],
                5 => ['en' => 'Circular Motion', 'ur' => 'دائری حرکت'],
                6 => ['en' => 'Fluid Dynamics', 'ur' => 'سیال کی دینامیات'],
                7 => ['en' => 'Oscillations', 'ur' => 'ارتعاشات'],
                8 => ['en' => 'Waves', 'ur' => 'امواج'],
                9 => ['en' => 'Physical Optics', 'ur' => 'طبیعی بصریات'],
                10 => ['en' => 'Optical Instruments', 'ur' => 'بصری آلات'],
                11 => ['en' => 'Heat and Thermodynamics', 'ur' => 'حرارت اور حر دینامیات'],
            ],
            'Chemistry' => [
                1 => ['en' => 'Periodic Table and Periodic Properties', 'ur' => 'دوری جدول اور دوری خصوصیات'],
                2 => ['en' => 'Atomic Structure', 'ur' => 'ایٹم کی ساخت'],
                3 => ['en' => 'Chemical Bonding', 'ur' => 'کیمیائی بندش'],
                4 => ['en' => 'Stoichiometry', 'ur' => 'اسٹویکیومیٹری'],
                5 => ['en' => 'States and Phases of Matter', 'ur' => 'مادے کی حالتیں'],
                6 => ['en' => 'Chemical Energetics', 'ur' => 'کیمیائی توانائیات'],
                7 => ['en' => 'Reaction Kinetics', 'ur' => 'ردعمل کی حرکیات'],
                8 => ['en' => 'Chemical Equilibrium', 'ur' => 'کیمیائی توازن'],
                9 => ['en' => 'Acid-Base Chemistry', 'ur' => 'تیزاب و قاعدہ کیمیا'],
                10 => ['en' => 'Electrochemistry', 'ur' => 'برق کیمیا'],
                11 => ['en' => 'Hydrocarbons', 'ur' => 'ہائیڈرو کاربن'],
                12 => ['en' => 'Nitrogen and Sulfur', 'ur' => 'نائیٹروجن اور سلفر'],
                13 => ['en' => 'Halogens', 'ur' => 'ہیلوجن'],
                14 => ['en' => 'Atmosphere', 'ur' => 'ماحول'],
                15 => ['en' => 'Basic Separation Techniques', 'ur' => 'بنیادی علیحدگی تکنیکیں'],
                16 => ['en' => 'Lab Safety and Practical Skills', 'ur' => 'لیب حفاظت اور عملی مہارتیں'],
            ],
            'Biology' => [
                1 => ['en' => 'Biodiversity and Classification', 'ur' => 'حیاتیاتی تنوع اور درجہ بندی'],
                2 => ['en' => 'Bacteria and Viruses', 'ur' => 'بیکٹیریا اور وائرس'],
                3 => ['en' => 'Cells and Subcellular Organelles', 'ur' => 'خلیے اور ذیلی خلوی اعضاء'],
                4 => ['en' => 'Molecular Biology', 'ur' => 'سالماتی حیاتیات'],
                5 => ['en' => 'Enzymes', 'ur' => 'انزائمز'],
                6 => ['en' => 'Bioenergetics', 'ur' => 'حیاتی توانائیات'],
                7 => ['en' => 'Structural and Computational Biology', 'ur' => 'ساخت و کمپیوٹیشنل حیاتیات'],
                8 => ['en' => 'Plant Physiology', 'ur' => 'نباتاتی فعلیات'],
                9 => ['en' => 'Human Digestive System', 'ur' => 'انسانی نظام ہاضمہ'],
                10 => ['en' => 'Human Respiratory System', 'ur' => 'انسانی نظام تنفس'],
                11 => ['en' => 'Human Circulatory System', 'ur' => 'انسانی نظام دوران خون'],
                12 => ['en' => 'Human Skeletal and Muscular Systems', 'ur' => 'انسانی ڈھانچہ اور پٹھے'],
            ],
            'Computer Science' => [
                1 => ['en' => 'Basics of Information Technology', 'ur' => 'انفارمیشن ٹیکنالوجی کی بنیادی باتیں'],
                2 => ['en' => 'Information Networks', 'ur' => 'معلوماتی نیٹ ورکس'],
                3 => ['en' => 'Data Communications', 'ur' => 'ڈیٹا مواصلات'],
                4 => ['en' => 'Applications and Uses of Computers', 'ur' => 'کمپیوٹر کے اطلاقات'],
                5 => ['en' => 'Computer Architecture', 'ur' => 'کمپیوٹر فن تعمیر'],
                6 => ['en' => 'Security, Copyright and the Law', 'ur' => 'سیکورٹی، کاپی رائٹ اور قانون'],
                7 => ['en' => 'Introduction to Operating Systems', 'ur' => 'آپریٹنگ سسٹم کا تعارف'],
            ],
            'Tarjuma-tul-Quran' => [
                1 => ['en' => 'Selected Surahs – Part 1', 'ur' => 'منتخب سورتیں – حصہ ۱'],
                2 => ['en' => 'Selected Surahs – Part 2', 'ur' => 'منتخب سورتیں – حصہ ۲'],
                3 => ['en' => 'Selected Ayat – Faith and Worship', 'ur' => 'منتخب آیات – ایمان و عبادت'],
                4 => ['en' => 'Selected Ayat – Character and Society', 'ur' => 'منتخب آیات – اخلاق و معاشرت'],
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
