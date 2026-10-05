<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum UserRole: string
{
    case Citizen = 'warga';
    case Officer = 'petugas';
    case Treasurer = 'bendahara';
    case Admin = 'admin';

    /** @return array<string, string> */
    public static function options(bool $staffOnly = false): array
    {
        $options = [];
        foreach (self::cases() as $role) {
            if (! $staffOnly || $role !== self::Citizen) {
                $options[$role->value] = ucfirst($role->value);
            }
        }

        return $options;
    }
}
