<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\MobileServices\Models\MobileService;
use App\Models\User;

final readonly class MobileServicePolicy
{
    public function __construct(private PermissionChecker $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'mobile-service.view') || $this->permissions->allows($actor, 'mobile-service.manage');
    }

    public function view(User $actor, MobileService $service): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->permissions->allows($actor, 'mobile-service.manage');
    }

    public function update(User $actor, MobileService $service): bool
    {
        return $this->permissions->allows($actor, 'mobile-service.manage');
    }

    public function publish(User $actor, MobileService $service): bool
    {
        return $this->permissions->allows($actor, 'mobile-service.manage');
    }

    public function operate(User $actor, MobileService $service): bool
    {
        return $this->permissions->allows($actor, 'mobile-service.operate');
    }

    public function cancel(User $actor, MobileService $service): bool
    {
        return $this->permissions->allows($actor, 'mobile-service.manage');
    }
}
