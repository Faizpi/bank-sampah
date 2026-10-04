<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WasteCategories\Pages;

use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Filament\Resources\WasteMaster\Models\WasteCategories\WasteCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageWasteCategories extends ManageRecords
{
    protected static string $resource = WasteCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data): WasteCategory => app(ManageWasteMaster::class)->createCategory(auth()->user(), $data['code'], $data['name'], (int) $data['sort_order'])),
        ];
    }
}
