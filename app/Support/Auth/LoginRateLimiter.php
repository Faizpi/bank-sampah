<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class LoginRateLimiter
{
    public const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    private const FAILURE_MESSAGE = 'Kredensial tidak valid atau akun tidak dapat digunakan.';

    public function ensureAllowed(string $username, string $ip): void
    {
        if (RateLimiter::tooManyAttempts(self::key($username, $ip), self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['username' => self::FAILURE_MESSAGE]);
        }
    }

    public function recordFailure(string $username, string $ip): void
    {
        RateLimiter::hit(self::key($username, $ip), self::DECAY_SECONDS);
    }

    public static function key(string $username, string $ip): string
    {
        return 'login:'.hash('sha256', Username::normalize($username).'|'.$ip);
    }
}
