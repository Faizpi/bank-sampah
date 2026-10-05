<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Users\Pages;

use App\Domain\Identity\Actions\ManageUsers as ManageUsersAction;
use App\Filament\Resources\Identity\Models\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;

final class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Pengguna baru')
                ->using(function (array $data): User {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        throw new \LogicException('Pengguna terautentikasi tidak valid.');
                    }

                    try {
                        return app(ManageUsersAction::class)->create($actor, $data);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Pengguna tidak dapat dibuat')
                            ->body(implode(' ', $exception->validator->errors()->all()))
                            ->danger()
                            ->send();

                        throw $exception;
                    }
                }),
        ];
    }
}
