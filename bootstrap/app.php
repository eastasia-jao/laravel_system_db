<?php

use App\Http\Middleware\EnforceIdleSessionTimeout;
use App\Http\Middleware\EnsureUserBelongsToHub;
use App\Support\ActiveUserSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

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
        $middleware->validateCsrfTokens(except: [
            'session/timeout',
        ]);

        $middleware->alias([
            'hub.access' => EnsureUserBelongsToHub::class,
            'idle-timeout' => EnforceIdleSessionTimeout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            $request = request();
            $user = Auth::guard('web')->user();
            if ($user && $request->hasSession()) {
                ActiveUserSession::release(
                    (int) $user->id,
                    (string) $request->session()->get('active_user_session_token')
                );
            }

            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->with(
                'warning',
                'Your session expired. Please sign in again.'
            );
        });
    })->create();
