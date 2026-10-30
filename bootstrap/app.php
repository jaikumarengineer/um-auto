<?php

use App\Http\Middleware\Auth\AdminAuthCheck;
use App\Http\Middleware\Auth\AdminAuthRedirect;
use App\Http\Middleware\Auth\EnsureAdminOnly;
use App\Http\Middleware\Auth\EnsureModuleAccess;
use App\Http\Middleware\Auth\EnsureQualityRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: [
            __DIR__.'/../routes/web.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->web(append: [
            // Global Middleware
        ]);

        $middleware->redirectGuestsTo(fn () => route('admin::login'));

        $middleware->alias([
            'admin.check' => AdminAuthCheck::class,
            'admin.redirect' => AdminAuthRedirect::class,
            'admin.only' => EnsureAdminOnly::class,
            'module.access' => EnsureModuleAccess::class,
            'quality.role' => EnsureQualityRole::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
