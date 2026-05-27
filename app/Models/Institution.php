<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function savedPapers(): HasMany
    {
        return $this->hasMany(SavedPaper::class);
    }
}

