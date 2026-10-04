<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ledger\Models\BalanceHolds\Pages;

use App\Filament\Resources\Ledger\Models\BalanceHolds\BalanceHoldResource;
use Filament\Resources\Pages\ListRecords;

final class ManageBalanceHolds extends ListRecords
{
    protected static string $resource = BalanceHoldResource::class;
}
