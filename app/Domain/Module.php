<?php

declare(strict_types=1);

namespace App\Domain;

enum Module: string
{
    case Identity = 'Identity';
    case CustomersRegions = 'CustomersRegions';
    case WasteMaster = 'WasteMaster';
    case Deposits = 'Deposits';
    case Ledger = 'Ledger';
    case Pickups = 'Pickups';
    case Withdrawals = 'Withdrawals';
    case Groceries = 'Groceries';
    case Programs = 'Programs';
    case Communication = 'Communication';
    case Reports = 'Reports';
    case AuditReconciliation = 'AuditReconciliation';
    case Platform = 'Platform';
    case Notifications = 'Notifications';
    case Corrections = 'Corrections';
    case Operations = 'Operations';
    case Statistics = 'Statistics';

    public function namespace(): string
    {
        return "App\\Domain\\{$this->value}";
    }

    public function relativePath(): string
    {
        return "app/Domain/{$this->value}";
    }
}
