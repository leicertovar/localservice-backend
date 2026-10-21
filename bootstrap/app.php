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
        // Rechaza tokens de cuentas desactivadas o eliminadas por su dueño
        $middleware->appendToGroup('api', \App\Http\Middleware\CuentaActiva::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
