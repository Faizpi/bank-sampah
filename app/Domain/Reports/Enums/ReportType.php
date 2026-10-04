<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

enum ReportType: string
{
    case Deposits = 'deposits';
    case Withdrawals = 'withdrawals';
    case Groceries = 'groceries';
    case Pickups = 'pickups';
    case Participation = 'participation';
    case Reconciliation = 'reconciliation';
}
