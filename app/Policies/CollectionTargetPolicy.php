<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Programs\Models\CollectionTarget;
use App\Models\User;

final readonly class CollectionTargetPolicy
{
    public function __construct(private PermissionChecker $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'target.view') || $this->permissions->allows($actor, 'target.manage');
    }

    public function view(User $actor, CollectionTarget $target): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->permissions->allows($actor, 'target.manage');
    }

    public function update(User $actor, CollectionTarget $target): bool
    {
        return $this->permissions->allows($actor, 'target.manage');
    }

    public function activate(User $actor, CollectionTarget $target): bool
    {
        return $this->permissions->allows($actor, 'target.publish');
    }

    public function close(User $actor, CollectionTarget $target): bool
    {
        return $this->permissions->allows($actor, 'target.manage');
    }

    public function cancel(User $actor, CollectionTarget $target): bool
    {
        return $this->permissions->allows($actor, 'target.manage');
    }
}
