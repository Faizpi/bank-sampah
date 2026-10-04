<?php

declare(strict_types=1);

namespace App\Filament\Resources\Deposits\Models\Deposits\Pages;

use App\Filament\Resources\Deposits\Models\Deposits\DepositResource;
use Filament\Resources\Pages\ListRecords;

final class ManageDeposits extends ListRecords
{
    protected static string $resource = DepositResource::class;
}
