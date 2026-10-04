<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Models\User;

abstract readonly class WasteMasterPolicy
{
    public function __construct(private PermissionChecker $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'waste.view')
            || $this->permissions->allows($actor, 'waste.manage');
    }

    public function view(User $actor, object $record): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->permissions->allows($actor, 'waste.manage');
    }

    public function update(User $actor, object $record): bool
    {
        return $this->permissions->allows($actor, 'waste.manage');
    }

    public function deactivate(User $actor, object $record): bool
    {
        return $this->permissions->allows($actor, 'waste.manage');
    }

    public function delete(User $actor, object $record): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
