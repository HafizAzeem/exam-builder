<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'number',
        'label_en',
        'label_ur',
    ];

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}

