<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Domain\Operations\Services\OperationalHealthService;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class TechnicalHealthPage extends Page
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
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'system.maintenance');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(self::canAccess(), 403);

        return ['health' => app(OperationalHealthService::class)->check()->toArray()];
    }
}
