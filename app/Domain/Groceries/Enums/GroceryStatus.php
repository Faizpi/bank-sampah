<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Enums;

enum GroceryStatus: string
{
    case PendingVerification = 'menunggu_verifikasi';
    case Approved = 'disetujui';
    case Preparing = 'sedang_disiapkan';
    case ReadyForPickup = 'siap_diambil';
    case Completed = 'selesai';
    case Rejected = 'ditolak';
    case Cancelled = 'dibatalkan';
    case Expired = 'kedaluwarsa';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PendingVerification => in_array($next, [self::Approved, self::Rejected, self::Cancelled, self::Expired], true),
            self::Approved => in_array($next, [self::Preparing, self::Rejected, self::Cancelled, self::Expired], true),
            self::Preparing => in_array($next, [self::ReadyForPickup, self::Cancelled, self::Expired], true),
            self::ReadyForPickup => in_array($next, [self::Completed, self::Cancelled, self::Expired], true),
            self::Completed, self::Rejected, self::Cancelled, self::Expired => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled, self::Expired], true);
    }
}
