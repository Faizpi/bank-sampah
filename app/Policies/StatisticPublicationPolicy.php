<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Models\User;

final readonly class StatisticPublicationPolicy
{
    public function __construct(private PermissionChecker $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'statistics.public.manage');
    }

    public function view(User $actor, StatisticPublication $publication): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, StatisticPublication $publication): bool
    {
        return $this->viewAny($actor);
    }
}
