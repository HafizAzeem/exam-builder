<?php

use App\Http\Middleware\CheckLicenceExpiry;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogActivity;
use App\Http\Middleware\ScopeTeacherPermissions;
use App\Http\Middleware\TenantResolver;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            LogActivity::class,
        ]);

        $middleware->alias([
            'tenant' => TenantResolver::class,
            'check.licence' => CheckLicenceExpiry::class,
            'teacher.scope' => ScopeTeacherPermissions::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
