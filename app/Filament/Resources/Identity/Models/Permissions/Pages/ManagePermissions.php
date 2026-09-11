<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Permissions\Pages;

use App\Filament\Resources\Identity\Models\Permissions\PermissionResource;
use Filament\Resources\Pages\ManageRecords;

final class ManagePermissions extends ManageRecords
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
