<?php

namespace Database\Seeders;

use App\Models\Grade;
use Database\Seeders\Concerns\SeedsSubjectChapters;
use Illuminate\Database\Seeder;

/**
 * Class 9 subjects + real chapter titles (Punjab / Lahore Board, PCTB–PECTAA 2025–26).
 */
class Class9PunjabChaptersSeeder extends Seeder
{
    use SeedsSubjectChapters;

    public function run(): void
    {
        $grade = Grade::query()->where('number', 9)->first();
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
            ['en' => 'Computer Science', 'ur' => 'کمپیوٹر سائنس', 'aliases' => ['Computer', 'Computer Science & Entrepreneurship']],
            ['en' => 'Tarjuma-tul-Quran', 'ur' => 'ترجمۃ القرآن', 'aliases' => ['Tarjuma Tul Quran']],
        ]);

        $map = [
            'English' => [
                1 => ['en' => 'The Saviour of Mankind', 'ur' => 'انسانیت کا نجات دہندہ'],
                2 => ['en' => 'Patriotism', 'ur' => 'محب وطنی'],
                3 => ['en' => 'Media and Its Impact', 'ur' => 'میڈیا اور اس کے اثرات'],
                4 => ['en' => 'Hazrat Asma (R.A.)', 'ur' => 'حضرت اسماءؓ'],
                5 => ['en' => 'Daffodils', 'ur' => 'ڈافوڈلز'],
                6 => ['en' => 'The Value of Time', 'ur' => 'وقت کی اہمیت'],
                7 => ['en' => 'If', 'ur' => 'اگر'],
                8 => ['en' => 'The Impact of Globalisation on Culture and Economy', 'ur' => 'عالمگیریت کے ثقافتی و معاشی اثرات'],
                9 => ['en' => 'Quality Education: A Key to Success', 'ur' => 'معیاری تعلیم: کامیابی کی کنجی'],
                10 => ['en' => 'The Silent Predator and the Majestic Prey – Snow Leopard and Markhor', 'ur' => 'برفانی تیندوا اور مارخور'],
                11 => ['en' => 'The Dear Departed', 'ur' => 'دی ڈیئر ڈیپارٹڈ'],
                12 => ['en' => 'Women Empowerment through Entrepreneurship', 'ur' => 'کاروبار کے ذریعے خواتین کو بااختیار بنانا'],
            ],
            'Urdu' => [
                1 => ['en' => 'Hamd', 'ur' => 'حمد'],
                2 => ['en' => 'Naat', 'ur' => 'نعت'],
                3 => ['en' => 'Quaid-e-Azam Muhammad Ali Jinnah', 'ur' => 'قائد اعظم محمد علی جناح'],
                4 => ['en' => 'Allama Iqbal', 'ur' => 'علامہ اقبال'],
                5 => ['en' => 'Nasr / Prose Lesson 1', 'ur' => 'نثر سبق ۱'],
                6 => ['en' => 'Nasr / Prose Lesson 2', 'ur' => 'نثر سبق ۲'],
                7 => ['en' => 'Ghazal', 'ur' => 'غزل'],
                8 => ['en' => 'Nazm', 'ur' => 'نظم'],
                9 => ['en' => 'Drama / Play', 'ur' => 'ڈرامہ'],
                10 => ['en' => 'Grammar and Composition', 'ur' => 'قواعد و انشاء'],
            ],
            'Islamiyat' => [
                1 => ['en' => 'Surah Al-Fatihah and Selected Ayat', 'ur' => 'سورۃ الفاتحہ اور منتخب آیات'],
                2 => ['en' => 'Hadith', 'ur' => 'احادیث'],
                3 => ['en' => 'Articles of Faith (Imaniyat)', 'ur' => 'ایمانیات'],
                4 => ['en' => 'Worship (Ibadat)', 'ur' => 'عبادات'],
                5 => ['en' => 'Seerah of the Holy Prophet (PBUH)', 'ur' => 'سیرت النبی ﷺ'],
                6 => ['en' => 'Islamic Ethics and Character', 'ur' => 'اسلامی اخلاق و کردار'],
                7 => ['en' => 'Rights and Social Responsibilities', 'ur' => 'حقوق و فرائض'],
            ],
            'Pakistan Studies' => [
                1 => ['en' => 'Ideological Basis of Pakistan', 'ur' => 'پاکستان کی نظریاتی بنیاد'],
                2 => ['en' => 'Making of Pakistan', 'ur' => 'قیام پاکستان'],
                3 => ['en' => 'Land and Environment of Pakistan', 'ur' => 'پاکستان کا جغرافیہ اور ماحول'],
                4 => ['en' => 'History of Pakistan (Early Years)', 'ur' => 'پاکستان کی تاریخ (ابتدائی دور)'],
                5 => ['en' => 'Culture and Society of Pakistan', 'ur' => 'پاکستانی ثقافت و معاشرت'],
            ],
            'Mathematics' => [
                1 => ['en' => 'Real Numbers', 'ur' => 'حقیقی اعداد'],
                2 => ['en' => 'Logarithms', 'ur' => 'لوگارتھم'],
                3 => ['en' => 'Sets and Functions', 'ur' => 'مجموعے اور تفاعل'],
                4 => ['en' => 'Factorization and Algebraic Manipulation', 'ur' => 'تجزیہ اور الجبرائی عمل'],
                5 => ['en' => 'Linear Equations and Inequalities', 'ur' => 'خطی مساوات اور عدم مساوات'],
                6 => ['en' => 'Trigonometry', 'ur' => 'مثلثیات'],
                7 => ['en' => 'Coordinate Geometry', 'ur' => 'مختصاتی ہندسہ'],
                8 => ['en' => 'Logic', 'ur' => 'منطق'],
                9 => ['en' => 'Similar Figures', 'ur' => 'مشابہ اشکال'],
                10 => ['en' => 'Graphs of Functions', 'ur' => 'تفاعل کے خط'],
                11 => ['en' => 'Loci and Construction', 'ur' => 'محل وقوع و تعمیر'],
                12 => ['en' => 'Information Handling', 'ur' => 'معلومات کا انتظام'],
                13 => ['en' => 'Probability', 'ur' => 'احتمال'],
            ],
            'Physics' => [
                1 => ['en' => 'Physical Quantities and Measurements', 'ur' => 'طبیعی مقداریں اور پیمائش'],
                2 => ['en' => 'Kinematics', 'ur' => 'حرکت شناسی'],
                3 => ['en' => 'Dynamics', 'ur' => 'حرکت کی دینامیات'],
                4 => ['en' => 'Turning Effect of Forces', 'ur' => 'قوتوں کا مداری اثر'],
                5 => ['en' => 'Work and Energy', 'ur' => 'کام اور توانائی'],
                6 => ['en' => 'Mechanical Properties of Matter', 'ur' => 'مادے کی میکانی خصوصیات'],
                7 => ['en' => 'Thermal Properties of Matter', 'ur' => 'مادے کی حرارتی خصوصیات'],
                8 => ['en' => 'Magnetism', 'ur' => 'مقناطیسیت'],
                9 => ['en' => 'Nature of Science', 'ur' => 'سائنس کی نوعیت'],
            ],
            'Chemistry' => [
                1 => ['en' => 'Fundamentals of Chemistry', 'ur' => 'کیمسٹری کی بنیادی باتیں'],
                2 => ['en' => 'Structure of Atoms', 'ur' => 'ایٹم کی ساخت'],
                3 => ['en' => 'Periodic Table and Periodicity', 'ur' => 'دوری جدول اور دوری خصوصیات'],
                4 => ['en' => 'Structure of Molecules / Chemical Bonding', 'ur' => 'مالیکیولز کی ساخت / کیمیائی بندش'],
                5 => ['en' => 'Physical States of Matter', 'ur' => 'مادے کی طبیعی حالتیں'],
                6 => ['en' => 'Solutions', 'ur' => 'محلول'],
                7 => ['en' => 'Electrochemistry', 'ur' => 'برق کیمیا'],
                8 => ['en' => 'Chemical Reactivity', 'ur' => 'کیمیائی فعالیت'],
            ],
            'Biology' => [
                1 => ['en' => 'Introduction to Biology', 'ur' => 'حیاتیات کا تعارف'],
                2 => ['en' => 'Solving Biological Problems', 'ur' => 'حیاتیاتی مسائل کا حل'],
                3 => ['en' => 'Biodiversity', 'ur' => 'حیاتیاتی تنوع'],
                4 => ['en' => 'Cells and Tissues', 'ur' => 'خلیے اور بافتیں'],
                5 => ['en' => 'Cell Cycle', 'ur' => 'خلوی دور'],
                6 => ['en' => 'Enzymes', 'ur' => 'انزائمز'],
                7 => ['en' => 'Bioenergetics', 'ur' => 'حیاتی توانائیات'],
                8 => ['en' => 'Nutrition', 'ur' => 'تغذیہ'],
                9 => ['en' => 'Transport', 'ur' => 'نقل و حمل'],
            ],
            'Computer Science' => [
                1 => ['en' => 'Problem Solving', 'ur' => 'مسئلہ حل کرنا'],
                2 => ['en' => 'Binary Systems', 'ur' => 'ثنائی نظام'],
                3 => ['en' => 'Networks', 'ur' => 'نیٹ ورکس'],
                4 => ['en' => 'Data and Information', 'ur' => 'ڈیٹا اور معلومات'],
                5 => ['en' => 'Computer Hardware', 'ur' => 'کمپیوٹر ہارڈویئر'],
                6 => ['en' => 'Software', 'ur' => 'سافٹ ویئر'],
                7 => ['en' => 'Introduction to Entrepreneurship', 'ur' => 'کاروباریت کا تعارف'],
            ],
            'Tarjuma-tul-Quran' => [
                1 => ['en' => 'Surah Al-Fatihah', 'ur' => 'سورۃ الفاتحہ'],
                2 => ['en' => 'Selected Ayat – Belief and Worship', 'ur' => 'منتخب آیات – ایمان و عبادت'],
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
