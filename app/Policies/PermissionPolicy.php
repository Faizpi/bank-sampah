<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Identity\Models\Permission;
use App\Models\User;

final readonly class PermissionPolicy
{
    public function __construct(private PermissionChecker $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'role.view')
            || $this->permissions->allows($actor, 'role.manage');
    }

    public function view(User $actor, Permission $permission): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Permission $permission): bool
    {
        return false;
    }

    public function delete(User $actor, Permission $permission): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
