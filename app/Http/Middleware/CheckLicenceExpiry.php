<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicenceExpiry
{
    /** Routes blocked when licence expired (paper generation / mutation). */
    protected array $blockedPrefixes = [
        'builder',
        'builder/*',
        'api/builder/*',
        'papers',
        'papers/*',
        'editor/*',
        'admin/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $institution = $request->attributes->get('institution');

        if (! $institution) {
            return $next($request);
        }

        if ($institution->expiry_date->isFuture()) {
            return $next($request);
        }

        foreach ($this->blockedPrefixes as $prefix) {
            if ($request->is($prefix)) {
                // Allow reading/printing existing papers.
                if ($request->is('editor/*') && $request->method() === 'GET') {
                    return $next($request);
                }
                if ($request->is('editor/*/print') && $request->method() === 'GET') {
                    return $next($request);
                }

                return redirect()
                    ->route('dashboard')
                    ->with('error', 'Your institution licence has expired. You can view/print saved papers but cannot create or edit new papers.');
            }
        }

        return $next($request);
    }
}
