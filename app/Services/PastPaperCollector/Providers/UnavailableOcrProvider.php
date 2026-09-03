<?php

namespace App\Services\PastPaperCollector\Providers;

use App\Contracts\PastPaperCollector\OcrProvider;

class UnavailableOcrProvider implements OcrProvider
{
    public function supports(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/')
            || $mimeType === 'application/pdf';
    }

    public function extractText(string $absolutePath, string $mimeType): string
    {
        throw new \RuntimeException(
            'OCR is not configured yet. Scanned documents are flagged as ocr_required for future processing.'
        );
    }

    public function name(): string
    {
        return 'unavailable';
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
