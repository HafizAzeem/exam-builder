<?php

namespace App\Services\PastPaperCollector\Providers;

use App\Contracts\PastPaperCollector\OcrProvider;
use App\Models\AISetting;
use Illuminate\Support\Facades\Http;

/**
 * Vision / document OCR via Gemini multimodal (PDF + images).
 */
class GeminiOcrProvider implements OcrProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function isAvailable(): bool
    {
        return filled(AISetting::current()->resolvedGeminiApiKey());
    }

    public function supports(string $mimeType): bool
    {
        $mimeType = strtolower($mimeType);

        return $mimeType === 'application/pdf'
            || str_starts_with($mimeType, 'image/');
    }

    public function extractText(string $absolutePath, string $mimeType): string
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException(
                'OCR is not configured. Set the Gemini API key in AI Settings.'
            );
        }

        if (! is_file($absolutePath)) {
            throw new \RuntimeException('OCR source file not found.');
        }

        $settings = AISetting::current();
        $settings->applyProviderConfig();

        $apiKey = $settings->resolvedGeminiApiKey();
        $model = $settings->resolvedTextModel() ?: 'gemini-3.5-flash-lite';
        $bytes = file_get_contents($absolutePath);
        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException('OCR source file is empty.');
        }

        // Gemini inline payloads: keep under ~15MB base64-safe budget.
        if (strlen($bytes) > 12_000_000) {
            throw new \RuntimeException('Document is too large for OCR. Split or compress the PDF.');
        }

        $mimeType = $this->normalizeMime($mimeType, $absolutePath);
        $base64 = base64_encode($bytes);

        $baseUrl = rtrim((string) config('ai.providers.gemini.url', 'https://generativelanguage.googleapis.com/v1beta/'), '/');
        $endpoint = "{$baseUrl}/models/{$model}:generateContent";

        $response = Http::timeout(180)
            ->acceptJson()
            ->asJson()
            ->post($endpoint.'?key='.urlencode((string) $apiKey), [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64,
                                ],
                            ],
                            ['text' => $this->prompt()],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 8192,
                ],
            ]);

        if ($response->status() === 429) {
            throw new \RuntimeException('Gemini rate limit exceeded during OCR.');
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini OCR failed: '.mb_substr($response->body(), 0, 500));
        }

        $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
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
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/pdf',
        };
    }

    protected function prompt(): string
    {
        return <<<'PROMPT'
You are reading a Pakistani board past paper document (PDF or scanned image).

Extract ALL readable exam text in reading order.
Preserve:
- paper header (board, class, subject, year, session) if visible
- section titles
- question numbers
- full question wording (English and/or Urdu)
- MCQ options A B C D when present

Rules:
- Do not invent missing text.
- If a page is unreadable, write: [UNREADABLE PAGE]
- Output plain text only (no markdown fences).
PROMPT;
    }
}
