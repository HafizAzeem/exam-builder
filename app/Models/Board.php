<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Board extends Model
{
    use HasActiveStatus;

    public function scopeDropdownOrder(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN region = ? THEN 0 ELSE 1 END', ['Punjab'])
            ->orderBy('sort_order')->orderBy('name');
    }

    protected $fillable = [
        'name',
        'region',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
