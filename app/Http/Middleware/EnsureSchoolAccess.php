<?php

namespace App\Http\Middleware;

use App\Support\SchoolAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->hasRole('super_admin')) {
            return $next($request);
        }
        if ($request->is('super-admin', 'super-admin/*')) {
            // Role middleware rejects non-administrators before any controller runs.
            return $next($request);
        }
        // These routes must remain reachable even when a deadline has passed.
        if ($request->routeIs('verification.*', 'logout', 'profile.*', 'password.*', 'access.status', 'home', 'public.*')) {
            return $next($request);
        }
        $status = SchoolAccess::status($user);
        if ($status['verification']['blocked'] ?? false) {
            return $this->blocked($request, 'email_verification_required', 'Please verify your email to continue.', 'verification.notice');
        }
        if ($status['subscription']['expired'] ?? false) {
            $readable = $request->isMethod('GET') && $request->routeIs(
                'dashboard', 'subscription.show', 'saved-papers.index', 'paper-history.index',
                'editor.show', 'editor.print', 'paper.print.signed', 'admin.profile', 'diagrams.image'
            );
            if (! $readable && ! $request->routeIs('admin.profile.update')) {
                return $this->blocked($request, 'subscription_expired', 'Your school access has expired. Please renew to continue.', 'subscription.show');
            }
        }

        return $next($request);
    }

    private function blocked(Request $request, string $code, string $message, string $route): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'code' => $code, 'redirect' => route($route)], 403);
        }

        return redirect()->route($route)->with('error', $message);
    }
}
