<?php

declare(strict_types=1);

namespace App\Domain\Withdrawals\Enums;

enum WithdrawalStatus: string
{
    case PendingVerification = 'menunggu_verifikasi';
    case Approved = 'disetujui';
    case ReadyForPickup = 'siap_diambil';
    case Paid = 'sudah_dibayar';
    case Rejected = 'ditolak';
    case Cancelled = 'dibatalkan';
    case Expired = 'kedaluwarsa';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PendingVerification => in_array($next, [self::Approved, self::Rejected, self::Cancelled, self::Expired], true),
            self::Approved => in_array($next, [self::ReadyForPickup, self::Rejected, self::Cancelled, self::Expired], true),
            self::ReadyForPickup => in_array($next, [self::Paid, self::Cancelled, self::Expired], true),
            self::Paid, self::Rejected, self::Cancelled, self::Expired => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Rejected, self::Cancelled, self::Expired], true);
    }
}
