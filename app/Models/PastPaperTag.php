<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PastPaperTag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'question_id',
        'board_name',
        'year',
        'session',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

