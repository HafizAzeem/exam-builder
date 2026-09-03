<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIPaperCollection extends Model
{
    protected $table = 'ai_paper_collections';

    public const STATUSES = [
        'queued',
        'searching',
        'collecting_sources',
        'downloading',
        'extracting',
        'processing',
        'classifying',
        'review',
        'importing',
        'completed',
        'failed',
    ];

    public const STAGE_WEIGHTS = [
        'queued' => 0,
        'searching' => 10,
        'collecting_sources' => 20,
        'downloading' => 40,
        'extracting' => 55,
        'processing' => 75,
        'classifying' => 90,
        'review' => 100,
        'importing' => 100,
        'completed' => 100,
        'failed' => 100,
    ];

    protected $fillable = [
        'user_id',
        'grade_id',
        'subject_id',
        'ai_import_id',
        'board',
        'year',
        'session',
        'paper_type',
        'language',
        'country',
        'max_results',
        'keywords_override',
        'generated_queries',
        'status',
        'progress_stage',
        'progress_percent',
        'urls_visited',
        'successful_sources',
        'failed_sources',
        'ocr_required_sources',
        'questions_found',
        'duplicate_count',
        'imported_count',
        'input_tokens',
        'output_tokens',
        'processing_time_ms',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'year' => 'integer',
        'max_results' => 'integer',
        'generated_queries' => 'array',
        'progress_percent' => 'integer',
        'urls_visited' => 'integer',
        'successful_sources' => 'integer',
        'failed_sources' => 'integer',
        'ocr_required_sources' => 'integer',
        'questions_found' => 'integer',
        'duplicate_count' => 'integer',
        'imported_count' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'processing_time_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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

    public function import(): BelongsTo
    {
        return $this->belongsTo(AIImport::class, 'ai_import_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(AIPaperSource::class, 'ai_paper_collection_id');
    }

    public function markStage(string $stage, ?int $percent = null): void
    {
        $this->update([
            'status' => in_array($stage, self::STATUSES, true) ? $stage : $this->status,
            'progress_stage' => $stage,
            'progress_percent' => $percent ?? (self::STAGE_WEIGHTS[$stage] ?? $this->progress_percent),
        ]);
    }

    public function refreshCounters(): void
    {
        $sources = $this->sources();

        $this->update([
            'urls_visited' => (clone $sources)->count(),
            'successful_sources' => (clone $sources)->whereIn('status', ['extracted', 'processed'])->count(),
            'failed_sources' => (clone $sources)->where('status', 'failed')->count(),
            'ocr_required_sources' => (clone $sources)->where('status', 'ocr_required')->count(),
            'questions_found' => $this->import
                ? $this->import->questions()->count()
                : $this->questions_found,
            'duplicate_count' => $this->import
                ? $this->import->questions()->where('is_duplicate', true)->count()
                : $this->duplicate_count,
            'imported_count' => $this->import
                ? $this->import->questions()->where('status', 'imported')->count()
                : $this->imported_count,
        ]);
    }
}
