<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPaper extends Model
{
    protected $fillable = [
        'institution_id',
        'user_id',
        'title',
        'config_snapshot',
        'layout_snapshot',
        'status',
    ];

    protected $casts = [
        'config_snapshot' => 'array',
        'layout_snapshot' => 'array',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

