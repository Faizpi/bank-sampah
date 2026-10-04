<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Models\User;

final readonly class UserPolicy
{
    public function __construct(
        private PermissionChecker $permissions,
        private VisibleUsers $visibleUsers,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'user.view');
    }

    public function view(User $actor, User $subject): bool
    {
        return $actor->is($subject)
            ? $this->permissions->allows($actor, 'profile.view')
            : $this->visibleUsers->canView($actor, $subject);
    }

    public function create(User $actor): bool
    {
        return $this->permissions->allows($actor, 'user.create');
    }

    public function update(User $actor, User $subject): bool
    {
        return $actor->is($subject)
            ? $this->permissions->allows($actor, 'profile.update')
            : $this->visibleUsers->canView($actor, $subject) && $this->permissions->allows($actor, 'user.update');
    }

    public function activate(User $actor, User $subject): bool
    {
        return ! $actor->is($subject)
            && $this->visibleUsers->canView($actor, $subject)
            && $this->permissions->allows($actor, 'user.activate');
    }

    public function verify(User $actor, User $subject): bool
    {
        return ! $actor->is($subject) && $this->permissions->allows($actor, 'user.verify');
    }

    public function reject(User $actor, User $subject): bool
    {
        return ! $actor->is($subject) && $this->permissions->allows($actor, 'user.reject');
    }

    public function resetPassword(User $actor, User $subject): bool
    {
        return ! $actor->is($subject)
            && $this->visibleUsers->canView($actor, $subject)
            && $this->permissions->allows($actor, 'user.reset-password');
    }

    public function revokeSession(User $actor, User $subject): bool
    {
        return ! $actor->is($subject)
            && $this->visibleUsers->canView($actor, $subject)
            && $this->permissions->allows($actor, 'session.revoke');
    }
}
