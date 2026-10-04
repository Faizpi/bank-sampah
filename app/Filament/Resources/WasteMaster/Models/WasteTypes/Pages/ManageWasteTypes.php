<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WasteTypes\Pages;

use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Filament\Resources\WasteMaster\Models\WasteTypes\WasteTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageWasteTypes extends ManageRecords
{
    protected static string $resource = WasteTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(function (array $data): WasteType {
                /** @var WasteCategory $category */
                $category = WasteCategory::query()->findOrFail($data['waste_category_id']);
                /** @var WasteUnit $unit */
                $unit = WasteUnit::query()->findOrFail($data['waste_unit_id']);

                return app(ManageWasteMaster::class)->createType(auth()->user(), $category, $unit, $data['code'], $data['name'], $data['education_description'] ?? null, (int) $data['sort_order'], (bool) ($data['is_plastic'] ?? false), (bool) ($data['is_active'] ?? true), array_map('intval', $data['condition_ids'] ?? []));
            }),
        ];
    }
}
