<?php

namespace App\Models;

use App\Services\SavedQuestionSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPaper extends Model
{
    protected static function booted(): void
    {
        static::creating(function (SavedPaper $paper) {
            $config = $paper->config_snapshot ?? [];
            if (! array_key_exists('question_records', $config)) {
                $config['question_records'] = SavedQuestionSnapshot::capture($config['question_ids'] ?? []);
                $paper->config_snapshot = $config;
            }
        });
    }

    protected $fillable = [
        'institution_id',
        'user_id',
        'title',
        'institute_snapshot',
        'config_snapshot',
        'layout_snapshot',
        'status',
    ];

    protected $casts = [
        'institute_snapshot' => 'array',
        'config_snapshot' => 'array',
        'layout_snapshot' => 'array',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
