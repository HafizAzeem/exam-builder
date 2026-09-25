<?php

namespace App\Models;

use App\Events\AIImportProgressUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Throwable;

class AIImport extends Model
{
    protected $table = 'ai_imports';

    public const BOOK_TYPE_SOURCE_MAP = [
        'text_book' => 'exercise',
        'additional_questions' => 'additional',
        'past_paper' => 'past_paper',
    ];

    protected $fillable = [
        'user_id',
        'mode',
        'grade_id',
        'subject_id',
        'book_type',
        'board',
        'year',
        'session',
        'language',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'status',
        'progress_percent',
        'total_chunks',
        'processed_chunks',
        'questions_found',
        'approved_count',
        'rejected_count',
        'imported_count',
        'failed_count',
        'duplicate_count',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'year' => 'integer',
        'file_size' => 'integer',
        'progress_percent' => 'integer',
        'total_chunks' => 'integer',
        'processed_chunks' => 'integer',
        'questions_found' => 'integer',
        'approved_count' => 'integer',
        'rejected_count' => 'integer',
        'imported_count' => 'integer',
        'failed_count' => 'integer',
        'duplicate_count' => 'integer',
        'meta' => 'array',
    ];

    public function chapterIds(): array
    {
        $ids = $this->meta['chapter_ids'] ?? [];

        return collect(is_array($ids) ? $ids : [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AIImportQuestion::class, 'ai_import_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AIImportLog::class, 'ai_import_id');
    }

    public function paperCollection(): HasOne
    {
        return $this->hasOne(AIPaperCollection::class, 'ai_import_id');
    }

    public function sourceForBookType(): string
    {
        return self::BOOK_TYPE_SOURCE_MAP[$this->book_type] ?? 'exercise';
    }

    public function refreshCounters(): void
    {
        $this->update([
            'questions_found' => $this->questions()->count(),
            'approved_count' => $this->questions()->where('status', 'approved')->count(),
            'rejected_count' => $this->questions()->where('status', 'rejected')->count(),
            'imported_count' => $this->questions()->where('status', 'imported')->count(),
            'failed_count' => $this->questions()->where('status', 'failed')->count(),
            'duplicate_count' => $this->questions()->where('is_duplicate', true)->count(),
        ]);

        $this->broadcastProgress();
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(): array
    {
        $import = $this->exists ? $this->fresh() ?? $this : $this;

        return [
            'id' => $import->id,
            'status' => $import->status,
            'progress_percent' => $import->progress_percent,
            'total_chunks' => $import->total_chunks,
            'processed_chunks' => $import->processed_chunks,
            'questions_found' => $import->questions_found,
            'approved_count' => $import->approved_count,
            'rejected_count' => $import->rejected_count,
            'imported_count' => $import->imported_count,
            'failed_count' => $import->failed_count,
            'duplicate_count' => $import->duplicate_count,
            'error_message' => self::teacherFacingError($import->error_message),
        ];
    }

    public static function teacherFacingError(?string $error): ?string
    {
        if ($error === null || $error === '') {
            return $error;
        }

        if (preg_match('/website|web site|gemini|preferred site|api key|rate limit/i', $error)) {
            return 'No questions were produced. Try different chapters or counts.';
        }

        return $error;
    }

    public function broadcastProgress(): void
    {
        try {
            event(new AIImportProgressUpdated($this->fresh() ?? $this));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
