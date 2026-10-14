<?php

namespace Tests\Unit\AIImport;

use App\Services\AIImport\QuestionParserService;
use Tests\TestCase;

class QuestionParserServiceTest extends TestCase
{
    protected function parser(): QuestionParserService
    {
        return app(QuestionParserService::class);
    }

    public function test_mcq_without_options_becomes_fill(): void
    {
        $prepared = $this->parser()->prepareRawQuestion([
            'type' => 'mcq',
            'text_en' => '"Steadfast" means:',
        ]);

        $this->assertNotNull($prepared);
        $this->assertSame('fill', $prepared['type']);
        $this->assertNull($prepared['mcq_options']);
    }

    public function test_mcq_list_options_are_mapped(): void
    {
        $prepared = $this->parser()->prepareRawQuestion([
            'type' => 'mcq',
            'question' => '"Steadfast" means:',
            'options' => ['firm', 'weak', 'angry', 'silent'],
            'correct_option' => 'a',
        ]);

        $this->assertSame('mcq', $prepared['type']);
        $this->assertSame('firm', $prepared['mcq_options']['option_a_en']);
        $this->assertSame('silent', $prepared['mcq_options']['option_d_en']);
        $this->assertSame('"Steadfast" means:', $prepared['text_en']);
    }

    public function test_take_requested_counts_caps_extra_mcqs(): void
    {
        $raw = [];
        for ($i = 1; $i <= 27; $i++) {
            $raw[] = [
                'type' => 'mcq',
                'text_en' => "Q{$i}",
                'mcq_options' => [
                    'option_a_en' => 'A',
                    'option_b_en' => 'B',
                    'option_c_en' => 'C',
                    'option_d_en' => 'D',
                    'correct_option' => 'a',
                ],
            ];
        }
        $raw[] = ['type' => 'short', 'text_en' => 'Short 1'];
        $raw[] = ['type' => 'short', 'text_en' => 'Short 2'];
        $raw[] = ['type' => 'short', 'text_en' => 'Short extra'];
        $raw[] = ['type' => 'long', 'text_en' => 'Long 1'];
        $raw[] = ['type' => 'long', 'text_en' => 'Long 2'];

        $kept = $this->parser()->takeRequestedCounts($raw, [
            'mcq' => 10,
            'short' => 5,
            'long' => 2,
            'fill' => 0,
            'truefalse' => 0,
        ]);

        $types = array_count_values(array_column($kept, 'type'));
        $this->assertSame(10, $types['mcq'] ?? 0);
        $this->assertSame(3, $types['short'] ?? 0);
        $this->assertSame(2, $types['long'] ?? 0);
        $this->assertCount(15, $kept);
    }

    public function test_groq_response_format_is_json_schema(): void
    {
        $format = \App\Services\AIImport\QuestionJsonSchema::groqResponseFormat();
        $this->assertSame('json_schema', $format['type']);
        $this->assertSame('exam_questions', $format['json_schema']['name']);
        $this->assertTrue($format['json_schema']['strict']);
        $this->assertSame(['questions'], $format['json_schema']['schema']['required']);
    }
}
