<?php

namespace App\Services\PastPaperCollector;

use App\Contracts\PastPaperCollector\OcrProvider;
use App\Models\AISetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\DomCrawler\Crawler;

class SourceFetchService
{
    public function __construct(
        protected UrlSafetyService $urlSafety,
        protected OcrProvider $ocr,
    ) {}

    /**
     * @return array{
     *   content_type:?string,
     *   content_hash:string,
     *   stored_path:string,
     *   file_size:int,
     *   http_status:int,
     *   extracted_text:?string,
     *   status:string,
     *   error_message:?string,
     *   meta:array<string,mixed>
     * }
     */
    public function fetchAndExtract(string $url, int $collectionId): array
    {
        $settings = AISetting::current();
        $safeUrl = $this->urlSafety->assertSafe($url);
        $timeout = max(5, (int) $settings->search_timeout);
        $maxBytes = max(100_000, (int) $settings->max_source_bytes);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'User-Agent' => 'ExamBuilderPastPaperCollector/1.0',
                'Accept' => 'application/pdf,image/jpeg,image/png,image/webp,text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            ])
            ->withOptions([
                'allow_redirects' => [
                    'max' => 5,
                    'track_redirects' => true,
                ],
            ])
            ->get($safeUrl);

        $finalUrl = (string) ($response->effectiveUri() ?? $safeUrl);
        $this->urlSafety->assertSafe($finalUrl);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().' fetching source.');
        }

        $body = $response->body();
        $fileSize = strlen($body);
        if ($fileSize > $maxBytes) {
            throw new \RuntimeException('Source exceeds maximum allowed size.');
        }

        $contentType = strtolower((string) ($response->header('Content-Type') ?: ''));
        $contentType = trim(explode(';', $contentType)[0]);
        $extension = $this->guessExtension($finalUrl, $contentType, $body);
        $hash = hash('sha256', $body);

        $disk = Storage::disk(config('filesystems.ai_import_disk', config('filesystems.default')));
        $path = "ai-paper-collections/{$collectionId}/".Str::random(16).'.'.$extension;
        $disk->put($path, $body);

        $tempPath = storage_path('app/temp/paper-source-'.$collectionId.'-'.uniqid().'.'.$extension);
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        file_put_contents($tempPath, $body);

        try {
            return $this->extractFromTemp(
                tempPath: $tempPath,
                extension: $extension,
                contentType: $contentType,
                contentHash: $hash,
                storedPath: $path,
                fileSize: $fileSize,
                httpStatus: $response->status(),
                finalUrl: $finalUrl,
                settings: $settings,
            );
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractFromTemp(
        string $tempPath,
        string $extension,
        string $contentType,
        string $contentHash,
        string $storedPath,
        int $fileSize,
        int $httpStatus,
        string $finalUrl,
        AISetting $settings,
    ): array {
        $meta = [
            'final_url' => $finalUrl,
            'extension' => $extension,
        ];

        if (in_array($extension, ['html', 'htm'], true) || str_contains($contentType, 'text/html')) {
            $text = $this->extractHtml(file_get_contents($tempPath) ?: '');
            $cleaned = $this->cleanText($text);

            if (mb_strlen($cleaned) < 80) {
                return $this->result($contentType, $contentHash, $storedPath, $fileSize, $httpStatus, $cleaned, 'ignored', 'Insufficient HTML text content.', $meta);
            }

            return $this->result($contentType ?: 'text/html', $contentHash, $storedPath, $fileSize, $httpStatus, $cleaned, 'extracted', null, $meta);
        }

        if ($extension === 'pdf' || str_contains($contentType, 'pdf')) {
            $text = $this->extractPdfText($tempPath);
            $cleaned = $this->cleanText($text);

            if (mb_strlen($cleaned) < 40) {
                $meta['scanned_candidate'] = true;

                return $this->ocrOrRequire(
                    tempPath: $tempPath,
                    mimeType: 'application/pdf',
                    contentHash: $contentHash,
                    storedPath: $storedPath,
                    fileSize: $fileSize,
                    httpStatus: $httpStatus,
                    fallbackText: $cleaned,
                    meta: $meta,
                );
            }

            // Soft page limit: truncate extremely long extracted text for processing safety.
            $maxPages = max(1, (int) $settings->max_pages_per_source);
            $approxChars = $maxPages * 3000;
            if (mb_strlen($cleaned) > $approxChars) {
                $cleaned = mb_substr($cleaned, 0, $approxChars);
                $meta['truncated'] = true;
            }

            return $this->result('application/pdf', $contentHash, $storedPath, $fileSize, $httpStatus, $cleaned, 'extracted', null, $meta);
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) || str_starts_with($contentType, 'image/')) {
            $mime = $contentType !== '' && str_starts_with($contentType, 'image/')
                ? $contentType
                : match ($extension) {
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                    default => 'image/jpeg',
                };

            $meta['image_source'] = true;

            return $this->ocrOrRequire(
                tempPath: $tempPath,
                mimeType: $mime,
                contentHash: $contentHash,
                storedPath: $storedPath,
                fileSize: $fileSize,
                httpStatus: $httpStatus,
                fallbackText: '',
                meta: $meta,
            );
        }

        if ($extension === 'txt' || str_starts_with($contentType, 'text/')) {
            $cleaned = $this->cleanText((string) file_get_contents($tempPath));

            return $this->result($contentType ?: 'text/plain', $contentHash, $storedPath, $fileSize, $httpStatus, $cleaned, 'extracted', null, $meta);
        }

        return $this->result($contentType, $contentHash, $storedPath, $fileSize, $httpStatus, null, 'ignored', 'Unsupported content type.', $meta);
    }

    /**
     * Attempt OCR/vision; never treat a scanned PDF/image as a quiet empty success.
     *
     * @param  array<string,mixed>  $meta
     * @return array<string,mixed>
     */
    protected function ocrOrRequire(
        string $tempPath,
        string $mimeType,
        string $contentHash,
        string $storedPath,
        int $fileSize,
        int $httpStatus,
        string $fallbackText,
        array $meta,
    ): array {
        if ($this->ocr->isAvailable() && $this->ocr->supports($mimeType)) {
            try {
                $ocrText = $this->cleanText($this->ocr->extractText($tempPath, $mimeType));
                if (mb_strlen($ocrText) >= 40) {
                    $meta['ocr_provider'] = $this->ocr->name();

                    return $this->result($mimeType, $contentHash, $storedPath, $fileSize, $httpStatus, $ocrText, 'extracted', null, $meta);
                }
                $meta['ocr_empty'] = true;
            } catch (\Throwable $e) {
                $meta['ocr_error'] = $e->getMessage();
            }
        }

        return $this->result(
            $mimeType,
            $contentHash,
            $storedPath,
            $fileSize,
            $httpStatus,
            $fallbackText !== '' ? $fallbackText : null,
            'ocr_required',
            'Scanned/image document requires OCR/vision. Do not treat this as an empty paper.',
            $meta,
        );
    }

    protected function extractHtml(string $html): string
    {
        $crawler = new Crawler($html);
        $crawler->filter('script, style, noscript, nav, footer, header, iframe, form, aside')->each(function (Crawler $node) {
            foreach ($node as $domNode) {
                $domNode->parentNode?->removeChild($domNode);
            }
        });

        $text = $crawler->filter('body')->count()
            ? $crawler->filter('body')->text(' ')
            : $crawler->text(' ');

        return $text;
    }

    protected function extractPdfText(string $path): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($path);

            return trim($pdf->getText());
        } catch (\Throwable) {
            return '';
        }
    }

    public function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        // Drop common page-number / footer-ish lines.
        $lines = preg_split("/\n/u", $text) ?: [];
        $filtered = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $filtered[] = '';

                continue;
            }
            if (preg_match('/^(page\s*)?\d{1,3}(\s*of\s*\d{1,3})?$/i', $trim)) {
                continue;
            }
            $filtered[] = $trim;
        }

        return trim(implode("\n", $filtered));
    }

    protected function guessExtension(string $url, string $contentType, string $body): string
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));
        if (str_ends_with($path, '.pdf') || str_contains($contentType, 'pdf') || str_starts_with($body, '%PDF')) {
            return 'pdf';
        }
        if (str_ends_with($path, '.png') || str_contains($contentType, 'image/png')) {
            return 'png';
        }
        if (str_ends_with($path, '.webp') || str_contains($contentType, 'image/webp')) {
            return 'webp';
        }
        if (str_ends_with($path, '.gif') || str_contains($contentType, 'image/gif')) {
            return 'gif';
        }
        if (
            str_ends_with($path, '.jpg')
            || str_ends_with($path, '.jpeg')
            || str_contains($contentType, 'image/jpeg')
            || str_contains($contentType, 'image/jpg')
        ) {
            return 'jpg';
        }
        if (str_ends_with($path, '.txt') || str_starts_with($contentType, 'text/plain')) {
            return 'txt';
        }
        if (str_ends_with($path, '.docx')) {
            return 'docx';
        }

        return 'html';
    }

    /**
     * @param  array<string,mixed>  $meta
     * @return array<string,mixed>
     */
    protected function result(
        ?string $contentType,
        string $contentHash,
        string $storedPath,
        int $fileSize,
        int $httpStatus,
        ?string $extractedText,
        string $status,
        ?string $error,
        array $meta,
    ): array {
        return [
            'content_type' => $contentType,
            'content_hash' => $contentHash,
            'stored_path' => $storedPath,
            'file_size' => $fileSize,
            'http_status' => $httpStatus,
            'extracted_text' => $extractedText,
            'status' => $status,
            'error_message' => $error,
            'meta' => $meta,
        ];
    }
}
