<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan transaksi';

    protected string $view = 'filament.backoffice.reports';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'report.view');
    }
}
