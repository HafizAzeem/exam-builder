<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantRequest extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['reserved_at' => 'datetime', 'provider_started_at' => 'datetime', 'expires_at' => 'datetime', 'finished_at' => 'datetime'];
    public function institution(): BelongsTo { return $this->belongsTo(Institution::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function aiImport(): BelongsTo { return $this->belongsTo(AIImport::class); }
}
