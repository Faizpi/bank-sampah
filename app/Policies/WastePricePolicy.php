<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Models\User;

final readonly class WastePricePolicy
{
    public function __construct(private PermissionChecker $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'price.view')
            || $this->permissions->allows($actor, 'price.manage');
    }

    public function view(User $actor, object $record): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->permissions->allows($actor, 'price.manage');
    }

    public function update(User $actor, object $record): bool
    {
        return false;
    }

    public function delete(User $actor, object $record): bool
    {
        return false;
    }
}
