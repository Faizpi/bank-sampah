<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;

final readonly class PermissionChecker
{
    public function allows(?User $user, string $permission): bool
    {
        if ($user === null || $permission === '' || $user->status !== UserStatus::Active || $user->trashed()) {
            return false;
        }

        return in_array($permission, RoleCapabilities::for($user->role), true);
    }
}
