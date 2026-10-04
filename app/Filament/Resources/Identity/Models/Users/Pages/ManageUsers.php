<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Users\Pages;

use App\Domain\Identity\Actions\ManageUsers as ManageUsersAction;
use App\Filament\Resources\Identity\Models\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Pengguna baru')->using(fn (array $data): User => app(ManageUsersAction::class)->create(auth()->user(), $data)),
        ];
    }
}
