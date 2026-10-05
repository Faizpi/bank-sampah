<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

final class SessionAccess
{
    public const STARTED_AT = 'auth.started_at';

    public const AUTHORIZATION = 'auth.authorization';

    public static function begin(Session $session, User $user): void
    {
        $session->put([
            self::STARTED_AT => now()->getTimestamp(),
            'auth.last_activity_at' => now()->getTimestamp(),
            self::AUTHORIZATION => self::fingerprint($user),
        ]);
    }

    public static function fingerprint(User $user): string
    {
        return hash('sha256', json_encode([$user->id, $user->status->value, $user->role->value], JSON_THROW_ON_ERROR));
    }
}
