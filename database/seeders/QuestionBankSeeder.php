<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\McqOption;
use App\Models\PastPaperTag;
use App\Models\Question;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class QuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        // Prevent bloating DB on repeated seeds.
        if (Question::query()->count() > 2000) {
            return;
        }

        $chapters = Chapter::query()->with('subject.grade')->get();

        foreach ($chapters as $chapter) {
            $this->seedForChapter($chapter);
        }
    }

    protected function seedForChapter(Chapter $chapter): void
    {
        $gradeNumber = (int) ($chapter->subject?->grade?->number ?? 0);
        $subjectName = (string) ($chapter->subject?->name_en ?? '');

        // Make higher grades slightly denser.
        $multiplier = $gradeNumber >= 9 ? 2 : 1;

        $this->seedMcqs($chapter, 8 * $multiplier);
        $this->seedShort($chapter, 5 * $multiplier);
        $this->seedLongWithParts($chapter, 2 * $multiplier);
        $this->seedFill($chapter, 4 * $multiplier, $subjectName);
        $this->seedTrueFalse($chapter, 4 * $multiplier);

        // Add a few past paper items per chapter for filters.
        $this->seedPastPaperMcqs($chapter, 2 * $multiplier);
    }

    protected function seedMcqs(Chapter $chapter, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $q = Question::query()->create([
                'chapter_id' => $chapter->id,
                'type' => 'mcq',
                'source' => Arr::random(['exercise', 'additional']),
                'text_en' => "MCQ {$i}: Choose the correct option for Chapter {$chapter->number}.",
                'text_ur' => "ایم سی کیو {$i}: باب {$chapter->number} کے لیے درست جواب منتخب کریں۔",
                'is_active' => true,
            ]);

            $correct = Arr::random(['a', 'b', 'c', 'd']);

            McqOption::query()->create([
                'question_id' => $q->id,
                'option_a_en' => 'Option A',
                'option_a_ur' => 'آپشن الف',
                'option_b_en' => 'Option B',
                'option_b_ur' => 'آپشن ب',
                'option_c_en' => 'Option C',
                'option_c_ur' => 'آپشن ج',
                'option_d_en' => 'Option D',
                'option_d_ur' => 'آپشن د',
                'correct_option' => $correct,
            ]);
        }
    }

    protected function seedShort(Chapter $chapter, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Question::query()->create([
                'chapter_id' => $chapter->id,
                'type' => 'short',
                'source' => Arr::random(['exercise', 'additional']),
                'text_en' => "Short {$i}: Write a brief answer related to Chapter {$chapter->number}.",
                'text_ur' => "مختصر {$i}: باب {$chapter->number} سے متعلق مختصر جواب لکھیں۔",
                'is_active' => true,
            ]);
        }
    }

    protected function seedLongWithParts(Chapter $chapter, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $parent = Question::query()->create([
                'chapter_id' => $chapter->id,
                'type' => 'long',
                'source' => Arr::random(['exercise', 'additional']),
                'text_en' => "Long {$i}: Answer the following in detail (parts a & b).",
                'text_ur' => "طویل {$i}: درج ذیل کا تفصیلی جواب دیں (حصے الف اور ب)۔",
                'has_parts' => true,
                'is_active' => true,
            ]);

            Question::query()->create([
                'chapter_id' => $chapter->id,
                'type' => 'long',
                'source' => $parent->source,
                'text_en' => "(a) Explain the first point for Long {$i}.",
                'text_ur' => "(الف) طویل {$i} کے پہلے نکتے کی وضاحت کریں۔",
                'parent_question_id' => $parent->id,
                'is_active' => true,
            ]);

            Question::query()->create([
                'chapter_id' => $chapter->id,
                'type' => 'long',
                'source' => $parent->source,
                'text_en' => "(b) Explain the second point for Long {$i}.",
                'text_ur' => "(ب) طویل {$i} کے دوسرے نکتے کی وضاحت کریں۔",
                'parent_question_id' => $parent->id,
                'is_active' => true,
            ]);
        }
    }

    protected function seedFill(Chapter $chapter, int $count, string $subjectName): void
    {
        $templates = [
            'Mathematics' => [
                'The value of ___ in the expression is ___.',
                'Solve ___ and write the answer ___.',
            ],
            'English' => [
                'Fill in the blank: She ___ to school every day.',
                'Fill in the blank: They are ___ than us.',
            ],
            'Urdu' => [
                'خالی جگہ پُر کریں: وہ روزانہ ___ جاتا ہے۔',
                'خالی جگہ پُر کریں: ہم ___ کے لیے محنت کرتے ہیں۔',
            ],
            'default' => [
                'Fill in the blank: ___ is an important concept in this chapter.',
                'Fill in the blank: The main idea is ___.',
            ],
        ];

        $list = $templates[$subjectName] ?? $templates['default'];

        for ($i = 1; $i <= $count; $i++) {
            $en = Arr::random($templates['default']);
            $ur = "خالی جگہ پُر کریں: ___ (باب {$chapter->number})";

            // If subject has explicit Urdu templates, use them.
            if ($subjectName === 'Urdu') {
                $ur = Arr::random($templates['Urdu']);
            }

            Question::query()->create([
                'chapter_id' => $chapter->id,
                'type' => 'fill',
                'source' => Arr::random(['exercise', 'additional']),
                'text_en' => $subjectName === 'Urdu' ? null : $en,
                'text_ur' => $ur,
                'is_active' => true,
            ]);
        }
    }

    protected function seedTrueFalse(Chapter $chapter, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Question::query()->create([
                'chapter_id' => $chapter->id,
                'type' => 'truefalse',
                'source' => Arr::random(['exercise', 'additional']),
                'text_en' => "T/F {$i}: Statement for Chapter {$chapter->number} (mark True or False).",
                'text_ur' => "درست/غلط {$i}: باب {$chapter->number} سے متعلق بیان (درست یا غلط نشان لگائیں)۔",
                'is_active' => true,
            ]);
        }
    }

    protected function seedPastPaperMcqs(Chapter $chapter, int $count): void
    {
        $boards = ['Lahore Board', 'Gujranwala Board', 'Faisalabad Board', 'Sargodha Board', 'Rawalpindi Board'];
        $years = [2018, 2019, 2020, 2021, 2022, 2023];
        $sessions = ['morning', 'evening'];

        for ($i = 1; $i <= $count; $i++) {
            $q = Question::query()->create([
                'chapter_id' => $chapter->id,
                'type' => 'mcq',
                'source' => 'past_paper',
                'text_en' => "Past Paper MCQ {$i}: Board-style question for Chapter {$chapter->number}.",
                'text_ur' => "گزشتہ پرچہ ایم سی کیو {$i}: باب {$chapter->number} سے متعلق بورڈ طرز سوال۔",
                'is_active' => true,
            ]);

            McqOption::query()->create([
                'question_id' => $q->id,
                'option_a_en' => 'A',
                'option_a_ur' => 'الف',
                'option_b_en' => 'B',
                'option_b_ur' => 'ب',
                'option_c_en' => 'C',
                'option_c_ur' => 'ج',
                'option_d_en' => 'D',
                'option_d_ur' => 'د',
                'correct_option' => Arr::random(['a', 'b', 'c', 'd']),
            ]);

            PastPaperTag::query()->create([
                'question_id' => $q->id,
                'board_name' => Arr::random($boards),
                'year' => Arr::random($years),
                'session' => Arr::random($sessions),
            ]);
        }
    }
}

