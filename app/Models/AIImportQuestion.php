<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIImportQuestion extends Model
{
    protected $table = 'ai_import_questions';

    protected $fillable = [
        'ai_import_id',
        'ai_paper_source_id',
        'chapter_number',
        'chapter_title',
        'topic_title',
        'chapter_id',
        'topic_id',
        'match_status',
        'type',
        'source',
        'text_en',
        'text_ur',
        'mcq_options',
        'parts',
        'status',
        'is_duplicate',
        'duplicate_of_question_id',
        'imported_question_id',
        'review_notes',
        'raw_payload',
        'confidence_score',
        'difficulty',
        'estimated_marks',
        'source_url',
        'source_excerpt',
        'duplicate_score',
        'duplicate_diff',
    ];

    protected $casts = [
        'chapter_number' => 'integer',
        'mcq_options' => 'array',
        'parts' => 'array',
        'is_duplicate' => 'boolean',
        'raw_payload' => 'array',
        'confidence_score' => 'float',
        'estimated_marks' => 'integer',
        'duplicate_score' => 'float',
        'duplicate_diff' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(AIImport::class, 'ai_import_id');
    }

    public function paperSource(): BelongsTo
    {
        return $this->belongsTo(AIPaperSource::class, 'ai_paper_source_id');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'duplicate_of_question_id');
    }

    public function importedQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'imported_question_id');
    }

    public function isReadyToApprove(): bool
    {
        return $this->chapter_id !== null
            && ! in_array($this->match_status, ['unmatched_chapter'], true);
    }
}
