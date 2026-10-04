<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WasteUnits\Pages;

use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Filament\Resources\WasteMaster\Models\WasteUnits\WasteUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageWasteUnits extends ManageRecords
{
    protected static string $resource = WasteUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data): WasteUnit => app(ManageWasteMaster::class)->createUnit(auth()->user(), $data['code'], $data['name'], $data['symbol'], $data['classification'], $data['conversion_factor_to_kg'] ?? null)),
        ];
    }
}
