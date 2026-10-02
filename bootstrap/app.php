<?php

use App\Http\Middleware\EnsureUserBelongsToHub;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Render terminates TLS at its proxy and forwards the original HTTPS
        // scheme to the application. Trust those headers so generated form
        // actions and redirects remain HTTPS instead of falling back to HTTP.
        $middleware->trustProxies(at: '*');

        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->alias([
            'hub.access' => EnsureUserBelongsToHub::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
