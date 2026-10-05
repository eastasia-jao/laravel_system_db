<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class LoginRequest extends FormRequest
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['nullable', 'required_without:email', 'string'],
            'email' => ['nullable', 'required_without:username', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = $this->loginIdentifier();
        $field = $this->loginField();

        if (! Auth::attempt([
            $field => $login,
            'password' => $this->input('password'),
            'status' => 'active',
        ], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::LOCKOUT_SECONDS);

            if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_LOGIN_ATTEMPTS)) {
                event(new Lockout($this));

                throw ValidationException::withMessages([
                    $field => $this->lockoutMessage(),
                ]);
            }

            throw ValidationException::withMessages([
                $field => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_LOGIN_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            $this->loginField() => $this->lockoutMessage(),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $login = Str::lower($this->loginIdentifier());
        $userId = User::query()
            ->whereRaw('LOWER(username) = ?', [$login])
            ->orWhereRaw('LOWER(email) = ?', [$login])
            ->value('id');

        return $userId
            ? 'login-lock:user:'.$userId
            : 'login-lock:identifier:'.Str::transliterate($login);
    }

    private function loginIdentifier(): string
    {
        return trim((string) ($this->input('username') ?: $this->input('email')));
    }

    private function loginField(): string
    {
        return filter_var($this->loginIdentifier(), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    }

    private function lockoutMessage(): string
    {
        return 'This account has been temporarily locked after 5 unsuccessful sign-in attempts. Please contact the administrator.';
    }
}
