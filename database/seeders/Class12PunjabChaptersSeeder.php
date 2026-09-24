<?php

namespace Database\Seeders;

use App\Models\Grade;
use Database\Seeders\Concerns\SeedsSubjectChapters;
use Illuminate\Database\Seeder;

/**
 * Class 12 (2nd Year) subjects + real chapter titles — Punjab / Lahore (PECTAA).
 */
class Class12PunjabChaptersSeeder extends Seeder
{
    use SeedsSubjectChapters;

    public function run(): void
    {
        $grade = Grade::query()->where('number', 12)->first();
        if (! $grade) {
            return;
        }

        $grade->update(['is_active' => true]);

        $this->syncSubjects($grade, [
            ['en' => 'English', 'ur' => 'انگریزی'],
            ['en' => 'Urdu', 'ur' => 'اردو'],
            ['en' => 'Pakistan Studies', 'ur' => 'مطالعہ پاکستان', 'aliases' => ['Pak Studies']],
            ['en' => 'Mathematics', 'ur' => 'ریاضی'],
            ['en' => 'Physics', 'ur' => 'طبیعیات'],
            ['en' => 'Chemistry', 'ur' => 'کیمسٹری'],
            ['en' => 'Biology', 'ur' => 'حیاتیات'],
            ['en' => 'Computer Science', 'ur' => 'کمپیوٹر سائنس', 'aliases' => ['Computer']],
        ]);

        $map = [
            'English' => [
                1 => ['en' => 'The Dying Sun', 'ur' => 'مرتا ہوا سورج'],
                2 => ['en' => 'Using the Scientific Method', 'ur' => 'سائنسی طریقہ کار'],
                3 => ['en' => 'Why the Sea is Salt', 'ur' => 'سمندر نمکین کیوں ہے'],
                4 => ['en' => 'The Elixir of Life', 'ur' => 'آب حیات'],
                5 => ['en' => 'On Destroying Books', 'ur' => 'کتابیں تباہ کرنے پر'],
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
            'Pakistan Studies' => [
                1 => ['en' => 'Establishment of Pakistan', 'ur' => 'قیام پاکستان'],
                2 => ['en' => 'Early Problems of Pakistan', 'ur' => 'پاکستان کے ابتدائی مسائل'],
                3 => ['en' => 'Constitutional and Political Development', 'ur' => 'آئینی و سیاسی ارتقا'],
                4 => ['en' => 'Economy of Pakistan', 'ur' => 'پاکستان کی معیشت'],
                5 => ['en' => 'Foreign Relations of Pakistan', 'ur' => 'پاکستان کے خارجہ تعلقات'],
                6 => ['en' => 'Pakistan in the Modern World', 'ur' => 'جدید دنیا میں پاکستان'],
            ],
            'Mathematics' => [
                1 => ['en' => 'Functions and Limits', 'ur' => 'تفاعل اور حدود'],
                2 => ['en' => 'Differentiation', 'ur' => 'تفریق'],
                3 => ['en' => 'Higher Order Derivatives', 'ur' => 'اعلیٰ درجے کے مشتقات'],
                4 => ['en' => 'Application of Derivatives', 'ur' => 'مشتقات کے اطلاقات'],
                5 => ['en' => 'Integration', 'ur' => 'تکامل'],
                6 => ['en' => 'Plane Analytic Geometry – Straight Line', 'ur' => 'تحلیلی ہندسہ – سیدھی لکیر'],
                7 => ['en' => 'Plane Analytic Geometry – Circle', 'ur' => 'تحلیلی ہندسہ – دائرہ'],
                8 => ['en' => 'Conic Sections – Parabola', 'ur' => 'مخروطی قطع – قطع مکافی'],
                9 => ['en' => 'Conic Sections – Ellipse', 'ur' => 'مخروطی قطع – بیضوی'],
                10 => ['en' => 'Conic Sections – Hyperbola', 'ur' => 'مخروطی قطع – قطع زائد'],
                11 => ['en' => 'Differential Equations', 'ur' => 'تفریقی مساوات'],
                12 => ['en' => 'Partial Differentiation', 'ur' => 'جزوی تفریق'],
                13 => ['en' => 'Introduction to Numerical Methods', 'ur' => 'عددی طریقوں کا تعارف'],
            ],
            'Physics' => [
                1 => ['en' => 'Electrostatics', 'ur' => 'برقی سکونیات'],
                2 => ['en' => 'Current Electricity', 'ur' => 'برقی رو'],
                3 => ['en' => 'Electromagnetism', 'ur' => 'برق مقناطیسیت'],
                4 => ['en' => 'Electromagnetic Induction', 'ur' => 'برق مقناطیسی شمولیت'],
                5 => ['en' => 'Alternating Current', 'ur' => 'متناوب رو'],
                6 => ['en' => 'Physics of Solids', 'ur' => 'جامدات کی طبیعیات'],
                7 => ['en' => 'Electronics', 'ur' => 'الیکٹرانکس'],
                8 => ['en' => 'Dawn of Modern Physics', 'ur' => 'جدید طبیعیات کا آغاز'],
                9 => ['en' => 'Atomic Spectra', 'ur' => 'ایٹمی طیف'],
                10 => ['en' => 'Nuclear Physics', 'ur' => 'جوہری طبیعیات'],
            ],
            'Chemistry' => [
                1 => ['en' => 'Periodic Classification of Elements and Periodicity', 'ur' => 'عناصر کی دوری درجہ بندی'],
                2 => ['en' => 's-Block Elements', 'ur' => 'ایس بلاک عناصر'],
                3 => ['en' => 'Group IIIA and Group IVA Elements', 'ur' => 'گروپ IIIA اور IVA'],
                4 => ['en' => 'Group VA and Group VIA Elements', 'ur' => 'گروپ VA اور VIA'],
                5 => ['en' => 'Halogens and Noble Gases', 'ur' => 'ہیلوجن اور نوبل گیسیں'],
                6 => ['en' => 'Transition Elements', 'ur' => 'منتقلی عناصر'],
                7 => ['en' => 'Fundamental Principles of Organic Chemistry', 'ur' => 'عضوی کیمیا کے اصول'],
                8 => ['en' => 'Aliphatic Hydrocarbons', 'ur' => 'الیفیٹک ہائیڈرو کاربن'],
                9 => ['en' => 'Aromatic Hydrocarbons', 'ur' => 'ارومیٹک ہائیڈرو کاربن'],
                10 => ['en' => 'Alkyl Halides', 'ur' => 'الکائل ہلائیڈز'],
                11 => ['en' => 'Alcohols, Phenols and Ethers', 'ur' => 'الکوحل، فینول اور ایتھر'],
                12 => ['en' => 'Aldehydes and Ketones', 'ur' => 'الڈی ہائیڈز اور کیٹونز'],
                13 => ['en' => 'Carboxylic Acids', 'ur' => 'کاربوکسلک تیزاب'],
                14 => ['en' => 'Macromolecules', 'ur' => 'بڑے سالمے'],
                15 => ['en' => 'Common Chemical Industries in Pakistan', 'ur' => 'پاکستان کی کیمیائی صنعتیں'],
                16 => ['en' => 'Environmental Chemistry', 'ur' => 'ماحولیاتی کیمیا'],
            ],
            'Biology' => [
                1 => ['en' => 'Homeostasis', 'ur' => 'ہومیوسٹیسیس'],
                2 => ['en' => 'Coordination and Control', 'ur' => 'اشتراک و کنٹرول'],
                3 => ['en' => 'Support and Movement', 'ur' => 'سہارا اور حرکت'],
                4 => ['en' => 'Reproduction', 'ur' => 'تولید'],
                5 => ['en' => 'Growth and Development', 'ur' => 'نمو اور ارتقا'],
                6 => ['en' => 'Chromosomes and DNA', 'ur' => 'کروموسومز اور ڈی این اے'],
                7 => ['en' => 'Cell Cycle', 'ur' => 'خلوی دور'],
                8 => ['en' => 'Variation and Genetics', 'ur' => 'تغیر اور جینیات'],
                9 => ['en' => 'Evolution', 'ur' => 'ارتقاء'],
                10 => ['en' => 'Biotechnology', 'ur' => 'بایو ٹیکنالوجی'],
                11 => ['en' => 'Biology and Human Welfare', 'ur' => 'حیاتیات اور انسانی فلاح'],
                12 => ['en' => 'Man and His Environment', 'ur' => 'انسان اور اس کا ماحول'],
            ],
            'Computer Science' => [
                1 => ['en' => 'Operating Systems', 'ur' => 'آپریٹنگ سسٹمز'],
                2 => ['en' => 'System Development Life Cycle', 'ur' => 'نظام ترقیاتی دور'],
                3 => ['en' => 'Object Oriented Programming in C++', 'ur' => 'سی پلس پلس میں او او پی'],
                4 => ['en' => 'Control Structures', 'ur' => 'کنٹرول اسٹرکچرز'],
                5 => ['en' => 'Arrays and Strings', 'ur' => 'ارے اور سٹرنگز'],
                6 => ['en' => 'Pointers', 'ur' => 'پوائنٹرز'],
                7 => ['en' => 'Objects and Classes', 'ur' => 'آبجیکٹس اور کلاسز'],
                8 => ['en' => 'File Handling', 'ur' => 'فائل ہینڈلنگ'],
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
