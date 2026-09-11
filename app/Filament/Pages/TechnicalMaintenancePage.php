<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class TechnicalMaintenancePage extends OperationsDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPower;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi sistem';

    protected static ?string $navigationParentItem = 'Kontrol teknis';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Pemeliharaan';

    protected static ?string $title = 'Pemeliharaan aplikasi';

    protected string $view = 'filament.backoffice.technical-maintenance';

    public static function canAccess(): bool
    {
        return self::hasTechnicalPermission('system.maintenance');
    }
}
