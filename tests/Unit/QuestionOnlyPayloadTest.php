<?php

namespace Tests\Unit;

use App\Services\QuestionAssistant\QuestionOnlyPayload;
use PHPUnit\Framework\TestCase;

class QuestionOnlyPayloadTest extends TestCase
{
    public function test_only_source_questions_and_choices_survive_without_answers(): void
    {
        $source="What is force?\nAnswer: A push or pull.\nChoose the SI unit of force.\nA. Newton B. Joule C. Watt D. Pascal";
        $raw=[['type'=>'short','text_en'=>"What is force?\nAnswer: A push or pull.",'answer'=>'A push','explanation'=>'Explanation'],['type'=>'short','text_en'=>'Invented question absent from source'],['type'=>'mcq','text_en'=>'Choose the SI unit of force.','mcq_options'=>['option_a_en'=>'Newton','option_b_en'=>'Joule','option_c_en'=>'Watt','option_d_en'=>'Pascal','correct_option'=>'a']]];
        $questions=(new QuestionOnlyPayload)->filter($raw,[['text'=>$source,'url'=>'https://board.example/paper','published_at'=>null]],30);
        $this->assertCount(2,$questions);
        $this->assertSame('What is force?',$questions[0]['text_en']);
        $this->assertArrayNotHasKey('answer',$questions[0]);
        $this->assertArrayNotHasKey('correct_option',$questions[1]['mcq_options']);
        $this->assertSame('https://board.example/paper',$questions[0]['source_url']);
        $this->assertNull($questions[0]['source_published_at']);
    }
}
