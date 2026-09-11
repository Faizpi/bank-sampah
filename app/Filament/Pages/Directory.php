<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Filament\Resources\Identity\Models\CitizenVerifications\CitizenVerificationResource;
use App\Filament\Resources\Identity\Models\Customers\CustomerResource;
use App\Filament\Resources\Identity\Models\Users\UserResource;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class Directory extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Direktori';

    protected static ?string $title = 'Direktori';

    protected string $view = 'filament.backoffice.directory';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && app(PermissionChecker::class)->allows($actor, 'backoffice.access')
            && (app(PermissionChecker::class)->allows($actor, 'customer.view')
                || app(PermissionChecker::class)->allows($actor, 'user.view'));
    }

    /** @return array<string, bool> */
    protected function getViewData(): array
    {
        return [
            'canViewCustomers' => CustomerResource::canViewAny(),
            'canViewUsers' => UserResource::canViewAny(),
            'canVerifyCitizens' => CitizenVerificationResource::canViewAny(),
        ];
    }
}
