<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Roles\Pages;

use App\Domain\Identity\Actions\ManageRoles as ManageRolesAction;
use App\Domain\Identity\Models\Role;
use App\Filament\Resources\Identity\Models\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

final class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Peran baru')
                ->modalWidth(Width::SevenExtraLarge)
                ->using(fn (array $data): Role => app(ManageRolesAction::class)->createRole(
                    auth()->user(),
                    $data['name'],
                    $data['description'] ?? '',
                    array_map('intval', $data['permissions'] ?? []),
                )),
        ];
    }
}
