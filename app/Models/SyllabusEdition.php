<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyllabusEdition extends Model
{
    protected $guarded = [];
    protected $casts = ['verified_at' => 'date'];

    public function grade(): BelongsTo { return $this->belongsTo(Grade::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function chapters(): HasMany { return $this->hasMany(SyllabusChapter::class); }
}
