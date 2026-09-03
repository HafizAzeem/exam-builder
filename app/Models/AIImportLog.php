<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIImportLog extends Model
{
    protected $table = 'ai_import_logs';

    protected $fillable = [
        'ai_import_id',
        'chunk_index',
        'prompt',
        'response',
        'processing_time_ms',
        'input_tokens',
        'output_tokens',
        'error',
        'retry_count',
        'status',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'processing_time_ms' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'retry_count' => 'integer',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(AIImport::class, 'ai_import_id');
    }
}
