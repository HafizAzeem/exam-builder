<?php

namespace App\Services\QuestionAssistant;

use App\Models\AISetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;

class AssistantUploadValidator
{
    public function validate(UploadedFile $file): ?string
    {
        $mime = $file->getMimeType();
        if ($mime === 'application/pdf') {
            if ($file->getSize() > 5 * 1024 * 1024) {
                $this->reject('PDFs must be 5 MB or smaller.');
            }
            try {
                $pdf = (new Parser)->parseFile($file->getRealPath());
                $pages = $pdf->getPages();
            } catch (\Throwable) {
                $this->reject('The PDF is encrypted or unreadable. Upload a readable PDF of up to 5 pages.');
            }
            if (count($pages) < 1 || count($pages) > 5) {
                $this->reject('PDFs must contain 1–5 pages. Split this document before uploading.');
            }
            $text = trim($pdf->getText());
            if ($text === '') {
                if (! AISetting::current()->isGeminiUsable()) {
                    $this->reject('This PDF needs document OCR, which is not configured. Upload one clear page as an image instead.');
                }

                return null;
            }
            if (mb_strlen($text) > 20000) {
                $this->reject('The PDF contains more than 20,000 characters. Upload a shorter extract.');
            }

            return $text;
        }
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $file->getSize() > 3 * 1024 * 1024) {
            $this->reject('Upload one JPG, PNG or WebP image up to 3 MB, or a PDF up to 5 MB.');
        }
        $size = @getimagesize($file->getRealPath());
        if (! $size || $size[0] > 6000 || $size[1] > 6000) {
            $this->reject('Upload a readable image with dimensions up to 6000 × 6000 pixels.');
        }

        return null;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
