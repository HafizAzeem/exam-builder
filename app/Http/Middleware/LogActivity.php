<?php

namespace App\Http\Middleware;

use App\Support\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $previousErrors = $request->hasSession() ? $request->session()->get('errors') : null;
        /** @var Response $response */
        $response = $next($request);

        // Only log meaningful authenticated actions; keep the log clean.
        if (! $request->user()) {
            return $response;
        }

        if ($response->getStatusCode() >= 400 || $request->attributes->get('activity_recorded') || ($request->hasSession() && $request->session()->get('errors') !== $previousErrors && $request->session()->has('errors'))) {
            return $response;
        }

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            ActivityLogger::log($request, $request->route()?->getName() ?? 'request.mutated', [
                'method' => $request->method(),
                'path' => '/'.$request->path(),
            ]);
        }

        return $response;
    }
}
