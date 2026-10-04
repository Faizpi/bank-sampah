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

    public function ensureAllowed(string $phone, string $ip): void
    {
        if (RateLimiter::tooManyAttempts(self::key($phone, $ip), self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['phone' => self::FAILURE_MESSAGE]);
        }
    }

    public function recordFailure(string $phone, string $ip): void
    {
        RateLimiter::hit(self::key($phone, $ip), self::DECAY_SECONDS);
    }

    public static function key(string $phone, string $ip): string
    {
        return 'login:'.hash('sha256', PhoneNumber::normalize($phone).'|'.$ip);
    }
}
