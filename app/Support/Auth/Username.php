<?php

declare(strict_types=1);

namespace App\Support\Auth;

final class Username
{
    public static function normalize(string $username): string
    {
        return strtolower(trim($username));
    }

    /**
     * Validate that the normalized username is usable.
     *
     * Usernames must be 3–60 characters and contain only lowercase letters,
     * digits, dot, underscore, or hyphen.
     */
    public static function isValid(string $normalized): bool
    {
        return (bool) preg_match('/\A[a-z0-9._-]{3,60}\z/', $normalized);
    }
}
