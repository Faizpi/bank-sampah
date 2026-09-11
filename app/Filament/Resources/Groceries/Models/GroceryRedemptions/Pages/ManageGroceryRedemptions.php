<?php

declare(strict_types=1);

namespace App\Filament\Resources\Groceries\Models\GroceryRedemptions\Pages;

use App\Filament\Resources\Groceries\Models\GroceryRedemptions\GroceryRedemptionResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageGroceryRedemptions extends ManageRecords
{
    protected static string $resource = GroceryRedemptionResource::class;
}
