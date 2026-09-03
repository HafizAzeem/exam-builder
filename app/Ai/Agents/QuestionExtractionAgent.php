<?php

namespace App\Ai\Agents;

use App\Models\AISetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class QuestionExtractionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public ?string $instructionsOverride = null,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->instructionsOverride
            ?: AISetting::current()->prompt_template
            ?: AISetting::defaultPromptTemplate();
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
