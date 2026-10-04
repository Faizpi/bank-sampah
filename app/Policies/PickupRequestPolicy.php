<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Models\User;

final readonly class PickupRequestPolicy
{
    public function __construct(private PermissionChecker $permissions, private PickupService $pickups) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'pickup.view');
    }

    public function view(User $actor, PickupRequest $pickup): bool
    {
        return $this->pickups->canView($actor, $pickup);
    }

    public function review(User $actor, PickupRequest $pickup): bool
    {
        return $this->permissions->allows($actor, 'pickup.review') && $this->pickups->canView($actor, $pickup);
    }

    public function schedule(User $actor, PickupRequest $pickup): bool
    {
        return $this->permissions->allows($actor, 'pickup.schedule') && $this->pickups->canView($actor, $pickup);
    }

    public function execute(User $actor, PickupRequest $pickup): bool
    {
        return $this->permissions->allows($actor, 'pickup.execute') && $pickup->assigned_staff_id === $actor->id;
    }

    public function complete(User $actor, PickupRequest $pickup): bool
    {
        return $this->permissions->allows($actor, 'pickup.complete') && $pickup->assigned_staff_id === $actor->id;
    }

    public function cancel(User $actor, PickupRequest $pickup): bool
    {
        return $this->permissions->allows($actor, 'pickup.cancel') && ($pickup->customer_id === $actor->id || $this->pickups->canView($actor, $pickup));
    }
}
