<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Customers\Pages;

use App\Filament\Resources\Identity\Models\Customers\CustomerResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageCustomers extends ManageRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
