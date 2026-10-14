<?php

namespace App\Services\AIImport;

/**
 * Structured output contract shared with Gemini's QuestionGenerationAgent.
 */
class QuestionJsonSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableInt = ['type' => ['integer', 'null']];

        $mcqOptions = [
            'type' => ['object', 'null'],
            'additionalProperties' => false,
            'properties' => [
                'option_a_en' => $nullableString,
                'option_a_ur' => $nullableString,
                'option_b_en' => $nullableString,
                'option_b_ur' => $nullableString,
                'option_c_en' => $nullableString,
                'option_c_ur' => $nullableString,
                'option_d_en' => $nullableString,
                'option_d_ur' => $nullableString,
                'correct_option' => ['type' => ['string', 'null'], 'enum' => ['a', 'b', 'c', 'd', null]],
            ],
            'required' => [
                'option_a_en', 'option_a_ur',
                'option_b_en', 'option_b_ur',
                'option_c_en', 'option_c_ur',
                'option_d_en', 'option_d_ur',
                'correct_option',
            ],
        ];

        $question = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'chapter_number' => $nullableInt,
                'chapter_title' => $nullableString,
                'topic' => $nullableString,
                'type' => ['type' => 'string', 'enum' => ['mcq', 'short', 'long', 'fill', 'truefalse']],
                'text_en' => $nullableString,
                'text_ur' => $nullableString,
                'estimated_marks' => $nullableInt,
                'difficulty' => ['type' => ['string', 'null'], 'enum' => ['easy', 'medium', 'hard', null]],
                'mcq_options' => $mcqOptions,
                'parts' => [
                    'type' => ['array', 'null'],
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'text_en' => $nullableString,
                            'text_ur' => $nullableString,
                        ],
                        'required' => ['text_en', 'text_ur'],
                    ],
                ],
            ],
            'required' => [
                'chapter_number',
                'chapter_title',
                'topic',
                'type',
                'text_en',
                'text_ur',
                'estimated_marks',
                'difficulty',
                'mcq_options',
                'parts',
            ],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => $question,
                ],
            ],
            'required' => ['questions'],
        ];
    }

    /**
     * Groq OpenAI-compatible structured output payload.
     *
     * @return array{type:string, json_schema: array<string, mixed>}
     */
    public static function groqResponseFormat(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'exam_questions',
                'strict' => true,
                'schema' => self::schema(),
            ],
        ];
    }

    public static function systemInstructions(): string
    {
        return <<<'PROMPT'
You are an expert exam-question author for Pakistani boards (Class 9–12), especially Lahore / Punjab.

Return ONLY JSON matching the exam_questions schema: {"questions":[...]}.

Hard rules:
- Honour requested_counts EXACTLY. If mcq=10, short=5, long=2, the array must contain 10+5+2 items and no extras.
- type must be one of: mcq, short, long, fill, truefalse.
- Each item needs text_en and/or text_ur.
- MCQ items MUST include mcq_options with four distinct real choices (option_a_en…option_d_en or Urdu equivalents) and correct_option a|b|c|d.
- Never emit an MCQ stem without four options. Vocabulary "X means:" questions are MCQs with four word choices, not blanks.
- short/long/fill/truefalse must set mcq_options to null.
- Do not invent chapters outside the provided list.
PROMPT;
    }
}
