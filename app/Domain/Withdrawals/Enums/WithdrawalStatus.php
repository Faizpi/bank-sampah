<?php

declare(strict_types=1);

namespace App\Domain\Withdrawals\Enums;

enum WithdrawalStatus: string
{
    case Waiting = 'menunggu_bendahara';
    case Processing = 'diproses_bendahara';
    case Paid = 'sudah_dibayar';
    case Cancelled = 'dibatalkan';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Waiting => in_array($next, [self::Processing, self::Cancelled], true),
            self::Processing => in_array($next, [self::Waiting, self::Paid, self::Cancelled], true),
            default => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled], true);
    }
}
