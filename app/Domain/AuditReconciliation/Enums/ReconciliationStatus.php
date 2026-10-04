<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Enums;

enum ReconciliationStatus: string
{
    case Draft = 'draf';
    case Submitted = 'diajukan';
    case Approved = 'disahkan';
    case Rejected = 'ditolak';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => $next === self::Submitted,
            self::Submitted => in_array($next, [self::Approved, self::Rejected], true),
            self::Approved, self::Rejected => false,
        };
    }
}
