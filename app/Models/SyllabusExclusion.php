<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyllabusExclusion extends Model
{
    protected $guarded = [];
    protected $casts = ['verified_at' => 'date'];
    public function chapter(): BelongsTo { return $this->belongsTo(SyllabusChapter::class, 'syllabus_chapter_id'); }
}
