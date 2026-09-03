<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIPaperSource extends Model
{
    protected $table = 'ai_paper_sources';

    protected $fillable = [
        'ai_paper_collection_id',
        'url',
        'normalized_url',
        'title',
        'snippet',
        'content_type',
        'content_hash',
        'stored_path',
        'file_size',
        'status',
        'extracted_text',
        'http_status',
        'questions_extracted',
        'retry_count',
        'processing_time_ms',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'http_status' => 'integer',
        'questions_extracted' => 'integer',
        'retry_count' => 'integer',
        'processing_time_ms' => 'integer',
        'meta' => 'array',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(AIPaperCollection::class, 'ai_paper_collection_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AIImportQuestion::class, 'ai_paper_source_id');
    }

    public function isRetryable(): bool
    {
        return in_array($this->status, ['failed', 'ocr_required'], true);
    }
}
