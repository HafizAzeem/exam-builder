<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Institution extends Model
{
    protected $fillable = [
        'name',
        'logo_path',
        'address',
        'phone',
        'owner_name',
        'city',
        'expiry_date',
        'license_type',
        'board_id',
        'subscription_plan',
        'trial_started_at',
        'access_expires_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'trial_started_at' => 'datetime',
        'access_expires_at' => 'datetime',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function accessExpiry(): ?Carbon
    {
        return $this->access_expires_at ?? $this->expiry_date?->copy()->endOfDay();
    }

    public function hasAccess(): bool
    {
        return $this->accessExpiry()?->isFuture() ?? false;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function savedPapers(): HasMany
    {
        return $this->hasMany(SavedPaper::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
