<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class PickupExceptionActions
{
    /** @return list<Action> */
    public static function make(): array
    {
        $fields = fn (): array => [Textarea::make('reason')->label('Alasan dan hasil pemeriksaan')->required()->minLength(10)->maxLength(1000), TextInput::make('password')->label('Kata sandi Admin')->password()->required()];

        return [
            Action::make('freezePickup')->label('Kunci klaim bermasalah')
                ->authorize(fn (): bool => app(PermissionChecker::class)->allows(auth()->user(), 'transaction.exception'))
                ->visible(fn (PickupRequest $record): bool => ! $record->isTerminal() && $record->assigned_staff_id !== null && ($record->exception_reported_at === null || $record->exception_resolved_at !== null))
                ->schema($fields())->action(function (PickupRequest $record, array $data): void {
                    app(PickupService::class)->freezeException(auth()->user(), $record, $data['reason'], $data['password']);
                }),
            Action::make('reassignPickupException')->label('Alihkan pengecualian pickup')
                ->authorize(fn (): bool => app(PermissionChecker::class)->allows(auth()->user(), 'transaction.exception'))
                ->visible(fn (PickupRequest $record): bool => ! $record->isTerminal() && $record->exception_reported_at !== null && $record->exception_resolved_at === null)
                ->modalDescription('Pastikan siapa yang memegang sampah dan tugasnya sebelum mengalihkan. Ini bukan penugasan rutin.')
                ->schema([Select::make('staff_id')->label('Petugas pengganti')->options(fn (): array => User::where('role', 'petugas')->where('status', 'aktif')->pluck('name', 'id')->all())->required(), ...$fields()])
                ->action(function (PickupRequest $record, array $data): void {
                    app(PickupService::class)->reassignException(auth()->user(), $record, User::findOrFail($data['staff_id']), $data['reason'], $data['password']);
                }),
        ];
    }
}
