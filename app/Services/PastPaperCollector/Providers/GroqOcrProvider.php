<?php

namespace App\Services\PastPaperCollector\Providers;

use App\Contracts\PastPaperCollector\OcrProvider;
use App\Models\AISetting;
use App\Services\AIImport\GroqClient;

/**
 * Vision OCR via Groq qwen/qwen3.8-27b (images; PDF must already be page images).
 */
class GroqOcrProvider implements OcrProvider
{
    public function __construct(
        protected GroqClient $groq,
    ) {}

    public function name(): string
    {
        return AISetting::PROVIDER_GROQ;
    }

    public function isAvailable(): bool
    {
        return AISetting::current()->isGroqUsable();
    }

    public function supports(string $mimeType): bool
    {
        $mimeType = strtolower($mimeType);

        return str_starts_with($mimeType, 'image/');
    }

    public function extractText(string $absolutePath, string $mimeType): string
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('OCR is not configured. Set the Groq API key in AI Settings.');
        }

        if (! is_file($absolutePath)) {
            throw new \RuntimeException('OCR source file not found.');
        }

        $mimeType = $this->normalizeMime($mimeType, $absolutePath);
        if (! str_starts_with($mimeType, 'image/')) {
            throw new \RuntimeException(
                'Groq OCR accepts images only. Convert PDF pages to PNG/JPEG first, or switch the primary/fallback provider to Gemini for PDFs.'
            );
        }

        $bytes = file_get_contents($absolutePath);
        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException('OCR source file is empty.');
        }

        if (strlen($bytes) > 12_000_000) {
            throw new \RuntimeException('Image is too large for Groq OCR (20MB request limit). Compress it and retry.');
        }

        $settings = AISetting::current();
        $dataUrl = 'data:'.$mimeType.';base64,'.base64_encode($bytes);

        $result = $this->groq->chat([
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $this->prompt()],
                    ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                ],
            ],
        ], $settings->resolvedGroqVisionModel());

        $text = trim($result['content']);
        if ($text === '') {
            throw new \RuntimeException('OCR returned no readable text from this document.');
        }

        return $text;
    }

    protected function normalizeMime(string $mimeType, string $path): string
    {
        $mimeType = strtolower(trim(explode(';', $mimeType)[0]));
        if ($mimeType !== '' && $mimeType !== 'application/octet-stream') {
            return $mimeType;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            default => 'image/jpeg',
        };
    }

    protected function prompt(): string
    {
        return <<<'PROMPT'
You are reading a Pakistani board past paper page (scanned image).

Extract ALL readable exam text in reading order.
Preserve:
- paper header (board, class, subject, year, session) if visible
- section titles
- question numbers
- full question wording (English and/or Urdu)
- MCQ options A B C D when present

Rules:
- Do not invent missing text.
- If the page is unreadable, write: [UNREADABLE PAGE]
- Output plain text only (no markdown fences).
PROMPT;
    }
}
