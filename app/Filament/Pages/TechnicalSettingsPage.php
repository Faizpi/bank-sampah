<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class TechnicalSettingsPage extends OperationsDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi sistem';

    protected static ?string $navigationParentItem = 'Kontrol teknis';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static ?string $title = 'Pengaturan teknis';

    protected string $view = 'filament.backoffice.technical-settings';

    public static function canAccess(): bool
    {
        return self::hasTechnicalPermission('system.settings.manage');
    }
}
