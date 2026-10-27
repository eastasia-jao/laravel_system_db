<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\ActiveUserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $request->session()->put('last_user_activity_at', now()->timestamp);
        $activeSessionToken = Str::random(64);
        $request->session()->put('active_user_session_token', $activeSessionToken);

        if (! ActiveUserSession::claim((int) $request->user()->id, $activeSessionToken)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                $request->filled('email') && ! $request->filled('username') ? 'email' : 'username' => ActiveUserSession::ALREADY_LOGGED_IN_MESSAGE,
            ]);
        }

        // Ensure this points back to 'dashboard'
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user()) {
            ActiveUserSession::release(
                (int) $request->user()->id,
                (string) $request->session()->get('active_user_session_token')
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirect = redirect()->route('login');

        return $request->boolean('idle_logout')
            ? $redirect->with('warning', 'You were signed out after '.max(1, (int) config('session.lifetime')).' minutes of inactivity. Please sign in again.')
            : $redirect;
    }

    /**
     * End an idle session without depending on a potentially expired CSRF token.
     */
    public function timeout(Request $request): RedirectResponse
    {
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
            'You were signed out after '.max(1, (int) config('session.lifetime')).' minutes of inactivity. Please sign in again.'
        );
    }
}
