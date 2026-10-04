<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class TechnicalMediaRetentionPage extends OperationsDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi sistem';

    protected static ?string $navigationParentItem = 'Kontrol teknis';

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'Retensi foto';

    protected static ?string $title = 'Retensi foto penjemputan';

    protected string $view = 'filament.backoffice.technical-media-retention';

    public static function canAccess(): bool
    {
        return self::hasTechnicalPermission('media.retention.execute');
    }
}
