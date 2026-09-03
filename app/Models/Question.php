<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Question extends Model
{
    protected $fillable = [
        'chapter_id',
        'topic_id',
        'type',
        'source',
        'text_en',
        'text_ur',
        'image_path',
        'has_parts',
        'parent_question_id',
        'is_active',
        'difficulty',
        'estimated_marks',
    ];

    protected $casts = [
        'has_parts' => 'boolean',
        'is_active' => 'boolean',
        'estimated_marks' => 'integer',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function mcqOptions(): HasOne
    {
        return $this->hasOne(McqOption::class);
    }

    public function pastPaperTag(): HasOne
    {
        return $this->hasOne(PastPaperTag::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Question::class, 'parent_question_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'parent_question_id');
    }
}
