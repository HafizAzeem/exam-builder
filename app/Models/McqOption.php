<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McqOption extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'question_id',
        'option_a_en',
        'option_a_ur',
        'option_b_en',
        'option_b_ur',
        'option_c_en',
        'option_c_ur',
        'option_d_en',
        'option_d_ur',
        'correct_option',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

