<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogger
{
    public static function log(Request $request, string $action, array $meta = []): void
    {
        $user = $request->user();

        if (! $user || ! $user->institution_id || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        ActivityLog::create([
            'institution_id' => $user->institution_id,
            'user_id' => $user->id,
            'action' => $action,
            'meta' => array_merge($meta, [
                'actor_name' => $user->name,
                'actor_role' => $user->hasRole('institution_admin') ? 'School admin' : ($user->hasRole('teacher') ? 'Teacher' : 'School user'),
                'method' => $request->method(), 'path' => '/'.$request->path(),
                'route' => $request->route()?->getName(),
                'target' => ActivityDescription::target($request),
            ]),
            'ip_address' => $request->ip() ?? '0.0.0.0',
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
        $request->attributes->set('activity_recorded', true);
    }
}
