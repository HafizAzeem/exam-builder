<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasActiveStatus
{
    public function initializeHasActiveStatus(): void
    {
        $this->mergeFillable(['is_active']);
        $this->mergeCasts(['is_active' => 'boolean']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_active'), true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_active'), false);
    }
}
