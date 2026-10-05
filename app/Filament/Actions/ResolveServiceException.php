<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class ResolveServiceException
{
    public static function freeze(): Action
    {
        return Action::make('freezeException')->label('Kunci klaim bermasalah')
            ->visible(fn (WithdrawalRequest|GroceryRedemption $record): bool => ! $record->status->isTerminal() && ($record instanceof WithdrawalRequest ? $record->payer_id : $record->claimed_by_id) !== null && ($record->exception_reported_at === null || $record->exception_resolved_at !== null))
            ->authorize(fn (): bool => app(PermissionChecker::class)->allows(auth()->user(), 'transaction.exception'))
            ->modalDescription('Untuk klaim yang tidak dapat dilanjutkan, termasuk pelaksana yang aksesnya dicabut. Kunci dahulu; pastikan fakta serah fisik sebelum menentukan hasil.')
            ->schema([Textarea::make('reason')->label('Alasan kendala')->required()->minLength(10)->maxLength(1000), TextInput::make('password')->label('Kata sandi Admin')->password()->required()])
            ->action(function (WithdrawalRequest|GroceryRedemption $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(ClaimedServiceWorkflow::class)->freezeException($actor, $record, $data['reason'], $data['password']);
            });
    }

    public static function make(): Action
    {
        return Action::make('resolveException')->label('Selesaikan pengecualian')
            ->visible(fn (WithdrawalRequest|GroceryRedemption $record): bool => $record->exception_reported_at !== null && $record->exception_resolved_at === null)
            ->authorize(fn (): bool => app(PermissionChecker::class)->allows(auth()->user(), 'transaction.exception'))
            ->modalDescription('Hanya untuk transaksi dengan serah fisik yang tidak pasti. Konfirmasi fakta dengan pelaksana dan warga sebelum memutuskan. Ini bukan persetujuan rutin.')
            ->schema([
                Select::make('decision')->label('Hasil pemeriksaan')->options(['handed_over' => 'Sudah diserahkan — catat penyelesaian', 'not_handed_over' => 'Dipastikan belum diserahkan — kembali ke antrean'])->required(),
                Textarea::make('reason')->label('Alasan dan hasil pemeriksaan')->required()->minLength(10)->maxLength(1000),
                DateTimePicker::make('physical_time')->label('Waktu serah fisik jika diketahui (opsional)')->maxDate(now()),
                TextInput::make('password')->label('Kata sandi Admin saat ini')->password()->required(),
            ])
            ->requiresConfirmation()
            ->action(function (WithdrawalRequest|GroceryRedemption $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(ClaimedServiceWorkflow::class)->resolveException($actor, $record, $data['decision'] === 'handed_over', $data['reason'], $data['password'], $data['physical_time'] ?? null);
            });
    }
}
