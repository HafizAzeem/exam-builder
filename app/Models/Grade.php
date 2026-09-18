<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    use HasActiveStatus;

    public $timestamps = false;

    protected $fillable = [
        'number',
        'label_en',
        'label_ur',
        'is_active',
    ];

    protected $casts = [
        'number' => 'integer',
        'is_active' => 'boolean',
    ];

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
