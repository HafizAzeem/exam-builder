<?php

namespace App\Http\Middleware;

use App\Models\Institution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantResolver
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->institution_id) {
            $institution = Institution::find($user->institution_id);
            $request->attributes->set('institution', $institution);
            app()->instance(Institution::class, $institution);
        }

        return $next($request);
    }
}
