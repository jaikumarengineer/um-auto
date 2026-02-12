<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: [
            __DIR__ . '/../routes/web.php',
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->web(append: [
            \App\Http\Middleware\AdminLayoutMiddleware::class,
        ]);

        $middleware->alias([
            'admin.check' => \App\Http\Middleware\Auth\AdminAuthCheck::class,
            'admin.redirect' => \App\Http\Middleware\Auth\AdminAuthRedirect::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
