<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Support\SystemRoles;
use App\Models\User;

final readonly class RolePolicy
{
    public function __construct(private PermissionChecker $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'role.view')
            || $this->permissions->allows($actor, 'role.manage');
    }

    public function view(User $actor, Role $role): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->permissions->allows($actor, 'role.manage');
    }

    public function update(User $actor, Role $role): bool
    {
        return $this->permissions->allows($actor, 'role.manage');
    }

    public function delete(User $actor, Role $role): bool
    {
        return $this->permissions->allows($actor, 'role.manage')
            && ! SystemRoles::contains($role->name);
    }

    public function deleteAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'role.manage');
    }
}
