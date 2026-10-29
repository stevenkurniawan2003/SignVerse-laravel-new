<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Percayai header X-Forwarded-* dari reverse proxy (HTTPS di depan container).
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        // User yang sudah login dan membuka /login atau /register diarahkan ke dashboard sesuai role.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->dashboardRoute());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
