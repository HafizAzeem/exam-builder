<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogger
{
    public static function log(Request $request, string $action, array $meta = []): void
    {
        $user = $request->user();

        if (! $user || ! $user->institution_id) {
            return;
        }

        ActivityLog::create([
            'institution_id' => $user->institution_id,
            'user_id' => $user->id,
            'action' => $action,
            'meta' => $meta ?: null,
            'ip_address' => $request->ip() ?? '0.0.0.0',
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
