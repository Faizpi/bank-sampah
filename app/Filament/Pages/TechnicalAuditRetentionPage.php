<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class TechnicalAuditRetentionPage extends OperationsDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi sistem';

    protected static ?string $navigationParentItem = 'Kontrol teknis';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Retensi audit';

    protected static ?string $title = 'Retensi audit';

    protected string $view = 'filament.backoffice.technical-audit-retention';

    public static function canAccess(): bool
    {
        return self::hasTechnicalPermission('audit.retention.execute');
    }
}
