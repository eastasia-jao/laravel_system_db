<?php

namespace App\Http\Middleware;

use App\Support\ActiveUserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdleSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $timeoutMinutes = max(1, (int) config('session.lifetime'));
        $timeoutSeconds = $timeoutMinutes * 60;
        $lastActivity = (int) $request->session()->get('last_user_activity_at', 0);

        if ($lastActivity > 0 && now()->timestamp - $lastActivity >= $timeoutSeconds) {
            if ($request->user()) {
                ActiveUserSession::release(
                    (int) $request->user()->id,
                    (string) $request->session()->get('active_user_session_token')
                );
            }
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'warning',
                "You were signed out after {$timeoutMinutes} minutes of inactivity. Please sign in again."
            );
        }

        $activeSessionToken = (string) $request->session()->get('active_user_session_token');
        if ($request->user() && $activeSessionToken === '') {
            $activeSessionToken = Str::random(64);
            $request->session()->put('active_user_session_token', $activeSessionToken);
        }

        if ($request->user() && ! ActiveUserSession::ownsOrCanClaim((int) $request->user()->id, $activeSessionToken)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('warning', ActiveUserSession::REPLACED_SESSION_MESSAGE);
        }

        // Notification/import polling is automatic and must not keep a staff session alive.
        if (! $request->routeIs('notifications.feed', 'hub.products.import-status', 'api.notifications.index', 'api.hubs.products.import-status')) {
            $request->session()->put('last_user_activity_at', now()->timestamp);
            if ($request->user()) {
                ActiveUserSession::refresh((int) $request->user()->id, $activeSessionToken);
            }
        }

        return $next($request);
    }
}
