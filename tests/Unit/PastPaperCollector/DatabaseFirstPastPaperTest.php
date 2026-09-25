<?php

namespace Tests\Unit\PastPaperCollector;

use App\Contracts\PastPaperCollector\OcrProvider;
use App\Models\AIImport;
use App\Models\AIImportQuestion;
use App\Models\AIPaperCollection;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use App\Services\PastPaperCollector\PastPaperCollectionService;
use App\Services\PastPaperCollector\PastPaperContentValidator;
use App\Services\PastPaperCollector\Providers\GeminiOcrProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseFirstPastPaperTest extends TestCase
{
    use RefreshDatabase;

    protected function seedCurriculum(): array
    {
        $user = User::factory()->create();
        $grade = Grade::query()->create(['number' => 9, 'label_en' => 'Class 9', 'label_ur' => null]);
        $subject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'Physics',
            'name_ur' => null,
            'sort_order' => 1,
        ]);

        return compact('user', 'grade', 'subject');
    }

    public function test_ocr_provider_is_gemini_and_unavailable_without_key(): void
    {
        $ocr = app(OcrProvider::class);

        $this->assertInstanceOf(GeminiOcrProvider::class, $ocr);
        $this->assertFalse($ocr->isAvailable());
    }

    public function test_find_reusable_returns_existing_review_collection(): void
    {
        ['user' => $user, 'grade' => $grade, 'subject' => $subject] = $this->seedCurriculum();

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
            'status' => 'review',
        ]);

        AIImportQuestion::query()->create([
            'ai_import_id' => $import->id,
            'type' => 'short',
            'text_en' => 'Define force.',
            'status' => 'pending',
        ]);

        $existing = AIPaperCollection::query()->create([
            'user_id' => $user->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'ai_import_id' => $import->id,
            'board' => 'Lahore Board',
            'year' => 2020,
            'session' => 'morning',
            'paper_type' => 'complete',
            'language' => 'english',
            'country' => 'Pakistan',
            'max_results' => 10,
            'status' => 'review',
            'progress_stage' => 'review',
            'progress_percent' => 100,
            'questions_found' => 1,
        ]);

        Queue::fake();

        $result = app(PastPaperCollectionService::class)->createOrReuse([
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'board' => 'Lahore Board',
            'year' => 2020,
            'session' => 'morning',
            'paper_type' => 'complete',
            'language' => 'english',
            'max_results' => 5,
        ], $user->id);

        $this->assertTrue($result['reused']);
        $this->assertSame($existing->id, $result['collection']->id);
        $this->assertSame(1, AIPaperCollection::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_create_or_reuse_searches_when_no_match(): void
    {
        ['user' => $user, 'grade' => $grade, 'subject' => $subject] = $this->seedCurriculum();
        Queue::fake();

        $result = app(PastPaperCollectionService::class)->createOrReuse([
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'board' => 'Lahore Board',
            'year' => 2020,
            'session' => 'morning',
            'paper_type' => 'complete',
            'language' => 'english',
            'max_results' => 5,
        ], $user->id);

        $this->assertFalse($result['reused']);
        $this->assertSame(1, AIPaperCollection::query()->count());
        Queue::assertPushed(\App\Jobs\CollectPastPapersJob::class);
    }

    public function test_content_validator_rejects_wrong_subject(): void
    {
        ['grade' => $grade, 'subject' => $subject] = $this->seedCurriculum();
        $collection = new AIPaperCollection([
            'board' => 'Lahore Board',
            'year' => 2020,
        ]);
        $collection->setRelation('grade', $grade);
        $collection->setRelation('subject', $subject);

        $ok = app(PastPaperContentValidator::class)->matches(
            "BISE Lahore Board Class 9 Physics Annual 2020\nQ1. Define acceleration.",
            $collection
        );
        $this->assertTrue($ok);

        $bad = app(PastPaperContentValidator::class)->matches(
            "BISE Multan Board Class 10 Chemistry Annual 2018\nQ1. Define mole.",
            $collection
        );
        $this->assertFalse($bad);
    }

    public function test_scanned_pdf_still_marked_ocr_required_without_api_key(): void
    {
        Storage::fake(config('filesystems.ai_import_disk', 'local'));

        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

        Http::fake([
            'example.com/*' => Http::response($pdf, 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $result = app(\App\Services\PastPaperCollector\SourceFetchService::class)->fetchAndExtract(
            'https://example.com/scanned.pdf',
            1
        );

        $this->assertSame('ocr_required', $result['status']);
        $this->assertStringContainsString('OCR', (string) $result['error_message']);
    }
}
