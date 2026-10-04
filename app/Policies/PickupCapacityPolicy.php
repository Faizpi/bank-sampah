<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Models\User;

final readonly class PickupCapacityPolicy
{
    public function __construct(private PermissionChecker $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'pickup.capacity.manage');
    }

    public function view(User $actor, PickupCapacity $capacity): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, PickupCapacity $capacity): bool
    {
        return $this->viewAny($actor);
    }

    public function delete(User $actor, PickupCapacity $capacity): bool
    {
        return false;
    }
}
