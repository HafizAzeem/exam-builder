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
        /** @var Response $response */
        $response = $next($request);

        // Only log meaningful authenticated actions; keep the log clean.
        if (! $request->user()) {
            return $response;
        }

        if ($request->is('api/*')) {
            return $response;
        }

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            ActivityLogger::log($request, 'request.mutated', [
                'method' => $request->method(),
                'path' => '/'.$request->path(),
            ]);
        }

        return $response;
    }
}

