<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WasteConditions\Pages;

use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Filament\Resources\WasteMaster\Models\WasteConditions\WasteConditionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageWasteConditions extends ManageRecords
{
    protected static string $resource = WasteConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data): WasteCondition => app(ManageWasteMaster::class)->createCondition(auth()->user(), $data['code'], $data['name'], $data['description'] ?? null, (int) $data['sort_order'])),
        ];
    }
}
