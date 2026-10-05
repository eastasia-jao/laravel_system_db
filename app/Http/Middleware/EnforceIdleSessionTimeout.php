<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdleSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $timeoutSeconds = max(1, (int) config('session.lifetime')) * 60;
        $lastActivity = (int) $request->session()->get('last_user_activity_at', 0);

        if ($lastActivity > 0 && now()->timestamp - $lastActivity >= $timeoutSeconds) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'warning',
                'You were signed out after 15 minutes of inactivity. Please sign in again.'
            );
        }

        // Notification/import polling is automatic and must not keep a staff session alive.
        if (! $request->routeIs('notifications.feed', 'hub.products.import-status', 'api.notifications.index', 'api.hubs.products.import-status')) {
            $request->session()->put('last_user_activity_at', now()->timestamp);
        }

        return $next($request);
    }
}
