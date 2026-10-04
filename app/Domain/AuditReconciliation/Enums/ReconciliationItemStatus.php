<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Enums;

enum ReconciliationItemStatus: string
{
    case Open = 'terbuka';
    case Explained = 'dijelaskan';
    case Resolved = 'diselesaikan';
}
