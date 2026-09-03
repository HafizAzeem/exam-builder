<?php

namespace App\Contracts\PastPaperCollector;

interface OcrProvider
{
    public function supports(string $mimeType): bool;

    /**
     * Extract text from a scanned/image document.
     *
     * @throws \RuntimeException when OCR is unavailable
     */
    public function extractText(string $absolutePath, string $mimeType): string;

    public function name(): string;

    public function isAvailable(): bool;
}
