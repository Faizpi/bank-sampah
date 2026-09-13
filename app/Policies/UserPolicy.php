<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Identity\Support\SystemRoles;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    public function createWithRole(User $actor, Role $role): Response
    {
        return $this->create($actor)
            && (! SystemRoles::grantsNonDelegableAccess($role->name, SystemRoles::permissionNames($role)) || $this->permissions->allows($actor, 'role.manage'))
                ? Response::allow()
                : Response::deny('Anda hanya boleh menetapkan peran non-privileged tanpa izin role.manage.');
    }

    public function update(User $actor, User $subject): bool
    {
        return $actor->is($subject)
            ? $this->permissions->allows($actor, 'profile.update')
            : $this->visibleUsers->canView($actor, $subject) && $this->permissions->allows($actor, 'user.update');
    }

    public function manageView(User $actor, User $subject): bool
    {
        return $this->permissions->allows($actor, 'user.view')
            && $this->visibleUsers->canView($actor, $subject, ...UserStatus::cases());
    }

    public function manageUpdate(User $actor, User $subject): bool
    {
        return ! $actor->is($subject)
            && $this->manageView($actor, $subject)
            && $this->permissions->allows($actor, 'user.update');
    }

    public function updateCustomer(User $actor, User $subject): bool
    {
        return ! $actor->is($subject)
            && $this->manageView($actor, $subject)
            && $this->permissions->allows($actor, 'customer.update');
    }

    public function deactivate(User $actor, User $subject): bool
    {
        return ! $actor->is($subject)
            && $this->visibleUsers->canView($actor, $subject)
            && $this->permissions->allows($actor, 'user.update');
    }

    public function activate(User $actor, User $subject): bool
    {
        return ! $actor->is($subject)
            && $this->visibleUsers->canView($actor, $subject, UserStatus::Inactive)
            && $this->permissions->allows($actor, 'user.activate');
    }

    public function verify(User $actor, User $subject): bool
    {
        return ! $actor->is($subject)
            && $this->visibleUsers->canView($actor, $subject, UserStatus::PendingVerification, UserStatus::Active)
            && $this->permissions->allows($actor, 'user.verify');
    }

    public function reject(User $actor, User $subject): bool
    {
        return ! $actor->is($subject)
            && $this->visibleUsers->canView($actor, $subject, UserStatus::PendingVerification, UserStatus::Active)
            && $this->permissions->allows($actor, 'user.reject');
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
