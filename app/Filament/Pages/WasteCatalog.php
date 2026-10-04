<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Filament\Resources\WasteMaster\Models\WasteCategories\WasteCategoryResource;
use App\Filament\Resources\WasteMaster\Models\WasteConditions\WasteConditionResource;
use App\Filament\Resources\WasteMaster\Models\WastePrices\WastePriceResource;
use App\Filament\Resources\WasteMaster\Models\WasteTypes\WasteTypeResource;
use App\Filament\Resources\WasteMaster\Models\WasteUnits\WasteUnitResource;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class WasteCatalog extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Katalog Sampah';

    protected static ?string $title = 'Katalog Sampah';

    protected string $view = 'filament.backoffice.waste-catalog';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && (app(PermissionChecker::class)->allows($actor, 'waste.view')
                || app(PermissionChecker::class)->allows($actor, 'waste.manage')
                || app(PermissionChecker::class)->allows($actor, 'price.view')
                || app(PermissionChecker::class)->allows($actor, 'price.manage'));
    }

    /** @return array<string, bool> */
    protected function getViewData(): array
    {
        return [
            'canViewCategories' => WasteCategoryResource::canViewAny(),
            'canViewTypes' => WasteTypeResource::canViewAny(),
            'canViewConditions' => WasteConditionResource::canViewAny(),
            'canViewPrices' => WastePriceResource::canViewAny(),
            'canViewUnits' => WasteUnitResource::canViewAny(),
        ];
    }
}
