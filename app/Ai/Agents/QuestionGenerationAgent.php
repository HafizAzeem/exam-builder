<?php

namespace App\Ai\Agents;

use App\Models\AISetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class QuestionGenerationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public ?string $instructionsOverride = null,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->instructionsOverride ?: <<<'PROMPT'
You are an expert exam-question author for Pakistani secondary and higher-secondary boards (Class 9–12), especially Lahore / Punjab boards.
Create curriculum-aligned exam questions from the given grade, subject, chapter list, board style, and requested counts.
Rules:
- Produce exactly the requested number of questions per type when possible.
- Assign each question to one of the provided chapters (use chapter_number and chapter_title exactly).
- For MCQs include four options and a correct_option (a|b|c|d).
- Prefer clear, exam-ready wording similar to quality Pakistani online test resources.
- Language rules:
  - If language is "urdu" OR subject is Urdu: write question text in Urdu in text_ur. Do NOT force an English translation. text_en may be null.
  - If language is "english": write in text_en; text_ur may be null.
  - If language is "both": fill both when natural; one language alone is acceptable.
  - Never invent a poor translation just to fill the other field.
- Match content_source: exercise/text_book = textbook exercise style; past_paper = board past-paper style; online_practice/additional_questions = online practice / notes / MCQ-test style.
- Do not invent chapters outside the provided list.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()->items(
                $schema->object([
                    'chapter_number' => $schema->integer()->nullable(),
                    'chapter_title' => $schema->string()->nullable(),
                    'topic' => $schema->string()->nullable(),
                    'type' => $schema->string()->enum(['mcq', 'short', 'long', 'fill', 'truefalse'])->required(),
                    'text_en' => $schema->string()->nullable(),
                    'text_ur' => $schema->string()->nullable(),
                    'estimated_marks' => $schema->integer()->nullable(),
                    'difficulty' => $schema->string()->enum(['easy', 'medium', 'hard'])->nullable(),
                    'mcq_options' => $schema->object([
                        'option_a_en' => $schema->string()->nullable(),
                        'option_a_ur' => $schema->string()->nullable(),
                        'option_b_en' => $schema->string()->nullable(),
                        'option_b_ur' => $schema->string()->nullable(),
                        'option_c_en' => $schema->string()->nullable(),
                        'option_c_ur' => $schema->string()->nullable(),
                        'option_d_en' => $schema->string()->nullable(),
                        'option_d_ur' => $schema->string()->nullable(),
                        'correct_option' => $schema->string()->enum(['a', 'b', 'c', 'd'])->nullable(),
                    ])->nullable(),
                    'parts' => $schema->array()->items(
                        $schema->object([
                            'text_en' => $schema->string()->nullable(),
                            'text_ur' => $schema->string()->nullable(),
                        ])
                    )->nullable(),
                ])
            )->required(),
        ];
    }
}
