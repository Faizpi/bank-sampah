<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditReconciliation\Models\AuditLogs\Pages;

use App\Filament\Resources\AuditReconciliation\Models\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

final class ManageAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
