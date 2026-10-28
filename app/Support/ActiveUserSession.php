<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class ActiveUserSession
{
    public const ALREADY_LOGGED_IN_MESSAGE = 'This account is already logged in on another device or browser. Sign out there or wait for the inactive session to expire.';

    public static function hasActiveSession(int $userId): bool
    {
        return filled(Cache::get(self::key($userId)));
    }

    public static function claim(int $userId, string $sessionToken): bool
    {
        if (Cache::add(self::key($userId), $sessionToken, self::expiresAt())) {
            return true;
        }

        if (! self::matches($userId, $sessionToken)) {
            return false;
        }

        Cache::put(self::key($userId), $sessionToken, self::expiresAt());

        return true;
    }

    public static function takeOver(int $userId, string $sessionToken): bool
    {
        return Cache::put(self::key($userId), $sessionToken, self::expiresAt());
    }

    public static function ownsOrCanClaim(int $userId, string $sessionToken): bool
    {
        $currentSessionToken = Cache::get(self::key($userId));
        if (blank($currentSessionToken)) {
            return Cache::add(self::key($userId), $sessionToken, self::expiresAt())
                || self::matches($userId, $sessionToken);
        }

        return hash_equals((string) $currentSessionToken, $sessionToken);
    }

    public static function refresh(int $userId, string $sessionToken): void
    {
        if (self::matches($userId, $sessionToken)) {
            Cache::put(self::key($userId), $sessionToken, self::expiresAt());
        }
    }

    public static function release(int $userId, string $sessionToken): void
    {
        if (self::matches($userId, $sessionToken)) {
            Cache::forget(self::key($userId));
        }
    }

    private static function matches(int $userId, string $sessionToken): bool
    {
        $currentSessionToken = Cache::get(self::key($userId));

        return filled($currentSessionToken)
            && hash_equals((string) $currentSessionToken, $sessionToken);
    }

    private static function key(int $userId): string
    {
        return 'auth:active-session:user:'.$userId;
    }

    private static function expiresAt(): \DateTimeInterface
    {
        return now()->addMinutes(max(1, (int) config('session.lifetime')));
    }
}
