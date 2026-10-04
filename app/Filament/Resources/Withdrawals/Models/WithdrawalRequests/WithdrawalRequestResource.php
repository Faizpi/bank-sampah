<?php

declare(strict_types=1);

namespace App\Filament\Resources\Withdrawals\Models\WithdrawalRequests;

use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use App\Support\StatusLabel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class WithdrawalRequestResource extends Resource
{
    protected static ?string $model = WithdrawalRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Pencairan';

    protected static ?string $modelLabel = 'pencairan';

    protected static ?string $pluralModelLabel = 'pencairan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('request_number')->label('Nomor')->disabled(),
            TextInput::make('customer.name')->label('Nasabah')->disabled(),
            TextInput::make('amount')->label('Nominal')->prefix('Rp')->disabled(),
            TextInput::make('status')->label('Status')->disabled(),
            Textarea::make('pickup_location')->label('Lokasi')->disabled(),
            DatePicker::make('pickup_date')->label('Tanggal pengambilan')->disabled(),
            DatePicker::make('expires_at')->label('Batas pengambilan')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('request_number')
            ->defaultSort('expires_at', 'desc')
            ->columns([
                TextColumn::make('request_number')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Nasabah')->searchable(),
                TextColumn::make('amount')->label('Nominal')->money('IDR'),
                TextColumn::make('status')->label('Status')->formatStateUsing(fn ($state): string => StatusLabel::for($state))->badge(),
                TextColumn::make('approver.name')->label('Disetujui oleh')->placeholder('Belum diputuskan'),
                TextColumn::make('payer.name')->label('Petugas pembayaran')->placeholder('Belum ditugaskan'),
                TextColumn::make('expires_at')->label('Batas')->dateTime('d M Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options([
                    WithdrawalStatus::PendingVerification->value => 'Menunggu verifikasi',
                    WithdrawalStatus::Approved->value => 'Disetujui',
                    WithdrawalStatus::ReadyForPickup->value => 'Siap diambil',
                    WithdrawalStatus::Paid->value => 'Sudah dibayar',
                    WithdrawalStatus::Rejected->value => 'Ditolak',
                    WithdrawalStatus::Cancelled->value => 'Dibatalkan',
                    WithdrawalStatus::Expired->value => 'Kedaluwarsa',
                ]),
                Filter::make('pickup_date')->label('Tanggal pengambilan')->form([
                    DatePicker::make('date')->label('Tanggal'),
                ])->query(static function (Builder $query, array $data): Builder {
                    return $query->when($data['date'] ?? null, static fn (Builder $query, string $date): Builder => $query->whereDate('pickup_date', $date));
                }),
            ])
            ->recordActions([
                Action::make('inspect')
                    ->label('Tinjau pencairan')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Tinjau pencairan')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->schema([
                        TextInput::make('customer')->label('Penerima')->disabled(),
                        TextInput::make('amount')->label('Nominal')->disabled(),
                        TextInput::make('available_balance')->label('Saldo tersedia saat pemeriksaan')->disabled(),
                        TextInput::make('held_balance')->label('Dana ditahan')->disabled(),
                        TextInput::make('status')->label('Status')->disabled(),
                        TextInput::make('expires_at')->label('Batas pengambilan')->disabled(),
                        Textarea::make('consent')->label('Persetujuan dan verifikasi')->disabled()->rows(3),
                        Textarea::make('evidence')->label('Bukti')->disabled()->rows(3),
                        Textarea::make('impact')->label('Dampak persetujuan')->disabled()->rows(4),
                    ])
                    ->fillForm(fn (WithdrawalRequest $record): array => self::inspectionData($record)),
                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (WithdrawalRequest $record): bool => $record->status->value === 'menunggu_verifikasi')
                    ->authorize('approve')
                    ->requiresConfirmation()
                    ->modalHeading(fn (WithdrawalRequest $record): string => "Setujui pencairan {$record->request_number}?")
                    ->modalDescription('Dana tetap ditahan dan pengajuan diteruskan ke petugas pembayaran. Periksa penerima dan bukti sebelum menyetujui.')
                    ->modalSubmitActionLabel('Setujui pencairan')
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
                    ->label('Tetapkan petugas pembayaran')
                    ->icon(Heroicon::OutlinedUser)
                    ->visible(fn (WithdrawalRequest $record): bool => $record->status->value === 'disetujui')
                    ->authorize('approve')
                    ->schema([Select::make('payer_id')->label('Petugas pembayaran')->options(fn (WithdrawalRequest $record): array => User::query()->where('status', 'aktif')->whereHas('roles.permissions', fn (Builder $permissions): Builder => $permissions->where('permissions.name', 'withdrawal.pay'))->pluck('name', 'id')->all())->required()])
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

    /** @return array<string, string> */
    private static function inspectionData(WithdrawalRequest $record): array
    {
        $record->loadMissing(['customer.ledgerAccount', 'balanceHold', 'assistedService', 'proofMedia']);
        $availableBalance = $record->customer?->ledgerAccount?->availableBalance();
        $impact = match ($record->status->value) {
            WithdrawalStatus::PendingVerification->value => 'Persetujuan mempertahankan dana ditahan Rp '.number_format((int) (data_get($record->balanceHold, 'amount') ?? $record->amount), 0, ',', '.').' dan meneruskan pencairan untuk ditugaskan kepada petugas pembayaran.',
            WithdrawalStatus::Approved->value, WithdrawalStatus::ReadyForPickup->value => 'Pencairan sudah melewati pemeriksaan. Pastikan penerima dan bukti pembayaran cocok sebelum saldo keluar.',
            default => 'Tidak ada tindakan persetujuan yang tersedia pada status ini.',
        };

        return [
            'customer' => data_get($record->customer, 'name', 'Nasabah'),
            'amount' => 'Rp '.number_format($record->amount, 0, ',', '.'),
            'available_balance' => $availableBalance === null ? 'Tidak tersedia' : 'Rp '.number_format($availableBalance, 0, ',', '.'),
            'held_balance' => 'Rp '.number_format((int) (data_get($record->balanceHold, 'amount') ?? 0), 0, ',', '.'),
            'status' => StatusLabel::for($record->status),
            'expires_at' => $record->expires_at?->setTimezone('Asia/Jakarta')->format('d M Y H:i') ?? 'Belum ditetapkan',
            'consent' => $record->assistedService === null ? 'Pengajuan mandiri; verifikasi nasabah dilakukan pada tahap pembayaran.' : 'Persetujuan: '.$record->assistedService->consent_version.' · waktu: '.$record->assistedService->consented_at,
            'evidence' => data_get($record->proofMedia, 'original_name') ?? ($record->assistedService?->evidence_media_id === null ? 'Belum ada bukti privat.' : 'Bukti persetujuan privat tersimpan.'),
            'impact' => $impact,
        ];
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
