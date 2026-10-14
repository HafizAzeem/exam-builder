<?php

namespace Tests\Unit\AIImport;

use App\Models\AIImport;
use App\Models\AISetting;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use App\Services\AIImport\GroqClient;
use App\Services\AIImport\Providers\GroqQuestionAiService;
use App\Services\PastPaperCollector\Providers\GroqWebSearchProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqClientTest extends TestCase
{
    use RefreshDatabase;

    protected function enableGroq(): AISetting
    {
        $settings = AISetting::current();
        $settings->update([
            'preferred_text_provider' => 'groq',
            'fallback_text_provider' => 'none',
            'groq_enabled' => true,
            'groq_api_key' => 'gsk_test_key_value',
            'groq_model' => 'openai/gpt-oss-20b',
            'retry_count' => 1,
        ]);

        return $settings->fresh();
    }

    public function test_chat_completions_are_posted_to_groq(): void
    {
        $this->enableGroq();

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"questions":[{"text_en":"What is force?","type":"short"}]}',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 12,
                    'completion_tokens' => 8,
                ],
            ], 200),
        ]);

        $result = app(GroqClient::class)->prompt('Extract questions as JSON.');

        $this->assertStringContainsString('What is force?', $result['content']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.groq.com')
                && str_contains($request->url(), '/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer gsk_test_key_value');
        });
    }

    public function test_generate_sends_json_schema_response_format(): void
    {
        $this->enableGroq();
        $user = User::factory()->create();
        $grade = Grade::query()->create(['number' => 9, 'label_en' => 'Class 9', 'label_ur' => null]);
        $subject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'English',
            'name_ur' => null,
            'sort_order' => 1,
        ]);
        $import = AIImport::query()->create([
            'user_id' => $user->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'book_type' => 'text_book',
            'language' => 'english',
            'original_filename' => 'generate',
            'stored_path' => 'pending',
            'status' => 'processing',
            'meta' => ['counts' => ['mcq' => 10, 'short' => 5, 'long' => 2]],
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['questions' => []]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        app(GroqQuestionAiService::class)->generateQuestions($import);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['response_format']['type'] ?? null) === 'json_schema'
                && ($data['response_format']['json_schema']['name'] ?? null) === 'exam_questions';
        });
    }

    public function test_question_ai_service_parses_json_questions(): void
    {
        $this->enableGroq();
        $user = User::factory()->create();
        $grade = Grade::query()->create(['number' => 9, 'label_en' => 'Class 9', 'label_ur' => null]);
        $subject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'Physics',
            'name_ur' => null,
            'sort_order' => 1,
        ]);
        $import = AIImport::query()->create([
            'user_id' => $user->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'book_type' => 'past_paper',
            'board' => 'Lahore Board',
            'year' => 2020,
            'session' => 'morning',
            'language' => 'english',
            'original_filename' => 'web-collection',
            'stored_path' => 'pending',
            'status' => 'processing',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'questions' => [
                                    ['text_en' => 'Define velocity.', 'type' => 'short'],
                                ],
                            ]),
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 4, 'completion_tokens' => 6],
            ], 200),
        ]);

        $result = app(GroqQuestionAiService::class)->extractQuestions($import, 'Q1. Define velocity.', 0);

        $this->assertCount(1, $result['questions']);
        $this->assertSame('Define velocity.', $result['questions'][0]['text_en']);
        $this->assertSame('success', $result['log']->status);
    }

    public function test_web_search_provider_parses_groq_results(): void
    {
        $this->enableGroq();

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'results' => [
                                    [
                                        'title' => 'Physics Paper',
                                        'url' => 'https://example.com/paper.pdf',
                                        'snippet' => 'BISE Lahore',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $results = app(GroqWebSearchProvider::class)->search('Lahore Board Physics', 5);

        $this->assertCount(1, $results);
        $this->assertSame('https://example.com/paper.pdf', $results[0]['url']);
        $this->assertSame('groq', app(GroqWebSearchProvider::class)->name());
    }

    public function test_decode_json_unwraps_question_list(): void
    {
        $decoded = app(GroqClient::class)->decodeJson('```json\n[{"text_en":"Q"}]\n```');

        $this->assertSame([['text_en' => 'Q']], $decoded['questions']);
    }
}
