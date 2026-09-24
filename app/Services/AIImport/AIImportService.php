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
            'mode' => 'upload',
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
            'meta' => [
                'chapter_ids' => array_values(array_map('intval', $data['chapter_ids'] ?? [])),
            ],
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
            'mode' => 'paste',
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
            'meta' => [
                'chapter_ids' => array_values(array_map('intval', $data['chapter_ids'] ?? [])),
            ],
        ]);

        $path = "ai-imports/{$import->id}/".Str::uuid()->toString().'.txt';
        Storage::disk($this->disk())->put($path, $text);

        $import->update(['stored_path' => $path]);

        return $import->fresh(['grade', 'subject', 'user']);
    }

    /**
     * @param  array{
     *   grade_id:int,
     *   subject_id:int,
     *   chapter_ids:list<int>,
     *   book_type?:string,
     *   board?:?string,
     *   language?:string,
     *   counts:array<string,int>
     * }  $data
     */
    public function createFromGenerate(array $data, int $userId): AIImport
    {
        $counts = $data['counts'] ?? [];
        $contentSource = $data['content_source'] ?? 'exercise';

        $bookType = $data['book_type'] ?? match ($contentSource) {
            'past_paper' => 'past_paper',
            'online_practice' => 'additional_questions',
            'exercise' => 'text_book',
            default => 'additional_questions',
        };

        return AIImport::create([
            'user_id' => $userId,
            'mode' => 'generate',
            'grade_id' => $data['grade_id'],
            'subject_id' => $data['subject_id'],
            'book_type' => $bookType,
            'board' => $data['board'] ?? null,
            'year' => null,
            'session' => null,
            'language' => $data['language'] ?? 'english',
            'original_filename' => 'ai-generate-'.now()->format('Ymd-His').'.json',
            'stored_path' => '',
            'mime_type' => 'application/json',
            'file_size' => 0,
            'status' => 'uploaded',
            'meta' => [
                'chapter_ids' => array_values(array_map('intval', $data['chapter_ids'] ?? [])),
                'content_source' => $contentSource,
                // Hidden default: always prefer education websites first.
                'prefer_websites' => true,
                'counts' => [
                    'mcq' => (int) ($counts['mcq'] ?? 0),
                    'short' => (int) ($counts['short'] ?? 0),
                    'long' => (int) ($counts['long'] ?? 0),
                    'fill' => (int) ($counts['fill'] ?? 0),
                    'truefalse' => (int) ($counts['truefalse'] ?? 0),
                ],
            ],
        ])->fresh(['grade', 'subject', 'user']);
    }

    /**
     * Ensure staging questions map to one of the teacher-selected chapters.
     */
    public function constrainChapters(AIImport $import): void
    {
        $allowed = $import->chapterIds();
        if (! $allowed) {
            return;
        }

        $fallback = $allowed[0];

        $import->questions()
            ->where(function ($q) use ($allowed) {
                $q->whereNull('chapter_id')
                    ->orWhereNotIn('chapter_id', $allowed);
            })
            ->update([
                'chapter_id' => $fallback,
                'match_status' => 'manual',
            ]);
    }

    public function updateProgress(AIImport $import, int $processedChunks): void
    {
        $total = max(1, (int) $import->total_chunks);
        $percent = (int) min(99, round(($processedChunks / $total) * 100));

        $import->update([
            'processed_chunks' => $processedChunks,
            'progress_percent' => $percent,
        ]);

        $import->broadcastProgress();
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
        $import->broadcastProgress();
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
