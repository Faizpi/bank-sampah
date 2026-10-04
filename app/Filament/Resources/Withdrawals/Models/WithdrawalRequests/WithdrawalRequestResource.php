<?php

declare(strict_types=1);

namespace App\Filament\Resources\Withdrawals\Models\WithdrawalRequests;

use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class WithdrawalRequestResource extends Resource
{
    protected static ?string $model = WithdrawalRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?string $navigationLabel = 'Pencairan';

    protected static ?string $modelLabel = 'pencairan';

    protected static ?string $pluralModelLabel = 'pencairan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('request_number')->label('Nomor')->disabled(),
            Textarea::make('pickup_location')->label('Lokasi')->disabled(),
            DatePicker::make('pickup_date')->label('Tanggal pengambilan')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('request_number')
            ->columns([
                TextColumn::make('request_number')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Warga')->searchable(),
                TextColumn::make('amount')->label('Nominal')->money('IDR'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('approver.name')->label('Approver')->placeholder('Belum diputuskan'),
                TextColumn::make('payer.name')->label('Payer')->placeholder('Belum ditugaskan'),
                TextColumn::make('expires_at')->label('Batas')->dateTime('d M Y H:i'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (WithdrawalRequest $record): bool => $record->status->value === 'menunggu_verifikasi')
                    ->authorize('approve')
                    ->action(fn (WithdrawalRequest $record): WithdrawalRequest => app(WithdrawalService::class)->approve(self::actor(), $record, true)),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (WithdrawalRequest $record): bool => in_array($record->status->value, ['menunggu_verifikasi', 'disetujui'], true))
                    ->authorize('approve')
                    ->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(10)->maxLength(1000)->rows(4)])
                    ->action(fn (WithdrawalRequest $record, array $data): WithdrawalRequest => app(WithdrawalService::class)->approve(self::actor(), $record, false, (string) $data['reason'])),
                Action::make('assignPayer')
                    ->label('Tetapkan payer')
                    ->icon(Heroicon::OutlinedUser)
                    ->visible(fn (WithdrawalRequest $record): bool => $record->status->value === 'disetujui')
                    ->authorize('approve')
                    ->schema([Select::make('payer_id')->label('Payer')->options(fn (WithdrawalRequest $record): array => User::query()->where('status', 'aktif')->whereHas('roles.permissions', fn (Builder $permissions): Builder => $permissions->where('permissions.name', 'withdrawal.pay'))->pluck('name', 'id')->all())->required()])
                    ->action(fn (WithdrawalRequest $record, array $data): WithdrawalRequest => app(WithdrawalService::class)->assignPayer(self::actor(), $record, User::query()->findOrFail((int) $data['payer_id']))),
            ]);
    }

    /** @return Builder<WithdrawalRequest> */
    public static function getEloquentQuery(): Builder
    {
        return app(WithdrawalService::class)->visibleFor(self::actor());
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => Pages\ManageWithdrawalRequests::route('/')];
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
