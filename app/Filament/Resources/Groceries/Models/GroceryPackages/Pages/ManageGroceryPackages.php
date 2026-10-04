<?php

declare(strict_types=1);

namespace App\Filament\Resources\Groceries\Models\GroceryPackages\Pages;

use App\Domain\Groceries\Actions\ManageGroceryPackages as ManageGroceryPackageAction;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Filament\Resources\Groceries\Models\GroceryPackages\GroceryPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageGroceryPackages extends ManageRecords
{
    protected static string $resource = GroceryPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data): GroceryPackage => app(ManageGroceryPackageAction::class)->create(auth()->user(), (string) $data['code'], (string) $data['name'], (string) $data['contents'], (int) $data['value'], $data['active_from'] ?? null, $data['active_until'] ?? null)),
        ];
    }
}
