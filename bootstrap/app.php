<?php

use App\Services\Guardian\ErrorCounter;
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
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum', 'verified']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Feeds the guardian's hourly error counter (ErrorRateCheck). The
        // counter swallows its own failures, so this cannot break reporting.
        $exceptions->report(function (Throwable $e): void {
            app(ErrorCounter::class)->record($e);
        });
    })->create();
