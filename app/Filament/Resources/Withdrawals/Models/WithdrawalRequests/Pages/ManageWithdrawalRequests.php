<?php

declare(strict_types=1);

namespace App\Filament\Resources\Withdrawals\Models\WithdrawalRequests\Pages;

use App\Filament\Resources\Withdrawals\Models\WithdrawalRequests\WithdrawalRequestResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageWithdrawalRequests extends ManageRecords
{
    protected static string $resource = WithdrawalRequestResource::class;
}
