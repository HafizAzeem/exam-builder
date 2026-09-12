<?php

namespace App\Services\AIImport;

use App\Models\AIImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AIImportService
{
    public function disk(): string
    {
        return config('filesystems.ai_import_disk', 'r2');
    }

    public function createFromUpload(array $data, UploadedFile $file, int $userId): AIImport
    {
        $import = AIImport::create([
            'user_id' => $userId,
            'grade_id' => $data['grade_id'],
            'subject_id' => $data['subject_id'],
            'book_type' => $data['book_type'],
            'board' => $data['board'] ?? null,
            'year' => $data['year'] ?? null,
            'session' => $data['session'] ?? null,
            'language' => $data['language'] ?? 'english',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => '',
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize() ?: 0,
            'status' => 'uploaded',
        ]);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = "ai-imports/{$import->id}/{$filename}";

        Storage::disk($this->disk())->put($path, file_get_contents($file->getRealPath()));

        $import->update(['stored_path' => $path]);

        return $import->fresh(['grade', 'subject', 'user']);
    }

    public function createFromPastedText(array $data, string $rawText, int $userId): AIImport
    {
        $text = trim(preg_replace("/\r\n?/", "\n", $rawText) ?? $rawText);

        $import = AIImport::create([
            'user_id' => $userId,
            'grade_id' => $data['grade_id'],
            'subject_id' => $data['subject_id'],
            'book_type' => $data['book_type'],
            'board' => $data['board'] ?? null,
            'year' => $data['year'] ?? null,
            'session' => $data['session'] ?? null,
            'language' => $data['language'] ?? 'english',
            'original_filename' => 'pasted-text-'.now()->format('Ymd-His').'.txt',
            'stored_path' => '',
            'mime_type' => 'text/plain',
            'file_size' => strlen($text),
            'status' => 'uploaded',
        ]);

        $path = "ai-imports/{$import->id}/".Str::uuid()->toString().'.txt';
        Storage::disk($this->disk())->put($path, $text);

        $import->update(['stored_path' => $path]);

        return $import->fresh(['grade', 'subject', 'user']);
    }

    public function updateProgress(AIImport $import, int $processedChunks): void
    {
        $total = max(1, (int) $import->total_chunks);
        $percent = (int) min(99, round(($processedChunks / $total) * 100));

        $import->update([
            'processed_chunks' => $processedChunks,
            'progress_percent' => $percent,
        ]);
    }

    public function markStatus(AIImport $import, string $status, ?string $error = null): void
    {
        $payload = ['status' => $status];

        if ($error !== null) {
            $payload['error_message'] = $error;
        }

        if ($status === 'review') {
            $payload['progress_percent'] = 100;
        }

        if ($status === 'completed') {
            $payload['progress_percent'] = 100;
        }

        $import->update($payload);
    }

    public function dashboardStats(): array
    {
        return [
            'total_uploaded' => AIImport::query()->count(),
            'pending_reviews' => AIImport::query()->where('status', 'review')->count(),
            'approved_questions' => AIImport::query()->sum('approved_count'),
            'rejected_questions' => AIImport::query()->sum('rejected_count'),
            'imported_questions' => AIImport::query()->sum('imported_count'),
            'processing_jobs' => AIImport::query()
                ->whereIn('status', ['uploaded', 'extracting', 'processing', 'importing'])
                ->count(),
        ];
    }
}
