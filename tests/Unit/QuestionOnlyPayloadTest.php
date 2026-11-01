<?php

namespace Tests\Unit;

use App\Services\QuestionAssistant\QuestionOnlyPayload;
use PHPUnit\Framework\TestCase;

class QuestionOnlyPayloadTest extends TestCase
{
    public function test_only_source_questions_and_choices_survive_without_answers(): void
    {
        $source = "What is force?\nAnswer: A push or pull.\nChoose the SI unit of force.\nA. Newton B. Joule C. Watt D. Pascal";
        $raw = [['type' => 'short', 'text_en' => "What is force?\nAnswer: A push or pull.", 'answer' => 'A push', 'explanation' => 'Explanation'], ['type' => 'short', 'text_en' => 'Invented question absent from source'], ['type' => 'mcq', 'text_en' => 'Choose the SI unit of force.', 'mcq_options' => ['option_a_en' => 'Newton', 'option_b_en' => 'Joule', 'option_c_en' => 'Watt', 'option_d_en' => 'Pascal', 'correct_option' => 'a']]];
        $questions = (new QuestionOnlyPayload)->filter($raw, [['text' => $source, 'url' => 'https://board.example/paper', 'published_at' => null]], 30);
        $this->assertCount(2, $questions);
        $this->assertSame('What is force?', $questions[0]['text_en']);
        $this->assertArrayNotHasKey('answer', $questions[0]);
        $this->assertArrayNotHasKey('correct_option', $questions[1]['mcq_options']);
        $this->assertSame('https://board.example/paper', $questions[0]['source_url']);
        $this->assertNull($questions[0]['source_published_at']);
    }

    public function test_inline_multilingual_answer_labels_and_alternate_separators_are_removed(): void
    {
        $guard = new QuestionOnlyPayload;
        $this->assertSame('سوال کیا ہے؟', $guard->text('سوال کیا ہے؟ جواب: درست جواب'));
        $this->assertSame('What is force?', $guard->text('What is force? Answer = a push'));
        $this->assertSame('What is force?', $guard->text('What is force? Solution — a push'));
    }

    public function test_source_verification_does_not_ignore_math_operators_decimals_or_variable_case(): void
    {
        $guard = new QuestionOnlyPayload;
        foreach ([['Solve x+2=3', 'Solve x-2=3'], ['Round 05', 'Round 0.5'], ['Prove x<2', 'Prove x>2'], ['Find X', 'Find x']] as [$question,$source]) {
            $this->assertSame([], $guard->filter([['type' => 'short', 'text_en' => $question]], [['text' => $source]], 30));
        }
        $this->assertCount(1, $guard->filter([['type' => 'short', 'text_en' => 'Solve x + 2 = 3']], [['text' => 'Solve x+2=3']], 30));
    }
}
