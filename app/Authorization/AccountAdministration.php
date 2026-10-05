<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;

final readonly class AccountAdministration
{
    public function __construct(private PermissionChecker $permissions) {}

    public function canManage(User $actor, User $subject): bool
    {
        return $this->permissions->allows($actor, 'user.update');
    }
}
