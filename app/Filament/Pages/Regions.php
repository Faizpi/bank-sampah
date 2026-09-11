<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Filament\Resources\CustomersRegions\Models\Dusuns\DusunResource;
use App\Filament\Resources\CustomersRegions\Models\Rts\RtResource;
use App\Filament\Resources\CustomersRegions\Models\Rws\RwResource;
use App\Filament\Resources\CustomersRegions\Models\ServiceAreas\ServiceAreaResource;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class Regions extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Wilayah';

    protected static ?string $title = 'Wilayah';

    protected string $view = 'filament.backoffice.regions';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && (app(PermissionChecker::class)->allows($actor, 'region.view')
                || app(PermissionChecker::class)->allows($actor, 'region.manage'));
    }

    /** @return array<string, bool> */
    protected function getViewData(): array
    {
        return [
            'canViewAreas' => ServiceAreaResource::canViewAny(),
            'canViewDusuns' => DusunResource::canViewAny(),
            'canViewRws' => RwResource::canViewAny(),
            'canViewRts' => RtResource::canViewAny(),
        ];
    }
}
