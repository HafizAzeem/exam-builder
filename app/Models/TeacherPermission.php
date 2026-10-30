<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherPermission extends Model
{
    protected $fillable = [
        'user_id',
        'can_use_assistant',
        'allowed_grades',
        'allowed_subjects',
        'allowed_categories',
    ];

    protected $casts = [
        'can_use_assistant' => 'boolean',
        'allowed_grades' => 'array',
        'allowed_subjects' => 'array',
        'allowed_categories' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeQuestions($query): void
    {
        if (is_array($this->allowed_grades)) {
            $query->whereHas('chapter.subject.grade', fn ($q) => $q->whereIn('number', $this->allowed_grades));
        }
        if (is_array($this->allowed_subjects)) {
            $query->whereHas('chapter', fn ($q) => $q->whereIn('subject_id', $this->allowed_subjects));
        }
        if (is_array($this->allowed_categories)) {
            $query->whereIn('source', $this->allowed_categories);
        }
    }
}
