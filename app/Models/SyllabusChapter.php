<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyllabusChapter extends Model
{
    protected $guarded = [];
    protected $casts = ['topics' => 'array', 'verified_at' => 'date'];
    public function edition(): BelongsTo { return $this->belongsTo(SyllabusEdition::class, 'syllabus_edition_id'); }
    public function exclusions(): HasMany { return $this->hasMany(SyllabusExclusion::class); }
}
