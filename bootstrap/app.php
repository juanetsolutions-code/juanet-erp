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
    ->withMiddleware(function (Middleware $middleware) {
        // Log all exceptions at the HTTP layer
        $middleware->append(\App\Http\Middleware\ExceptionLoggingMiddleware::class);

        // Configure Sanctum Stateful API authentication
        $middleware->statefulApi();

        // Register custom tenant routing and context resolution alias
        $middleware->alias([
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'permission' => \App\Http\Middleware\RequirePermission::class,
        ]);

        // Standard SaaS request validation settings
        $middleware->validateSignatures([
            // Add signature routes if necessary
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            try {
                app(\App\Services\ExceptionLogServiceInterface::class)->log($e);
            } catch (\Throwable $ignore) {
                // Ignore errors during exception logging to prevent crash loops
            }
        });
    })->create();
