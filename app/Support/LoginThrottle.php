<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginThrottle
{
    public static function key(Request $request): string
    {
        // Shared across both login portals so switching portals cannot bypass the limit.
        return 'login:'.hash('sha256', Str::lower(trim((string) $request->input('email'))).'|'.$request->ip());
    }

    public static function ensureAllowed(Request $request): void
    {
        if (RateLimiter::tooManyAttempts(self::key($request), 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again in one minute.',
            ])->status(429);
        }
    }

    public static function failed(Request $request): void
    {
        RateLimiter::hit(self::key($request), 60);
    }

    public static function clear(Request $request): void
    {
        RateLimiter::clear(self::key($request));
    }
}
