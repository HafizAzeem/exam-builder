<?php

namespace Tests\Unit\PastPaperCollector;

use App\Contracts\PastPaperCollector\OcrProvider;
use App\Services\PastPaperCollector\Providers\UnavailableOcrProvider;
use App\Services\PastPaperCollector\SourceFetchService;
use App\Services\PastPaperCollector\UrlSafetyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SourceFetchAndOcrTest extends TestCase
{
    use RefreshDatabase;

    public function test_ocr_provider_is_unavailable_by_default(): void
    {
        $ocr = app(OcrProvider::class);

        $this->assertInstanceOf(UnavailableOcrProvider::class, $ocr);
        $this->assertFalse($ocr->isAvailable());

        $this->expectException(\RuntimeException::class);
        $ocr->extractText('/tmp/scan.pdf', 'application/pdf');
    }

    public function test_scanned_pdf_is_marked_ocr_required(): void
    {
        Storage::fake(config('filesystems.ai_import_disk', 'local'));
        Storage::fake('local');

        // Minimal PDF header without extractable text layer.
        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

        Http::fake([
            'example.com/*' => Http::response($pdf, 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $result = app(SourceFetchService::class)->fetchAndExtract(
            'https://example.com/scanned.pdf',
            1
        );

        $this->assertSame('ocr_required', $result['status']);
        $this->assertStringContainsString('OCR', (string) $result['error_message']);
    }

    public function test_html_source_extracts_cleaned_text(): void
    {
        Storage::fake(config('filesystems.ai_import_disk', 'local'));

        $html = '<html><body><h1>BISE Lahore Physics Past Paper</h1><p>Q1. Define acceleration with formula and units for class 9.</p><p>Q2. What is velocity?</p></body></html>';

        Http::fake([
            'papers.example.edu/*' => Http::response($html, 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]),
        ]);

        // Avoid DNS private checks failing for fake domain by stubbing assertSafe path via real public-looking host.
        $result = app(SourceFetchService::class)->fetchAndExtract(
            'https://papers.example.edu/physics.html',
            99
        );

        $this->assertSame('extracted', $result['status']);
        $this->assertStringContainsString('Define acceleration', (string) $result['extracted_text']);
    }

    public function test_private_ip_redirect_target_is_blocked(): void
    {
        $service = new UrlSafetyService;

        $this->expectException(\InvalidArgumentException::class);
        $service->assertSafe('http://192.168.1.10/internal');
    }
}
