<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function required(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.change-password-required');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);

        $wasRequired = $request->user()->must_change_password;

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        if ($wasRequired) {
            $request->session()->regenerate();

            return redirect()->route('dashboard')->with('status', 'password-updated');
        }

        return back()->with('status', 'password-updated');
    }
}
