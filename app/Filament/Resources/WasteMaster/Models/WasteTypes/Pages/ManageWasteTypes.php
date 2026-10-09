<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WasteTypes\Pages;

use App\Domain\WasteMaster\Actions\SaveWasteCatalogType;
use App\Domain\WasteMaster\Models\WasteType;
use App\Filament\Resources\WasteMaster\Models\WasteTypes\WasteTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageWasteTypes extends ManageRecords
{
    protected static string $resource = WasteTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data): WasteType => app(SaveWasteCatalogType::class)->handle(auth()->user(), null, $data)),
        ];
    }
}
