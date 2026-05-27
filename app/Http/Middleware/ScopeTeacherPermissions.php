<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopeTeacherPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher')) {
            $request->attributes->set('teacher_permissions', $user->teacherPermission);
        }

        return $next($request);
    }
}
