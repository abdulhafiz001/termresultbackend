<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'role' => \App\Http\Middleware\RequireRole::class,
            'platform_admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
        ]);

        // Ensure tenant DB is selected before Sanctum/auth middleware runs.
        $middleware->prependToGroup('api', \App\Http\Middleware\IdentifyTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
