<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherPermission extends Model
{
    protected $fillable = [
        'user_id',
        'allowed_grades',
        'allowed_subjects',
        'allowed_categories',
    ];

    protected $casts = [
        'allowed_grades' => 'array',
        'allowed_subjects' => 'array',
        'allowed_categories' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

