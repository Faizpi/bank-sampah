<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ledger\Models\LedgerEntries\Pages;

use App\Filament\Resources\Ledger\Models\LedgerEntries\LedgerEntryResource;
use Filament\Resources\Pages\ListRecords;

final class ManageLedgerEntries extends ListRecords
{
    protected static string $resource = LedgerEntryResource::class;
}
