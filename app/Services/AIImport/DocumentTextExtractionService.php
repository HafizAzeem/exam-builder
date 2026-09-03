<?php

namespace App\Services\AIImport;

use App\Models\AIImport;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentTextExtractionService
{
    public function extract(AIImport $import): string
    {
        $disk = Storage::disk(app(AIImportService::class)->disk());
        $contents = $disk->get($import->stored_path);

        $extension = strtolower(pathinfo($import->original_filename, PATHINFO_EXTENSION));
        $tempPath = storage_path('app/temp/ai-import-'.$import->id.'-'.uniqid().'.'.$extension);

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        file_put_contents($tempPath, $contents);

        try {
            return match ($extension) {
                'pdf' => $this->extractPdf($tempPath),
                'docx' => $this->extractDocx($tempPath),
                'txt' => (string) file_get_contents($tempPath),
                default => throw new \RuntimeException("Unsupported file type: {$extension}"),
            };
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    protected function extractPdf(string $path): string
    {
        $parser = new PdfParser;
        $pdf = $parser->parseFile($path);

        return trim($pdf->getText());
    }

    protected function extractDocx(string $path): string
    {
        $phpWord = WordIOFactory::load($path);
        $text = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text[] = $this->elementToText($element);
            }
        }

        return trim(implode("\n", array_filter($text)));
    }

    protected function elementToText(mixed $element): string
    {
        if (method_exists($element, 'getText')) {
            $value = $element->getText();

            return is_string($value) ? $value : '';
        }

        if (method_exists($element, 'getElements')) {
            $parts = [];
            foreach ($element->getElements() as $child) {
                $parts[] = $this->elementToText($child);
            }

            return implode(' ', array_filter($parts));
        }

        return '';
    }
}
