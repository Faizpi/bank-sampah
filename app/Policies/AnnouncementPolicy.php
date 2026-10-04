<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Communication\Models\Announcement;
use App\Domain\Communication\Services\AnnouncementService;
use App\Models\User;

final readonly class AnnouncementPolicy
{
    public function __construct(private PermissionChecker $permissions, private AnnouncementService $announcements) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'announcement.view') || $this->permissions->allows($actor, 'announcement.manage');
    }

    public function view(User $actor, Announcement $announcement): bool
    {
        return $this->announcements->canView($actor, $announcement);
    }

    public function create(User $actor): bool
    {
        return $this->permissions->allows($actor, 'announcement.manage');
    }

    public function update(User $actor, Announcement $announcement): bool
    {
        return $this->permissions->allows($actor, 'announcement.manage');
    }

    public function publish(User $actor, Announcement $announcement): bool
    {
        return $this->permissions->allows($actor, 'announcement.publish');
    }
}
