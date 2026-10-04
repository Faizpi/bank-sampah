<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditReconciliation\Models\Reconciliations\Pages;

use App\Filament\Resources\AuditReconciliation\Models\Reconciliations\ReconciliationResource;
use Filament\Resources\Pages\ListRecords;

final class ManageReconciliations extends ListRecords
{
    protected static string $resource = ReconciliationResource::class;
}
