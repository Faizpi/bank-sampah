<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

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

    public static function contains(string $roleName): bool
    {
        return in_array($roleName, self::NAMES, true);
    }
}
