<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    ];

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
    }
}
