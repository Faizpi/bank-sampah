<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class TechnicalHealthPage extends OperationsDashboard
{
    protected static bool $isDiscovered = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi sistem';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Kondisi sistem';

    protected static ?string $title = 'Kondisi sistem';

    protected string $view = 'filament.backoffice.technical-health';

    public static function canAccess(): bool
    {
        return self::hasTechnicalPermission('system.maintenance');
    }
}
