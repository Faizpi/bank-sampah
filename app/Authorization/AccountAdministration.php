<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Domain\Identity\Models\Role;
use App\Models\User;

final readonly class AccountAdministration
{
    public function __construct(private PermissionChecker $permissions) {}

    public function canAssign(User $actor, Role $role): bool
    {
        if ($this->permissions->allows($actor, 'role.manage')) {
            return true;
        }

        return in_array($role->name, ['warga', 'petugas', 'bendahara'], true)
            && ! $this->isPrivilegedRole($role);
    }

    public function canManage(User $actor, User $subject): bool
    {
        return $this->permissions->allows($actor, 'role.manage')
            || ! $subject->roles()->get()->contains(fn (Role $role): bool => $this->isPrivilegedRole($role));
    }

    private function isPrivilegedRole(Role $role): bool
    {
        return $role->name === 'superadmin' || $role->permissions()->where(function ($query): void {
            $query->whereIn('name', ['role.manage', 'ledger.adjust', 'deposit.approve', 'transaction.correct', 'transaction.reverse', 'audit.retention.execute', 'media.retention.execute'])
                ->orWhere('name', 'like', 'system.%')
                ->orWhere('name', 'like', 'backup.%')
                ->orWhere('name', 'like', 'reconciliation.%');
        })->exists();
    }
}
