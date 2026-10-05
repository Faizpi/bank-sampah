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
        $roles = $user->roles()->with('permissions')->orderBy('roles.id')->get();
        $grants = $roles->map(fn ($role): array => [$role->id, $role->permissions->pluck('id')->sort()->values()->all()])->all();

        return hash('sha256', json_encode([$user->id, $user->status->value, $grants], JSON_THROW_ON_ERROR));
    }
}
