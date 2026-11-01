<?php

namespace Tests\Feature;

use App\Jobs\ProcessQuestionAssistantJob;
use App\Models\AIImport;
use App\Models\AISetting;
use App\Models\AssistantRequest;
use App\Models\Board;
use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\PastPaperCollector\UrlSafetyService;
use App\Services\QuestionAssistant\AssistantAiExtractor;
use App\Services\QuestionAssistant\AssistantBudgetService;
use App\Services\QuestionAssistant\AssistantSourceReader;
use App\Services\QuestionAssistant\QuestionAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuestionAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        Queue::fake();
        Storage::fake('local');
        config(['filesystems.ai_import_disk' => 'local']);
        Http::preventStrayRequests();
        AISetting::current()->update(['enable_queue' => true]);
        $board = Board::create(['name' => 'School Board', 'is_active' => true]);
        $school = Institution::create(['name' => 'School', 'board_id' => $board->id, 'expiry_date' => now()->addYear(), 'access_expires_at' => now()->addYear()]);
        $user = User::factory()->create(['institution_id' => $school->id, 'is_active' => true]);
        $user->assignRole(Role::findOrCreate('institution_admin', 'web'));
        $grade = Grade::create(['number' => 9, 'label_en' => 'Class 9', 'is_active' => true]);
        $subject = Subject::create(['grade_id' => $grade->id, 'name_en' => 'Physics', 'is_active' => true]);
        $chapter = Chapter::create(['subject_id' => $subject->id, 'number' => 1, 'title_en' => 'Motion', 'is_active' => true]);
        $data = ['grade_id' => $grade->id, 'subject_id' => $subject->id, 'chapter_ids' => [$chapter->id], 'book_type' => 'additional_questions', 'language' => 'english', 'mode' => 'format', 'question_count' => 10, 'request_key' => (string) Str::uuid(), 'raw_text' => 'What is force?'];
        $this->actingAs($user);

        return compact('user', 'school', 'grade', 'subject', 'chapter', 'data');
    }

    public function test_one_entry_defaults_to_school_board_and_legacy_posts_cannot_bypass_budget(): void
    {
        extract($this->context());
        $query = ['grade' => $grade->id, 'subject' => $subject->id, 'chapters' => $chapter->id];
        $this->get(route('builder.ai.paste', $query))->assertOk()->assertInertia(fn (Assert $p) => $p->component('PaperBuilder/AI/Paste')->where('defaultBoard', 'School Board')->where('assistantUsage.remaining', 20));
        $this->get(route('builder.ai.generate', $query))->assertRedirect(route('builder.ai.paste', $query));
        $this->post(route('builder.ai.generate.store'), $data)->assertStatus(410);
        $this->post(route('builder.ai.extract-past-paper.store'), $data)->assertStatus(410);
        $this->assertDatabaseCount('assistant_requests', 0);
    }

    public function test_invalid_limits_spend_nothing_and_repeated_submission_is_idempotent(): void
    {
        extract($this->context());
        $this->post(route('builder.ai.paste.store'), [...$data, 'question_count' => 31])->assertSessionHasErrors('question_count');
        $this->post(route('builder.ai.paste.store'), [...$data, 'raw_text' => str_repeat('x', 20001)])->assertSessionHasErrors('raw_text');
        $this->assertDatabaseCount('assistant_requests', 0);
        $this->post(route('builder.ai.paste.store'), $data)->assertRedirect();
        $this->post(route('builder.ai.paste.store'), $data)->assertRedirect();
        $this->assertDatabaseCount('assistant_requests', 1);
        $this->assertDatabaseCount('ai_imports', 1);
        Queue::assertPushed(ProcessQuestionAssistantJob::class, 1);
    }

    public function test_format_has_no_web_calls_and_inserts_questions_without_answer_keys(): void
    {
        extract($this->context());
        $text = 'Choose the SI unit of force. A. Newton B. Joule C. Watt D. Pascal';
        $this->post(route('builder.ai.paste.store'), [...$data, 'raw_text' => $text])->assertRedirect();
        $this->mock(AssistantSourceReader::class)->shouldNotReceive('sources');
        $this->mock(AssistantAiExtractor::class)->shouldReceive('extract')->once()->andReturn([['type' => 'mcq', 'text_en' => 'Choose the SI unit of force.', 'mcq_options' => ['option_a_en' => 'Newton', 'option_b_en' => 'Joule', 'option_c_en' => 'Watt', 'option_d_en' => 'Pascal', 'correct_option' => 'a'], 'answer' => 'Newton']]);
        $row = AssistantRequest::firstOrFail();
        app(QuestionAssistantService::class)->process($row);
        $import = AIImport::firstOrFail();
        $staged = $import->questions()->firstOrFail();
        $this->assertSame('completed', $row->fresh()->status);
        $this->assertArrayNotHasKey('correct_option', $staged->mcq_options);
        $this->assertArrayNotHasKey('answer', $staged->raw_payload);
        $this->get(route('builder.ai.review', $import))->assertOk()->assertInertia(fn (Assert $p) => $p->where('assistantRequest.status', 'completed')->missing('questions.0.mcq_options.correct_option'));
        $this->post(route('builder.ai.add-to-paper', $import), ['staging_ids' => [$staged->id]])->assertRedirect();
        $question = Question::firstOrFail();
        $this->assertNull($question->mcqOptions->correct_option);
        $this->assertSame(19, app(AssistantBudgetService::class)->summary($school)['remaining']);
    }

    public function test_canceled_request_never_runs_or_publishes_and_is_refunded(): void
    {
        extract($this->context());
        $this->post(route('builder.ai.paste.store'), $data)->assertRedirect();
        $row = AssistantRequest::firstOrFail();
        $import = AIImport::firstOrFail();
        $this->post(route('builder.ai.cancel', $import))->assertRedirect();
        $this->mock(AssistantAiExtractor::class)->shouldNotReceive('extract');
        app(QuestionAssistantService::class)->process($row);
        $this->assertSame('canceled', $row->fresh()->status);
        $this->assertSame(0, $import->questions()->count());
        $this->getJson(route('builder.ai.status.json', $import))->assertJsonPath('status', 'canceled');
        $this->assertSame(20, app(AssistantBudgetService::class)->summary($school)['remaining']);
    }

    public function test_detected_chapter_changes_require_confirmation_before_reservation(): void
    {
        extract($this->context());
        $second = Chapter::create(['subject_id' => $subject->id, 'number' => 2, 'title_en' => 'Energy', 'is_active' => true]);
        $data = [...$data, 'raw_text' => 'Find questions for class 9 Physics chapter 2', 'mode' => 'search'];
        $this->post(route('builder.ai.paste.store'), $data)->assertSessionHasErrors('raw_text')->assertSessionHas('assistant_context_confirmation');
        $this->assertDatabaseCount('assistant_requests', 0);
        $this->post(route('builder.ai.paste.store'), [...$data, 'confirm_context' => true])->assertRedirect();
        $this->assertSame([$second->id], AIImport::firstOrFail()->chapterIds());
    }

    public function test_other_school_cannot_access_an_assistant_preview_even_for_a_past_paper(): void
    {
        extract($this->context());
        $this->post(route('builder.ai.paste.store'), $data)->assertRedirect();
        $import = AIImport::firstOrFail();
        $import->update(['book_type' => 'past_paper']);
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('builder.ai.status', $import))->assertForbidden();
    }

    public function test_search_keeps_source_provenance_and_never_falls_back_to_invented_questions(): void
    {
        extract($this->context());
        AISetting::current()->update(['langsearch_api_key' => 'test-search-key']);
        $this->mock(UrlSafetyService::class)->shouldReceive('assertSafe')->andReturnUsing(fn ($url) => $url);
        Http::fake([
            'api.langsearch.com/*' => Http::response(['data' => ['webPages' => ['value' => [['name' => 'Board paper', 'url' => 'https://board.example/paper', 'datePublished' => '2026-02-01', 'snippet' => 'Question paper']]]]]),
            'board.example/*' => Http::response('<html><body>What is force? Answer: A push or pull.</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $this->mock(AssistantAiExtractor::class)->shouldReceive('extract')->once()->andReturn([['type' => 'short', 'text_en' => 'What is force?', 'answer' => 'A push or pull']]);
        $this->post(route('builder.ai.paste.store'), [...$data, 'mode' => 'search', 'raw_text' => 'Find force questions'])->assertRedirect();
        app(QuestionAssistantService::class)->process(AssistantRequest::firstOrFail());
        $import = AIImport::firstOrFail();
        $question = $import->questions()->firstOrFail();
        $this->assertSame('https://board.example/paper', $question->source_url);
        $this->assertSame('2026-02-01', $question->source_published_at->toDateString());
        $this->post(route('builder.ai.add-to-paper', $import), ['staging_ids' => [$question->id]])->assertRedirect();
        $saved = Question::firstOrFail();
        $this->assertSame($question->source_url, $saved->source_url);
        Http::assertSentCount(3);
    }

    public function test_empty_search_refunds_request_without_generating_replacements(): void
    {
        extract($this->context());
        AISetting::current()->update(['langsearch_api_key' => 'test-search-key']);
        Http::fake(['api.langsearch.com/*' => Http::response(['data' => ['webPages' => ['value' => []]]])]);
        $this->mock(AssistantAiExtractor::class)->shouldNotReceive('extract');
        $this->post(route('builder.ai.paste.store'), [...$data, 'mode' => 'search', 'raw_text' => 'Find force questions'])->assertRedirect();
        $row = AssistantRequest::firstOrFail();
        app(QuestionAssistantService::class)->process($row);
        $this->assertSame('failed', $row->fresh()->status);
        $this->assertSame(0, AIImport::firstOrFail()->questions()->count());
        $totals = app(AssistantBudgetService::class)->summary($school);
        $this->assertSame(20, $totals['remaining']);
        $this->assertSame(1, $totals['daily_used']);
        Http::assertSentCount(2);
    }

    public function test_oversized_image_is_rejected_before_reserving_or_calling_providers(): void
    {
        extract($this->context());
        $file = UploadedFile::fake()->image('questions.png')->size(4096);
        $this->post(route('builder.ai.paste.store'), [...$data, 'mode' => 'upload', 'file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('assistant_requests', 0);
        Http::assertNothingSent();
    }

    public function test_pdf_page_limit_is_checked_before_reservation_and_provider_calls(): void
    {
        extract($this->context());
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Count 6 /Kids [3 0 R 4 0 R 5 0 R 6 0 R 7 0 R 8 0 R] >>'];
        for ($i = 0; $i < 6; $i++) {
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << >> >>';
        }
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 9\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        $file = UploadedFile::fake()->createWithContent('six-pages.pdf', $pdf);
        $this->post(route('builder.ai.paste.store'), [...$data, 'mode' => 'upload', 'file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('assistant_requests', 0);
        Http::assertNothingSent();
    }

    public function test_configured_text_client_has_fixed_output_limit_and_no_retry(): void
    {
        extract($this->context());
        AISetting::current()->update(['preferred_text_provider' => 'groq', 'groq_enabled' => true, 'groq_api_key' => 'test-groq-key', 'retry_count' => 5, 'max_tokens' => 30000]);
        $this->post(route('builder.ai.paste.store'), $data)->assertRedirect();
        $import = AIImport::firstOrFail();
        Http::fake(['api.groq.com/*' => Http::sequence()->push(['choices' => [['message' => ['content' => '{"questions":[{"type":"short","text_en":"What is force?"}]}']]], 'usage' => ['completion_tokens' => 20, 'prompt_tokens' => 50]])->push([], 429)]);
        $result = app(AssistantAiExtractor::class)->extract($import, 'What is force?');
        $this->assertCount(1, $result);
        Http::assertSent(fn ($request) => $request['max_completion_tokens'] === 4000 && ! isset($request['tools']));
        Http::assertSentCount(1);
        $failed = false;
        try {
            app(AssistantAiExtractor::class)->extract($import, 'What is force?');
        } catch (\RuntimeException $e) {
            $failed = true;
            $this->assertStringContainsString('unavailable', $e->getMessage());
        }
        $this->assertTrue($failed, 'Rate-limit responses must fail without retrying.');
        Http::assertSentCount(2);
    }
}
