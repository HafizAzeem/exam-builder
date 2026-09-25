<?php

namespace Database\Seeders;

use App\Models\Grade;
use Database\Seeders\Concerns\SeedsSubjectChapters;
use Illuminate\Database\Seeder;

/**
 * Priority-1 Arts / Humanities / Commerce subjects + real chapter titles
 * for Classes 9–12 (Punjab / PCTB–PECTAA). Does not deactivate Science subjects.
 */
class PunjabArtsCommerceChaptersSeeder extends Seeder
{
    use SeedsSubjectChapters;

    public function run(): void
    {
        $this->seedClass9();
        $this->seedClass10();
        $this->seedClass11();
        $this->seedClass12();
    }

    /**
     * Upsert subjects without deactivating existing Science subjects.
     *
     * @param  list<array{en: string, ur: string, aliases?: list<string>}>  $subjects
     */
    protected function upsertSubjects(Grade $grade, array $subjects): void
    {
        $sortBase = (int) $grade->subjects()->max('sort_order');

        foreach ($subjects as $item) {
            $names = array_merge([$item['en']], $item['aliases'] ?? []);
            $sortBase++;

            $subject = $grade->subjects()->whereIn('name_en', $names)->first();

            if (! $subject) {
                $grade->subjects()->create([
                    'name_en' => $item['en'],
                    'name_ur' => $item['ur'],
                    'sort_order' => $sortBase,
                    'is_active' => true,
                ]);
            } else {
                $subject->update([
                    'name_en' => $item['en'],
                    'name_ur' => $item['ur'],
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * @param  array<string, array<int, array{en: string, ur?: string|null}>>  $map
     */
    protected function applyChapters(Grade $grade, array $map): void
    {
        foreach ($map as $subjectName => $chapters) {
            $subject = $this->subjectByName($grade, $subjectName);
            if ($subject) {
                $this->syncChapters($subject, $chapters);
            }
        }
    }

    protected function seedClass9(): void
    {
        $grade = Grade::query()->where('number', 9)->first();
        if (! $grade) {
            return;
        }

        $this->upsertSubjects($grade, [
            ['en' => 'General Mathematics', 'ur' => 'جنرل ریاضی', 'aliases' => ['General Math']],
            ['en' => 'General Science', 'ur' => 'جنرل سائنس'],
            ['en' => 'Civics', 'ur' => 'شہریت', 'aliases' => ['Shehriyat']],
            ['en' => 'Education', 'ur' => 'تعلیم', 'aliases' => ['Ilm-ul-Taleem', 'Mabadiyat-e-Taleem']],
            ['en' => 'Economics', 'ur' => 'معاشیات'],
            ['en' => 'Geography', 'ur' => 'جغرافیہ'],
            ['en' => 'History of Pakistan', 'ur' => 'تاریخ پاکستان', 'aliases' => ['History']],
            ['en' => 'Punjabi', 'ur' => 'پنجابی'],
            ['en' => 'Persian', 'ur' => 'فارسی', 'aliases' => ['Farsi']],
            ['en' => 'Arabic', 'ur' => 'عربی'],
            ['en' => 'Home Economics', 'ur' => 'گھر داریات', 'aliases' => ['Elements of Home Economics']],
            ['en' => 'Health and Physical Education', 'ur' => 'صحت و جسمانی تعلیم', 'aliases' => ['Physical Education', 'Health & Physical Education']],
        ]);

        $this->applyChapters($grade, [
            'General Mathematics' => [
                1 => ['en' => 'Percentage, Ratio and Proportion', 'ur' => 'فیصد، تناسب اور تناسب'],
                2 => ['en' => 'Zakat, Ushar and Inheritance', 'ur' => 'زکوٰۃ، عشر اور وراثت'],
                3 => ['en' => 'Business Mathematics', 'ur' => 'کاروباری ریاضی'],
                4 => ['en' => 'Financial Mathematics', 'ur' => 'مالیاتی ریاضی'],
                5 => ['en' => 'Consumer Mathematics', 'ur' => 'صارفین کی ریاضی'],
                6 => ['en' => 'Exponents and Logarithms', 'ur' => 'قوتیں اور لوگارتھم'],
                7 => ['en' => 'Arithmetic and Geometric Sequences', 'ur' => 'حسابی اور ہندسی سلسلے'],
                8 => ['en' => 'Sets and Functions', 'ur' => 'مجموعے اور تفاعل'],
                9 => ['en' => 'Linear Graphs', 'ur' => 'خطی گراف'],
                10 => ['en' => 'Basic Statistics', 'ur' => 'بنیادی شماریات'],
            ],
            'General Science' => [
                1 => ['en' => 'Introduction and Role of Science', 'ur' => 'سائنس کا تعارف اور کردار'],
                2 => ['en' => 'Our Life and Chemistry', 'ur' => 'ہماری زندگی اور کیمسٹری'],
                3 => ['en' => 'Biochemistry and Biotechnology', 'ur' => 'حیاتی کیمیا اور بائیو ٹیکنالوجی'],
                4 => ['en' => 'Human Health', 'ur' => 'انسانی صحت'],
                5 => ['en' => 'Diseases, Cause and Prevention', 'ur' => 'بیماریاں، اسباب اور احتیاط'],
                6 => ['en' => 'Environment and Natural Resources', 'ur' => 'ماحول اور قدرتی وسائل'],
            ],
            'Civics' => [
                1 => ['en' => 'Introduction to Civics', 'ur' => 'علم شہریت کا تعارف'],
                2 => ['en' => 'Relations Between Individuals', 'ur' => 'افراد کے روابط'],
                3 => ['en' => 'The State', 'ur' => 'ریاست'],
                4 => ['en' => 'Government', 'ur' => 'حکومت'],
                5 => ['en' => 'Citizen and Citizenship', 'ur' => 'شہری اور شہریت'],
            ],
            'Education' => [
                1 => ['en' => 'Concepts of Education', 'ur' => 'تعلیم کے تصورات'],
                2 => ['en' => 'Scope and Functions of Education', 'ur' => 'تعلیم کا دائرہ کار اور وظائف'],
                3 => ['en' => 'Human Growth and Development', 'ur' => 'انسانی نشوونما اور بالیدگی'],
                4 => ['en' => 'Learning', 'ur' => 'تعلّم'],
                5 => ['en' => 'Home, School and Society', 'ur' => 'گھر، سکول اور معاشرہ'],
            ],
            'Economics' => [
                1 => ['en' => 'Basic Concepts of Economics', 'ur' => 'معاشیات کے بنیادی تصورات'],
                2 => ['en' => 'Demand', 'ur' => 'طلب'],
                3 => ['en' => 'Supply', 'ur' => 'سپلائی'],
                4 => ['en' => 'Price Determination', 'ur' => 'قیمت کا تعین'],
                5 => ['en' => 'Production and Factors of Production', 'ur' => 'پیداوار اور عواملِ پیداوار'],
            ],
            'Geography' => [
                1 => ['en' => 'Introduction to Geography', 'ur' => 'جغرافیہ کا تعارف'],
                2 => ['en' => 'The Earth', 'ur' => 'زمین'],
                3 => ['en' => 'Maps and Map Reading', 'ur' => 'نقشے اور نقشہ خوانی'],
                4 => ['en' => 'Atmosphere', 'ur' => 'فضا'],
                5 => ['en' => 'Hydrosphere', 'ur' => 'ماہی کرہ'],
                6 => ['en' => 'Lithosphere and Landforms', 'ur' => 'ارضیہ اور زمینی اشکال'],
            ],
            'History of Pakistan' => [
                1 => ['en' => 'Ideological Basis of Pakistan', 'ur' => 'پاکستان کی نظریاتی بنیاد'],
                2 => ['en' => 'Muslim Rule in the Subcontinent', 'ur' => 'برصغیر میں مسلمانوں کی حکومت'],
                3 => ['en' => 'Decline of Muslim Power', 'ur' => 'مسلمانوں کے زوال'],
                4 => ['en' => 'Reform Movements', 'ur' => 'اصلاحی تحریکیں'],
                5 => ['en' => 'Pakistan Movement (Early Phase)', 'ur' => 'تحریکِ پاکستان (ابتدائی دور)'],
            ],
            'Punjabi' => [
                1 => ['en' => 'Nasr / Prose Lesson 1', 'ur' => 'نثر سبق ۱'],
                2 => ['en' => 'Nasr / Prose Lesson 2', 'ur' => 'نثر سبق ۲'],
                3 => ['en' => 'Nazm / Poem 1', 'ur' => 'نظم ۱'],
                4 => ['en' => 'Nazm / Poem 2', 'ur' => 'نظم ۲'],
                5 => ['en' => 'Ghazal', 'ur' => 'غزل'],
                6 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Persian' => [
                1 => ['en' => 'Alphabet and Basic Reading', 'ur' => 'حروف تہجی اور بنیادی مطالعہ'],
                2 => ['en' => 'Selected Prose', 'ur' => 'منتخب نثر'],
                3 => ['en' => 'Selected Poetry', 'ur' => 'منتخب شاعری'],
                4 => ['en' => 'Grammar', 'ur' => 'قواعد'],
                5 => ['en' => 'Translation and Composition', 'ur' => 'ترجمہ و انشاء'],
            ],
            'Arabic' => [
                1 => ['en' => 'Alphabet and Basic Reading', 'ur' => 'حروف تہجی اور بنیادی مطالعہ'],
                2 => ['en' => 'Selected Lessons (Prose)', 'ur' => 'منتخب اسباق (نثر)'],
                3 => ['en' => 'Selected Poetry', 'ur' => 'منتخب شاعری'],
                4 => ['en' => 'Grammar', 'ur' => 'قواعد'],
                5 => ['en' => 'Translation and Composition', 'ur' => 'ترجمہ و انشاء'],
            ],
            'Home Economics' => [
                1 => ['en' => 'Introduction to Home Economics', 'ur' => 'گھر داریات کا تعارف'],
                2 => ['en' => 'Home Management', 'ur' => 'گھریلو انتظام'],
                3 => ['en' => 'Food and Nutrition Basics', 'ur' => 'غذا اور غذائیت کی بنیادی باتیں'],
                4 => ['en' => 'Clothing and Textiles Basics', 'ur' => 'کپڑے اور کپڑے کی بنیادی باتیں'],
                5 => ['en' => 'Child Care and Family', 'ur' => 'بچوں کی دیکھ بھال اور خاندان'],
            ],
            'Health and Physical Education' => [
                1 => ['en' => 'Introduction to Physical Education', 'ur' => 'جسمانی تعلیم کا تعارف'],
                2 => ['en' => 'Human Body and Health', 'ur' => 'انسانی جسم اور صحت'],
                3 => ['en' => 'Exercise and Fitness', 'ur' => 'ورزش اور فٹنس'],
                4 => ['en' => 'Games and Sports', 'ur' => 'کھیل اور اسپورٹس'],
                5 => ['en' => 'First Aid and Safety', 'ur' => 'ابتدائی طبی امداد اور حفاظت'],
            ],
        ]);
    }

    protected function seedClass10(): void
    {
        $grade = Grade::query()->where('number', 10)->first();
        if (! $grade) {
            return;
        }

        $this->upsertSubjects($grade, [
            ['en' => 'General Mathematics', 'ur' => 'جنرل ریاضی', 'aliases' => ['General Math']],
            ['en' => 'General Science', 'ur' => 'جنرل سائنس'],
            ['en' => 'Civics', 'ur' => 'شہریت', 'aliases' => ['Shehriyat']],
            ['en' => 'Education', 'ur' => 'تعلیم', 'aliases' => ['Ilm-ul-Taleem', 'Mabadiyat-e-Taleem']],
            ['en' => 'Economics', 'ur' => 'معاشیات'],
            ['en' => 'Geography', 'ur' => 'جغرافیہ'],
            ['en' => 'History of Pakistan', 'ur' => 'تاریخ پاکستان', 'aliases' => ['History']],
            ['en' => 'Punjabi', 'ur' => 'پنجابی'],
            ['en' => 'Persian', 'ur' => 'فارسی', 'aliases' => ['Farsi']],
            ['en' => 'Arabic', 'ur' => 'عربی'],
            ['en' => 'Home Economics', 'ur' => 'گھر داریات', 'aliases' => ['Elements of Home Economics']],
            ['en' => 'Health and Physical Education', 'ur' => 'صحت و جسمانی تعلیم', 'aliases' => ['Physical Education', 'Health & Physical Education']],
        ]);

        $this->applyChapters($grade, [
            'General Mathematics' => [
                1 => ['en' => 'Algebraic Formulas and Applications', 'ur' => 'الجبرائی فارمولے اور اطلاقات'],
                2 => ['en' => 'Factorization', 'ur' => 'تجزیہ'],
                3 => ['en' => 'Algebraic Manipulation', 'ur' => 'الجبرائی عمل'],
                4 => ['en' => 'Linear Equations and Inequalities', 'ur' => 'خطی مساوات اور عدم مساوات'],
                5 => ['en' => 'Quadratic Equations', 'ur' => 'مربعی مساوات'],
                6 => ['en' => 'Matrices and Determinants', 'ur' => 'میٹرکس اور ڈیٹرمننٹ'],
                7 => ['en' => 'Fundamentals of Geometry', 'ur' => 'ہندسہ کی بنیادی باتیں'],
                8 => ['en' => 'Practical Geometry', 'ur' => 'عملی ہندسہ'],
                9 => ['en' => 'Areas and Volumes', 'ur' => 'رقعے اور احجام'],
                10 => ['en' => 'Introduction to Coordinate Geometry', 'ur' => 'مختصاتی ہندسہ کا تعارف'],
            ],
            'General Science' => [
                1 => ['en' => 'Energy', 'ur' => 'توانائی'],
                2 => ['en' => 'Current Electricity', 'ur' => 'برقی رو'],
                3 => ['en' => 'Basic Electronics', 'ur' => 'بنیادی الیکٹرانکس'],
                4 => ['en' => 'Science and Technology', 'ur' => 'سائنس اور ٹیکنالوجی'],
                5 => ['en' => 'Space and Nuclear Programme of Pakistan', 'ur' => 'پاکستان کا خلائی اور جوہری پروگرام'],
            ],
            'Civics' => [
                1 => ['en' => 'Rights and Duties', 'ur' => 'حقوق و فرائض'],
                2 => ['en' => 'Two-Nation Theory and Emergence of Pakistan', 'ur' => 'نظریہ دو قومی اور قیام پاکستان'],
                3 => ['en' => 'Pakistan Appears on the World Map', 'ur' => 'پاکستان عالمی نقشے پر'],
                4 => ['en' => 'Constitutional Development in Pakistan', 'ur' => 'پاکستان میں آئینی ارتقا'],
                5 => ['en' => 'Constitution of Pakistan 1973', 'ur' => 'آئین پاکستان ۱۹۷۳ء'],
                6 => ['en' => 'Local Government System in Pakistan', 'ur' => 'پاکستان میں مقامی حکومت کا نظام'],
                7 => ['en' => 'Pakistan and the Outer World', 'ur' => 'پاکستان اور بیرونی دنیا'],
            ],
            'Education' => [
                1 => ['en' => 'Education in Pakistan', 'ur' => 'پاکستان میں تعلیم'],
                2 => ['en' => 'Curriculum', 'ur' => 'نصاب'],
                3 => ['en' => 'Organization of School Activities', 'ur' => 'مدرسہ کی سرگرمیوں کی تنظیم'],
                4 => ['en' => 'Guidance and Counselling', 'ur' => 'رہنمائی اور مشاورت'],
            ],
            'Economics' => [
                1 => ['en' => 'Production and Distribution', 'ur' => 'پیداوار اور تقسیم'],
                2 => ['en' => 'Money', 'ur' => 'رقم'],
                3 => ['en' => 'Banks', 'ur' => 'بینک'],
                4 => ['en' => 'Trade', 'ur' => 'تجارت'],
                5 => ['en' => 'Public and Private Finance', 'ur' => 'عوامی اور نجی مالیات'],
                6 => ['en' => 'Economic Development in Pakistan', 'ur' => 'پاکستان میں معاشی ترقی'],
                7 => ['en' => 'Islamic Economic System', 'ur' => 'اسلامی معاشی نظام'],
            ],
            'Geography' => [
                1 => ['en' => 'Geography of Pakistan – Location and Extent', 'ur' => 'پاکستان کا جغرافیہ – مقام و حدود'],
                2 => ['en' => 'Physical Features of Pakistan', 'ur' => 'پاکستان کی طبعی خصوصیات'],
                3 => ['en' => 'Climate of Pakistan', 'ur' => 'پاکستان کا موسم'],
                4 => ['en' => 'Natural Resources of Pakistan', 'ur' => 'پاکستان کے قدرتی وسائل'],
                5 => ['en' => 'Population of Pakistan', 'ur' => 'پاکستان کی آبادی'],
                6 => ['en' => 'Agriculture and Industry', 'ur' => 'زراعت اور صنعت'],
            ],
            'History of Pakistan' => [
                1 => ['en' => 'Pakistan Movement (Later Phase)', 'ur' => 'تحریکِ پاکستان (بعد کا دور)'],
                2 => ['en' => 'Creation of Pakistan 1947', 'ur' => 'قیام پاکستان ۱۹۴۷ء'],
                3 => ['en' => 'Early Years of Pakistan', 'ur' => 'پاکستان کے ابتدائی سال'],
                4 => ['en' => 'Constitutional History', 'ur' => 'آئینی تاریخ'],
                5 => ['en' => 'Pakistan in the Modern World', 'ur' => 'جدید دنیا میں پاکستان'],
            ],
            'Punjabi' => [
                1 => ['en' => 'Nasr / Prose Lesson 1', 'ur' => 'نثر سبق ۱'],
                2 => ['en' => 'Nasr / Prose Lesson 2', 'ur' => 'نثر سبق ۲'],
                3 => ['en' => 'Nazm / Poem 1', 'ur' => 'نظم ۱'],
                4 => ['en' => 'Nazm / Poem 2', 'ur' => 'نظم ۲'],
                5 => ['en' => 'Ghazal', 'ur' => 'غزل'],
                6 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Persian' => [
                1 => ['en' => 'Selected Prose', 'ur' => 'منتخب نثر'],
                2 => ['en' => 'Selected Poetry', 'ur' => 'منتخب شاعری'],
                3 => ['en' => 'Grammar', 'ur' => 'قواعد'],
                4 => ['en' => 'Translation', 'ur' => 'ترجمہ'],
                5 => ['en' => 'Composition', 'ur' => 'انشاء'],
            ],
            'Arabic' => [
                1 => ['en' => 'Selected Lessons (Prose)', 'ur' => 'منتخب اسباق (نثر)'],
                2 => ['en' => 'Selected Poetry', 'ur' => 'منتخب شاعری'],
                3 => ['en' => 'Grammar', 'ur' => 'قواعد'],
                4 => ['en' => 'Translation', 'ur' => 'ترجمہ'],
                5 => ['en' => 'Composition', 'ur' => 'انشاء'],
            ],
            'Home Economics' => [
                1 => ['en' => 'Home Management and Budgeting', 'ur' => 'گھریلو انتظام اور بجٹ'],
                2 => ['en' => 'Food Preparation and Nutrition', 'ur' => 'کھانے کی تیاری اور غذائیت'],
                3 => ['en' => 'Clothing Care and Design', 'ur' => 'کپڑوں کی دیکھ بھال اور ڈیزائن'],
                4 => ['en' => 'Family Relationships', 'ur' => 'خاندانی تعلقات'],
                5 => ['en' => 'Home Decoration and Hygiene', 'ur' => 'گھر کی سجاوٹ اور صفائی'],
            ],
            'Health and Physical Education' => [
                1 => ['en' => 'Physical Fitness and Training', 'ur' => 'جسمانی فٹنس اور تربیت'],
                2 => ['en' => 'Athletics', 'ur' => 'ایتھلیٹکس'],
                3 => ['en' => 'Major Games', 'ur' => 'اہم کھیل'],
                4 => ['en' => 'Health Education', 'ur' => 'صحت کی تعلیم'],
                5 => ['en' => 'Sports Rules and Organization', 'ur' => 'کھیل کے قوانین اور تنظیم'],
            ],
        ]);
    }

    protected function seedClass11(): void
    {
        $grade = Grade::query()->where('number', 11)->first();
        if (! $grade) {
            return;
        }

        $this->upsertSubjects($grade, [
            ['en' => 'Civics', 'ur' => 'شہریت'],
            ['en' => 'Education', 'ur' => 'تعلیم', 'aliases' => ['Ilm-ul-Taleem']],
            ['en' => 'Economics', 'ur' => 'معاشیات'],
            ['en' => 'Psychology', 'ur' => 'نفسیات'],
            ['en' => 'Sociology', 'ur' => 'عمرانیات'],
            ['en' => 'Geography', 'ur' => 'جغرافیہ'],
            ['en' => 'History', 'ur' => 'تاریخ', 'aliases' => ['History of Pakistan']],
            ['en' => 'Islamic Studies', 'ur' => 'اسلامیات اختیاری', 'aliases' => ['Islamic Studies Elective', 'Islamiyat Elective']],
            ['en' => 'Punjabi', 'ur' => 'پنجابی'],
            ['en' => 'Persian', 'ur' => 'فارسی', 'aliases' => ['Farsi']],
            ['en' => 'Arabic', 'ur' => 'عربی'],
            ['en' => 'Statistics', 'ur' => 'شماریات'],
            ['en' => 'Philosophy', 'ur' => 'فلسفہ'],
            ['en' => 'Principles of Accounting', 'ur' => 'اصولِ حسابداری', 'aliases' => ['Accounting']],
            ['en' => 'Principles of Commerce', 'ur' => 'اصولِ تجارت', 'aliases' => ['Commerce']],
            ['en' => 'Principles of Economics', 'ur' => 'اصولِ معاشیات'],
            ['en' => 'Business Mathematics', 'ur' => 'کاروباری ریاضی'],
        ]);

        $this->applyChapters($grade, [
            'Civics' => [
                1 => ['en' => 'Introduction to Civics', 'ur' => 'علم شہریت کا تعارف'],
                2 => ['en' => 'State and Sovereignty', 'ur' => 'ریاست اور حاکمیت'],
                3 => ['en' => 'Forms of Government', 'ur' => 'حکومت کی اقسام'],
                4 => ['en' => 'Democracy', 'ur' => 'جمہوریت'],
                5 => ['en' => 'Citizenship', 'ur' => 'شہریت'],
                6 => ['en' => 'Rights and Duties', 'ur' => 'حقوق و فرائض'],
            ],
            'Education' => [
                1 => ['en' => 'Meaning and Aims of Education', 'ur' => 'تعلیم کا مفہوم اور مقاصد'],
                2 => ['en' => 'Philosophical Foundations of Education', 'ur' => 'تعلیم کی فلسفیانہ بنیادیں'],
                3 => ['en' => 'Psychological Foundations of Education', 'ur' => 'تعلیم کی نفسیاتی بنیادیں'],
                4 => ['en' => 'Sociological Foundations of Education', 'ur' => 'تعلیم کی عمرانی بنیادیں'],
                5 => ['en' => 'Agencies of Education', 'ur' => 'تعلیم کے ادارے'],
            ],
            'Economics' => [
                1 => ['en' => 'Nature and Scope of Economics', 'ur' => 'معاشیات کی نوعیت اور دائرہ کار'],
                2 => ['en' => 'Consumer Behaviour and Utility', 'ur' => 'صارف کا رویہ اور افادیت'],
                3 => ['en' => 'Demand and Elasticity', 'ur' => 'طلب اور لچک'],
                4 => ['en' => 'Supply and Cost of Production', 'ur' => 'سپلائی اور لاگتِ پیداوار'],
                5 => ['en' => 'Market and Price', 'ur' => 'بازار اور قیمت'],
                6 => ['en' => 'National Income', 'ur' => 'قومی آمدن'],
            ],
            'Psychology' => [
                1 => ['en' => 'Introduction to Psychology', 'ur' => 'نفسیات کا تعارف'],
                2 => ['en' => 'Methods of Psychology', 'ur' => 'نفسیات کے طریقے'],
                3 => ['en' => 'Nervous System and Behaviour', 'ur' => 'اعصابی نظام اور رویہ'],
                4 => ['en' => 'Sensation and Perception', 'ur' => 'احساس اور ادراک'],
                5 => ['en' => 'Learning', 'ur' => 'تعلّم'],
                6 => ['en' => 'Memory', 'ur' => 'حافظہ'],
                7 => ['en' => 'Motivation and Emotion', 'ur' => 'محرکات اور جذبات'],
            ],
            'Sociology' => [
                1 => ['en' => 'Introduction to Sociology', 'ur' => 'عمرانیات کا تعارف'],
                2 => ['en' => 'Society and Culture', 'ur' => 'معاشرہ اور ثقافت'],
                3 => ['en' => 'Social Groups', 'ur' => 'سماجی گروہ'],
                4 => ['en' => 'Socialization', 'ur' => 'سماجی تربیت'],
                5 => ['en' => 'Social Institutions', 'ur' => 'سماجی ادارے'],
                6 => ['en' => 'Social Change', 'ur' => 'سماجی تبدیلی'],
            ],
            'Geography' => [
                1 => ['en' => 'Introduction to Physical Geography', 'ur' => 'طبعی جغرافیہ کا تعارف'],
                2 => ['en' => 'The Earth as a Planet', 'ur' => 'زمین بطور سیارہ'],
                3 => ['en' => 'Rocks and Minerals', 'ur' => 'چٹانیں اور معدنیات'],
                4 => ['en' => 'Atmosphere and Weather', 'ur' => 'فضا اور موسم'],
                5 => ['en' => 'Oceans and Water Bodies', 'ur' => 'سمندر اور آبی ذخائر'],
                6 => ['en' => 'Landforms', 'ur' => 'زمینی اشکال'],
            ],
            'History' => [
                1 => ['en' => 'Sources of History', 'ur' => 'تاریخ کے ذرائع'],
                2 => ['en' => 'Muslim Rule in India', 'ur' => 'ہندوستان میں مسلمانوں کی حکومت'],
                3 => ['en' => 'Mughal Empire', 'ur' => 'مغلیہ سلطنت'],
                4 => ['en' => 'Decline of Muslim Power', 'ur' => 'مسلمانوں کے زوال'],
                5 => ['en' => 'Rise of British Power', 'ur' => 'برطانوی طاقت کا عروج'],
                6 => ['en' => 'Reform and Political Movements', 'ur' => 'اصلاحی اور سیاسی تحریکیں'],
            ],
            'Islamic Studies' => [
                1 => ['en' => 'Quran and Hadith (Selected)', 'ur' => 'قرآن و حدیث (منتخب)'],
                2 => ['en' => 'Beliefs (Aqaid)', 'ur' => 'عقائد'],
                3 => ['en' => 'Worship (Ibadat)', 'ur' => 'عبادات'],
                4 => ['en' => 'Seerah of the Holy Prophet (PBUH)', 'ur' => 'سیرت النبی ﷺ'],
                5 => ['en' => 'Islamic Ethics', 'ur' => 'اسلامی اخلاق'],
                6 => ['en' => 'Islamic History (Selected)', 'ur' => 'اسلامی تاریخ (منتخب)'],
            ],
            'Punjabi' => [
                1 => ['en' => 'Nasr / Prose', 'ur' => 'نثر'],
                2 => ['en' => 'Nazm / Poetry', 'ur' => 'نظم'],
                3 => ['en' => 'Ghazal', 'ur' => 'غزل'],
                4 => ['en' => 'Drama / Fiction', 'ur' => 'ڈرامہ / افسانہ'],
                5 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Persian' => [
                1 => ['en' => 'Selected Prose', 'ur' => 'منتخب نثر'],
                2 => ['en' => 'Selected Poetry', 'ur' => 'منتخب شاعری'],
                3 => ['en' => 'Grammar', 'ur' => 'قواعد'],
                4 => ['en' => 'Translation and Composition', 'ur' => 'ترجمہ و انشاء'],
            ],
            'Arabic' => [
                1 => ['en' => 'Selected Prose', 'ur' => 'منتخب نثر'],
                2 => ['en' => 'Selected Poetry', 'ur' => 'منتخب شاعری'],
                3 => ['en' => 'Grammar', 'ur' => 'قواعد'],
                4 => ['en' => 'Translation and Composition', 'ur' => 'ترجمہ و انشاء'],
            ],
            'Statistics' => [
                1 => ['en' => 'Introduction to Statistics', 'ur' => 'شماریات کا تعارف'],
                2 => ['en' => 'Collection and Presentation of Data', 'ur' => 'ڈیٹا جمع کرنا اور پیش کرنا'],
                3 => ['en' => 'Measuresures of Central Tendency', 'ur' => 'مرکزی رجحان کی پیمائش'],
                4 => ['en' => 'Measuresures of Dispersion', 'ur' => 'انحراف کی پیمائش'],
                5 => ['en' => 'Index Numbers', 'ur' => 'اشاریہ اعداد'],
                6 => ['en' => 'Probability (Basics)', 'ur' => 'احتمال (بنیادی)'],
            ],
            'Philosophy' => [
                1 => ['en' => 'Introduction to Philosophy', 'ur' => 'فلسفہ کا تعارف'],
                2 => ['en' => 'Logic', 'ur' => 'منطق'],
                3 => ['en' => 'Ethics', 'ur' => 'اخلاقیات'],
                4 => ['en' => 'Theory of Knowledge', 'ur' => 'علم کا نظریہ'],
                5 => ['en' => 'Metaphysics (Basics)', 'ur' => 'ما بعد الطبیعیات (بنیادی)'],
            ],
            'Principles of Accounting' => [
                1 => ['en' => 'Introduction to Accounting', 'ur' => 'حسابداری کا تعارف'],
                2 => ['en' => 'Business Transactions and Accounting Equation', 'ur' => 'کاروباری لین دین اور حسابداری مساوات'],
                3 => ['en' => 'Nature of Accounts and Rules of Debit and Credit', 'ur' => 'اکاؤنٹس کی نوعیت اور ڈیبٹ کریڈٹ کے قواعد'],
                4 => ['en' => 'Journal', 'ur' => 'جرنل'],
                5 => ['en' => 'Ledger and Trial Balance', 'ur' => 'لیجر اور ٹرائل بیلنس'],
                6 => ['en' => 'Bank and Banking Transactions', 'ur' => 'بینک اور بینکنگ لین دین'],
                7 => ['en' => 'Sub-Division of Journal for Cash Transactions', 'ur' => 'نقد لین دین کے لیے جرنل کی ذیلی تقسیم'],
                8 => ['en' => 'Sub-Division of Journal for Non-Cash Transactions', 'ur' => 'غیر نقد لین دین کے لیے جرنل کی ذیلی تقسیم'],
                9 => ['en' => 'Bank Reconciliation Statement', 'ur' => 'بینک مصالحتی گوشوارہ'],
                10 => ['en' => 'Bills of Exchange and Promissory Note', 'ur' => 'بل آف ایکسچینج اور پرومسری نوٹ'],
                11 => ['en' => 'Final Accounts (Basic)', 'ur' => 'حتمی حسابات (بنیادی)'],
                12 => ['en' => 'Final Accounts with Adjustments', 'ur' => 'ترمیمات کے ساتھ حتمی حسابات'],
                13 => ['en' => 'Capital and Revenue', 'ur' => 'سرمایہ اور محصول'],
                14 => ['en' => 'Rectification of Errors', 'ur' => 'غلطیوں کی درستگی'],
                15 => ['en' => 'Worksheet', 'ur' => 'ورک شیٹ'],
                16 => ['en' => 'Financial Statements', 'ur' => 'مالیاتی گوشوارے'],
            ],
            'Principles of Commerce' => [
                1 => ['en' => 'Introduction to Commerce', 'ur' => 'تجارت کا تعارف'],
                2 => ['en' => 'Trade – Home and Foreign', 'ur' => 'تجارت – داخلی اور بیرونی'],
                3 => ['en' => 'Aids to Trade', 'ur' => 'تجارت کے معاون'],
                4 => ['en' => 'Business Organization', 'ur' => 'کاروباری تنظیم'],
                5 => ['en' => 'Sole Proprietorship and Partnership', 'ur' => 'انفرادی کاروبار اور شراکت داری'],
                6 => ['en' => 'Joint Stock Company', 'ur' => 'جوائنٹ اسٹاک کمپنی'],
                7 => ['en' => 'Warehousing and Transport', 'ur' => 'گودام داری اور نقل و حمل'],
                8 => ['en' => 'Insurance', 'ur' => 'بیمہ'],
            ],
            'Principles of Economics' => [
                1 => ['en' => 'Nature and Scope of Economics', 'ur' => 'معاشیات کی نوعیت اور دائرہ کار'],
                2 => ['en' => 'Consumer Behaviour', 'ur' => 'صارف کا رویہ'],
                3 => ['en' => 'Demand', 'ur' => 'طلب'],
                4 => ['en' => 'Supply and Cost', 'ur' => 'سپلائی اور لاگت'],
                5 => ['en' => 'Market Structures', 'ur' => 'بازار کی اقسام'],
                6 => ['en' => 'National Income', 'ur' => 'قومی آمدن'],
                7 => ['en' => 'Money and Banking (Basics)', 'ur' => 'رقم اور بینکنگ (بنیادی)'],
            ],
            'Business Mathematics' => [
                1 => ['en' => 'Number Systems and Percentages', 'ur' => 'اعداد کے نظام اور فیصد'],
                2 => ['en' => 'Ratio, Proportion and Variation', 'ur' => 'تناسب، تناسب اور تغیر'],
                3 => ['en' => 'Equations and Inequalities', 'ur' => 'مساوات اور عدم مساوات'],
                4 => ['en' => 'Commercial Arithmetic', 'ur' => 'تجارتی حساب'],
                5 => ['en' => 'Simple and Compound Interest', 'ur' => 'سادہ اور مرکب سود'],
                6 => ['en' => 'Sequence and Series', 'ur' => 'سلسلے'],
                7 => ['en' => 'Matrices (Basics)', 'ur' => 'میٹرکس (بنیادی)'],
            ],
        ]);
    }

    protected function seedClass12(): void
    {
        $grade = Grade::query()->where('number', 12)->first();
        if (! $grade) {
            return;
        }

        $this->upsertSubjects($grade, [
            ['en' => 'Civics', 'ur' => 'شہریت'],
            ['en' => 'Education', 'ur' => 'تعلیم', 'aliases' => ['Ilm-ul-Taleem']],
            ['en' => 'Economics', 'ur' => 'معاشیات'],
            ['en' => 'Psychology', 'ur' => 'نفسیات'],
            ['en' => 'Sociology', 'ur' => 'عمرانیات'],
            ['en' => 'Geography', 'ur' => 'جغرافیہ'],
            ['en' => 'History', 'ur' => 'تاریخ', 'aliases' => ['History of Pakistan']],
            ['en' => 'Islamic Studies', 'ur' => 'اسلامیات اختیاری', 'aliases' => ['Islamic Studies Elective', 'Islamiyat Elective']],
            ['en' => 'Punjabi', 'ur' => 'پنجابی'],
            ['en' => 'Persian', 'ur' => 'فارسی', 'aliases' => ['Farsi']],
            ['en' => 'Arabic', 'ur' => 'عربی'],
            ['en' => 'Statistics', 'ur' => 'شماریات'],
            ['en' => 'Philosophy', 'ur' => 'فلسفہ'],
            ['en' => 'Principles of Accounting', 'ur' => 'اصولِ حسابداری', 'aliases' => ['Accounting']],
            ['en' => 'Banking', 'ur' => 'بینکنگ', 'aliases' => ['Principles of Banking']],
            ['en' => 'Commercial Geography', 'ur' => 'تجارتی جغرافیہ'],
            ['en' => 'Business Statistics', 'ur' => 'کاروباری شماریات', 'aliases' => ['Statistics for Commerce']],
            ['en' => 'Computer Studies', 'ur' => 'کمپیوٹر اسٹڈیز'],
        ]);

        $this->applyChapters($grade, [
            'Civics' => [
                1 => ['en' => 'Constitution of Pakistan', 'ur' => 'آئین پاکستان'],
                2 => ['en' => 'Federal System', 'ur' => 'وفاقی نظام'],
                3 => ['en' => 'Organs of Government', 'ur' => 'حکومت کے اعضاء'],
                4 => ['en' => 'Political Parties', 'ur' => 'سیاسی جماعتیں'],
                5 => ['en' => 'Local Government', 'ur' => 'مقامی حکومت'],
                6 => ['en' => 'Pakistan and International Relations', 'ur' => 'پاکستان اور بین الاقوامی تعلقات'],
            ],
            'Education' => [
                1 => ['en' => 'Curriculum Development', 'ur' => 'نصاب کی تیاری'],
                2 => ['en' => 'Methods of Teaching', 'ur' => 'تدریس کے طریقے'],
                3 => ['en' => 'Educational Administration', 'ur' => 'تعلیمی انتظام'],
                4 => ['en' => 'Guidance and Counselling', 'ur' => 'رہنمائی اور مشاورت'],
                5 => ['en' => 'Educational Problems in Pakistan', 'ur' => 'پاکستان میں تعلیمی مسائل'],
            ],
            'Economics' => [
                1 => ['en' => 'Money', 'ur' => 'رقم'],
                2 => ['en' => 'Banking', 'ur' => 'بینکنگ'],
                3 => ['en' => 'Public Finance', 'ur' => 'عوامی مالیات'],
                4 => ['en' => 'International Trade', 'ur' => 'بین الاقوامی تجارت'],
                5 => ['en' => 'Economic Development', 'ur' => 'معاشی ترقی'],
                6 => ['en' => 'Economy of Pakistan', 'ur' => 'پاکستان کی معیشت'],
            ],
            'Psychology' => [
                1 => ['en' => 'Intelligence', 'ur' => 'ذہانت'],
                2 => ['en' => 'Personality', 'ur' => 'شخصیت'],
                3 => ['en' => 'Thinking and Problem Solving', 'ur' => 'سوچ اور مسئلہ حل'],
                4 => ['en' => 'Social Psychology (Basics)', 'ur' => 'سماجی نفسیات (بنیادی)'],
                5 => ['en' => 'Mental Health', 'ur' => 'ذہنی صحت'],
                6 => ['en' => 'Applied Psychology', 'ur' => 'اطلاقی نفسیات'],
            ],
            'Sociology' => [
                1 => ['en' => 'Social Stratification', 'ur' => 'سماجی طبقہ بندی'],
                2 => ['en' => 'Social Control', 'ur' => 'سماجی کنٹرول'],
                3 => ['en' => 'Family and Marriage', 'ur' => 'خاندان اور شادی'],
                4 => ['en' => 'Religion and Society', 'ur' => 'مذہب اور معاشرہ'],
                5 => ['en' => 'Urban and Rural Society', 'ur' => 'شہری اور دیہی معاشرہ'],
                6 => ['en' => 'Social Problems of Pakistan', 'ur' => 'پاکستان کے سماجی مسائل'],
            ],
            'Geography' => [
                1 => ['en' => 'Human Geography – Introduction', 'ur' => 'انسانی جغرافیہ – تعارف'],
                2 => ['en' => 'Population', 'ur' => 'آبادی'],
                3 => ['en' => 'Settlements', 'ur' => 'آبادیاں'],
                4 => ['en' => 'Economic Activities', 'ur' => 'معاشی سرگرمیاں'],
                5 => ['en' => 'Geography of Pakistan', 'ur' => 'پاکستان کا جغرافیہ'],
                6 => ['en' => 'Regional Geography', 'ur' => 'علاقائی جغرافیہ'],
            ],
            'History' => [
                1 => ['en' => 'Pakistan Movement', 'ur' => 'تحریکِ پاکستان'],
                2 => ['en' => 'Establishment of Pakistan', 'ur' => 'قیام پاکستان'],
                3 => ['en' => 'Constitutional Development', 'ur' => 'آئینی ارتقا'],
                4 => ['en' => 'Foreign Policy of Pakistan', 'ur' => 'پاکستان کی خارجہ پالیسی'],
                5 => ['en' => 'Pakistan since 1971', 'ur' => '۱۹۷۱ء کے بعد پاکستان'],
                6 => ['en' => 'Contemporary Issues', 'ur' => 'عصری مسائل'],
            ],
            'Islamic Studies' => [
                1 => ['en' => 'Selected Quranic Surahs / Ayat', 'ur' => 'منتخب سورتیں / آیات'],
                2 => ['en' => 'Hadith (Selected)', 'ur' => 'احادیث (منتخب)'],
                3 => ['en' => 'Fiqh and Worship', 'ur' => 'فقہ اور عبادات'],
                4 => ['en' => 'Islamic Culture and Civilization', 'ur' => 'اسلامی تہذیب و تمدن'],
                5 => ['en' => 'Muslim Contribution to Knowledge', 'ur' => 'علم میں مسلمانوں کا حصہ'],
                6 => ['en' => 'Islam and Contemporary Issues', 'ur' => 'اسلام اور عصری مسائل'],
            ],
            'Punjabi' => [
                1 => ['en' => 'Nasr / Prose', 'ur' => 'نثر'],
                2 => ['en' => 'Nazm / Poetry', 'ur' => 'نظم'],
                3 => ['en' => 'Ghazal', 'ur' => 'غزل'],
                4 => ['en' => 'Drama / Fiction', 'ur' => 'ڈرامہ / افسانہ'],
                5 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Persian' => [
                1 => ['en' => 'Selected Prose', 'ur' => 'منتخب نثر'],
                2 => ['en' => 'Selected Poetry', 'ur' => 'منتخب شاعری'],
                3 => ['en' => 'Grammar', 'ur' => 'قواعد'],
                4 => ['en' => 'Translation and Composition', 'ur' => 'ترجمہ و انشاء'],
            ],
            'Arabic' => [
                1 => ['en' => 'Selected Prose', 'ur' => 'منتخب نثر'],
                2 => ['en' => 'Selected Poetry', 'ur' => 'منتخب شاعری'],
                3 => ['en' => 'Grammar', 'ur' => 'قواعد'],
                4 => ['en' => 'Translation and Composition', 'ur' => 'ترجمہ و انشاء'],
            ],
            'Statistics' => [
                1 => ['en' => 'Correlation', 'ur' => 'باہمی تعلق'],
                2 => ['en' => 'Regression', 'ur' => 'رجوع'],
                3 => ['en' => 'Probability Distributions', 'ur' => 'احتمالی تقسیم'],
                4 => ['en' => 'Sampling', 'ur' => 'نمونہ گیری'],
                5 => ['en' => 'Hypothesis Testing (Basics)', 'ur' => 'فرضیے کی جانچ (بنیادی)'],
                6 => ['en' => 'Time Series', 'ur' => 'وقت کے سلسلے'],
            ],
            'Philosophy' => [
                1 => ['en' => 'Muslim Philosophy', 'ur' => 'اسلامی فلسفہ'],
                2 => ['en' => 'Western Philosophy (Selected)', 'ur' => 'مغربی فلسفہ (منتخب)'],
                3 => ['en' => 'Ethics and Values', 'ur' => 'اخلاقیات اور اقدار'],
                4 => ['en' => 'Philosophy of Religion', 'ur' => 'مذہب کا فلسفہ'],
                5 => ['en' => 'Contemporary Philosophical Issues', 'ur' => 'عصری فلسفیانہ مسائل'],
            ],
            'Principles of Accounting' => [
                1 => ['en' => 'Partnership Accounts', 'ur' => 'شراکت داری کے حسابات'],
                2 => ['en' => 'Admission of a Partner', 'ur' => 'پارٹنر کا داخلہ'],
                3 => ['en' => 'Retirement and Death of a Partner', 'ur' => 'پارٹنر کی ریٹائرمنٹ اور وفات'],
                4 => ['en' => 'Dissolution of Partnership', 'ur' => 'شراکت داری کا خاتمہ'],
                5 => ['en' => 'Company Accounts – Share Capital', 'ur' => 'کمپنی اکاؤنٹس – شیئر سرمایہ'],
                6 => ['en' => 'Company Final Accounts', 'ur' => 'کمپنی کے حتمی حسابات'],
                7 => ['en' => 'Depreciation', 'ur' => 'فرسودگی'],
                8 => ['en' => 'Bills of Exchange (Advanced)', 'ur' => 'بل آف ایکسچینج (اعلیٰ)'],
                9 => ['en' => 'Consignment Accounts', 'ur' => 'کنسائنمنٹ اکاؤنٹس'],
                10 => ['en' => 'Joint Venture', 'ur' => 'جوائنٹ وینچر'],
            ],
            'Banking' => [
                1 => ['en' => 'Introduction to Banking', 'ur' => 'بینکنگ کا تعارف'],
                2 => ['en' => 'Types of Banks', 'ur' => 'بینکوں کی اقسام'],
                3 => ['en' => 'Banker and Customer', 'ur' => 'بینکر اور گاہک'],
                4 => ['en' => 'Cheques and Negotiable Instruments', 'ur' => 'چیک اور قابلِ انتقال دستاویزات'],
                5 => ['en' => 'Deposits and Advances', 'ur' => 'ڈپازٹس اور ایڈوانسز'],
                6 => ['en' => 'Central Banking', 'ur' => 'مرکزی بینکنگ'],
                7 => ['en' => 'Islamic Banking (Basics)', 'ur' => 'اسلامی بینکنگ (بنیادی)'],
            ],
            'Commercial Geography' => [
                1 => ['en' => 'Introduction to Commercial Geography', 'ur' => 'تجارتی جغرافیہ کا تعارف'],
                2 => ['en' => 'Natural Resources and Trade', 'ur' => 'قدرتی وسائل اور تجارت'],
                3 => ['en' => 'Agriculture and Commerce', 'ur' => 'زراعت اور تجارت'],
                4 => ['en' => 'Industries of Pakistan', 'ur' => 'پاکستان کی صنعتیں'],
                5 => ['en' => 'Transport and Communication', 'ur' => 'نقل و حمل اور مواصلات'],
                6 => ['en' => 'Foreign Trade of Pakistan', 'ur' => 'پاکستان کی بیرونی تجارت'],
            ],
            'Business Statistics' => [
                1 => ['en' => 'Introduction to Business Statistics', 'ur' => 'کاروباری شماریات کا تعارف'],
                2 => ['en' => 'Collection and Classification of Data', 'ur' => 'ڈیٹا جمع کرنا اور درجہ بندی'],
                3 => ['en' => 'Presentation of Data', 'ur' => 'ڈیٹا کی پیشکش'],
                4 => ['en' => 'Measuresures of Central Tendency', 'ur' => 'مرکزی رجحان کی پیمائش'],
                5 => ['en' => 'Measuresures of Dispersion', 'ur' => 'انحراف کی پیمائش'],
                6 => ['en' => 'Index Numbers', 'ur' => 'اشاریہ اعداد'],
                7 => ['en' => 'Time Series Analysis', 'ur' => 'وقت کے سلسلے کا تجزیہ'],
            ],
            'Computer Studies' => [
                1 => ['en' => 'Introduction to Computers', 'ur' => 'کمپیوٹر کا تعارف'],
                2 => ['en' => 'Hardware and Software', 'ur' => 'ہارڈویئر اور سافٹ ویئر'],
                3 => ['en' => 'Operating Systems', 'ur' => 'آپریٹنگ سسٹمز'],
                4 => ['en' => 'Office Applications', 'ur' => 'آفس ایپلیکیشنز'],
                5 => ['en' => 'Internet and E-Commerce', 'ur' => 'انٹرنیٹ اور ای کامرس'],
                6 => ['en' => 'Computer Security', 'ur' => 'کمپیوٹر سیکیورٹی'],
            ],
        ]);
    }
}
