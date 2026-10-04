<?php

declare(strict_types=1);

namespace App\Domain;

enum Module: string
{
    case IdentityAccess = 'IdentityAccess';
    case CustomersRegions = 'CustomersRegions';
    case WastePricing = 'WastePricing';
    case Deposits = 'Deposits';
    case Ledger = 'Ledger';
    case Pickups = 'Pickups';
    case Withdrawals = 'Withdrawals';
    case Groceries = 'Groceries';
    case Programs = 'Programs';
    case Communications = 'Communications';
    case Reporting = 'Reporting';
    case AuditReconciliation = 'AuditReconciliation';
    case Platform = 'Platform';

    public function namespace(): string
    {
        return "App\\Domain\\{$this->value}";
    }

    public function relativePath(): string
    {
        return "app/Domain/{$this->value}";
    }
}
