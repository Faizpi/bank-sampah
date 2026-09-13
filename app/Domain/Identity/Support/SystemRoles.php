<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Models\Role;

/**
 * System roles that are core to the domain model and therefore cannot be
 * deleted from the back-office.
 */
final readonly class SystemRoles
{
    /** @var list<string> */
    public const NAMES = [
        'warga',
        'petugas',
        'bendahara',
        'admin',
        'superadmin',
    ];

    /**
     * Roles that carry broad back-office authority. Only an actor with
     * `role.manage` may assign these, so a plain `user.create` holder cannot
     * escalate an account into the administration layer.
     *
     * Only the seeded `admin`/`superadmin` slugs are protected by name; any
     * other role is judged by the capabilities it actually grants.
     *
     * @var list<string>
     */
    public const PRIVILEGED_NAMES = [
        'admin',
        'superadmin',
    ];

    /**
     * Capabilities that an actor with `user.create` must never be able to
     * delegate, because they exceed the scope of ordinary user provisioning:
     * role administration, backup/restore, irreversible financial mutations,
     * system settings/maintenance, and the account-takeover surface.
     *
     * @var list<string>
     */
    public const NON_DELEGABLE_PERMISSIONS = [
        'role.manage',
        'backup.restore',
        'transaction.reverse',
        'ledger.adjust',
        'system.settings.manage',
        'system.maintenance',
        'user.reset-password',
        'session.revoke',
        'user.view.all',
        'user.create', 'user.update', 'user.activate', 'user.verify', 'user.reject',
        'backoffice.access', 'role.view', 'region.manage', 'waste.manage', 'price.manage',
        'pickup.review', 'pickup.schedule', 'withdrawal.approve',
        'grocery.package.manage', 'grocery.approve',
        'announcement.manage', 'announcement.publish', 'target.manage', 'target.publish',
        'statistics.public.manage', 'qr-verification.rotate', 'audit.view',
        'deposit.approve', 'transaction.correct', 'reconciliation.view',
        'reconciliation.create', 'reconciliation.approve', 'backup.run', 'backup.view',
        'audit.retention.execute', 'media.retention.execute',
    ];

    public static function contains(string $roleName): bool
    {
        return in_array($roleName, self::NAMES, true);
    }

    public static function isPrivileged(string $roleName): bool
    {
        return in_array($roleName, self::PRIVILEGED_NAMES, true);
    }

    /**
     * Whether assigning this role exceeds what a plain `user.create` holder may
     * delegate. The role name is a secondary signal only: a custom role whose
     * effective permissions include any non-delegable capability (including the
     * seeded admin baseline) is treated as privileged regardless of its name.
     *
     * @param  list<string>  $permissionNames
     */
    public static function grantsNonDelegableAccess(string $roleName, array $permissionNames): bool
    {
        if (self::isPrivileged($roleName)) {
            return true;
        }

        return array_intersect($permissionNames, self::NON_DELEGABLE_PERMISSIONS) !== [];
    }

    /** @return list<string> */
    public static function permissionNames(Role $role): array
    {
        if ($role->relationLoaded('permissions')) {
            return $role->permissions->pluck('name')->all();
        }

        return $role->permissions()->pluck('permissions.name')->all();
    }
}
