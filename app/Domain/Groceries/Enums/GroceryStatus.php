<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Enums;

enum GroceryStatus: string
{
    case Waiting = 'menunggu_petugas';
    case Processing = 'diproses_petugas';
    case ReadyForPickup = 'siap_diambil';
    case Completed = 'selesai';
    case Unavailable = 'tidak_tersedia';
    case Cancelled = 'dibatalkan';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Waiting => in_array($next, [self::Processing, self::Unavailable, self::Cancelled], true),
            self::Processing => in_array($next, [self::Waiting, self::ReadyForPickup, self::Completed, self::Cancelled], true),
            self::ReadyForPickup => in_array($next, [self::Waiting, self::Completed, self::Cancelled], true),
            default => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Unavailable, self::Cancelled], true);
    }
}
