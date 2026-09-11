<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class TechnicalBackupsPage extends OperationsDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi sistem';

    protected static ?string $navigationParentItem = 'Kontrol teknis';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Cadangan';

    protected static ?string $title = 'Cadangan dan pemulihan';

    protected string $view = 'filament.backoffice.technical-backups';

    public static function canAccess(): bool
    {
        return self::hasAnyTechnicalPermission(['backup.view', 'backup.run', 'backup.restore']);
    }
}
