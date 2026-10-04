<?php

declare(strict_types=1);

namespace App\Filament\Resources\Groceries\Models\GroceryRedemptions;

use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Filament\Resources\Groceries\Models\GroceryRedemptions\Pages\ManageGroceryRedemptions;
use App\Models\User;
use App\Support\StatusLabel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class GroceryRedemptionResource extends Resource
{
    protected static ?string $model = GroceryRedemption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Penukaran Sembako';

    protected static ?string $modelLabel = 'penukaran sembako';

    protected static ?string $pluralModelLabel = 'penukaran sembako';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('request_number')->label('Nomor')->disabled(),
            Textarea::make('package_snapshot')->label('Rekaman paket saat penukaran')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('request_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('request_number')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Nasabah')->searchable(),
                TextColumn::make('package.name')->label('Paket')->searchable(),
                TextColumn::make('value_snapshot')->label('Nilai')->money('IDR'),
                TextColumn::make('status')->label('Status')->formatStateUsing(fn ($state): string => StatusLabel::for($state))->badge(),
                TextColumn::make('approver.name')->label('Disetujui oleh')->placeholder('Belum disetujui'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(collect(GroceryStatus::cases())->mapWithKeys(fn (GroceryStatus $status): array => [$status->value => StatusLabel::for($status)])->all()),
                SelectFilter::make('grocery_package_id')->label('Paket')->relationship('package', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('inspect')
                    ->label('Tinjau penukaran')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Tinjau penukaran sembako')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->schema([
                        TextInput::make('package')->label('Paket saat pengajuan')->disabled(),
                        TextInput::make('held_amount')->label('Dana ditahan')->disabled(),
                        TextInput::make('status')->label('Status')->disabled(),
                        TextInput::make('customer')->label('Nasabah')->disabled(),
                        TextInput::make('requested_by')->label('Diajukan oleh')->disabled(),
                        TextInput::make('approver')->label('Penyetuju')->disabled(),
                        TextInput::make('prepared_by')->label('Petugas persiapan')->disabled(),
                        Textarea::make('snapshot')->label('Rekaman paket saat penukaran')->disabled()->rows(5),
                    ])
                    ->fillForm(fn (GroceryRedemption $record): array => self::inspectionData($record)),
                Action::make('approve')
                    ->label('Setujui')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (GroceryRedemption $record): bool => $record->status === GroceryStatus::PendingVerification)->authorize('approve')->requiresConfirmation()
                    ->modalHeading(fn (GroceryRedemption $record): string => "Setujui penukaran {$record->request_number}?")
                    ->modalDescription(fn (GroceryRedemption $record): string => 'Paket '.data_get($record->package_snapshot, 'name', 'sembako').' senilai Rp '.number_format($record->value_snapshot, 0, ',', '.').' tetap ditahan sampai serah-terima.')
                    ->modalSubmitActionLabel('Setujui penukaran')
                    ->schema([Textarea::make('availability_note')->label('Catatan ketersediaan')->required()->minLength(3)->maxLength(1000)->rows(4)])
                    ->action(fn (GroceryRedemption $record, array $data): GroceryRedemption => app(GroceryService::class)->approve(self::actor(), $record, true, (string) $data['availability_note'])),
                Action::make('reject')
                    ->label('Tolak')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (GroceryRedemption $record): bool => $record->status === GroceryStatus::PendingVerification)->authorize('approve')->requiresConfirmation()
                    ->modalHeading(fn (GroceryRedemption $record): string => "Tolak penukaran {$record->request_number}?")
                    ->modalDescription('Penolakan melepaskan dana yang ditahan kembali ke saldo tersedia nasabah.')
                    ->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(10)->maxLength(1000)->rows(4)])
                    ->action(fn (GroceryRedemption $record, array $data): GroceryRedemption => app(GroceryService::class)->approve(self::actor(), $record, false, null, (string) $data['reason'])),
                Action::make('prepare')
                    ->label('Mulai siapkan')->icon(Heroicon::OutlinedCube)
                    ->visible(fn (GroceryRedemption $record): bool => $record->status === GroceryStatus::Approved)->authorize('prepare')->requiresConfirmation()
                    ->modalHeading(fn (GroceryRedemption $record): string => "Mulai siapkan {$record->request_number}?")
                    ->modalDescription(fn (GroceryRedemption $record): string => 'Petugas akan dicatat sebagai penyiap paket '.data_get($record->package_snapshot, 'name', 'sembako').'.')
                    ->modalSubmitActionLabel('Mulai siapkan')
                    ->action(fn (GroceryRedemption $record): GroceryRedemption => app(GroceryService::class)->prepare(self::actor(), $record)),
                Action::make('ready')
                    ->label('Tandai siap')->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (GroceryRedemption $record): bool => $record->status === GroceryStatus::Preparing)->authorize('prepare')->requiresConfirmation()
                    ->modalHeading(fn (GroceryRedemption $record): string => "Tandai {$record->request_number} siap diambil?")
                    ->modalDescription('Paket akan tersedia untuk proses serah-terima; dana tetap ditahan hingga serah-terima tercatat.')
                    ->modalSubmitActionLabel('Tandai siap diambil')
                    ->action(fn (GroceryRedemption $record): GroceryRedemption => app(GroceryService::class)->ready(self::actor(), $record)),
            ]);
    }

    /** @return array<string, string> */
    private static function inspectionData(GroceryRedemption $record): array
    {
        $record->loadMissing(['customer', 'requestedBy', 'approver', 'preparedBy', 'balanceHold']);

        return [
            'package' => (string) data_get($record->package_snapshot, 'name', 'Tidak tersedia'),
            'held_amount' => 'Rp '.number_format((int) (data_get($record->balanceHold, 'amount') ?? $record->value_snapshot), 0, ',', '.'),
            'status' => StatusLabel::for($record->status),
            'customer' => $record->customer?->name ?? 'Tidak tersedia',
            'requested_by' => $record->requestedBy?->name ?? 'Tidak tersedia',
            'approver' => $record->approver?->name ?? 'Belum disetujui',
            'prepared_by' => $record->preparedBy?->name ?? 'Belum mulai disiapkan',
            'snapshot' => json_encode($record->package_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'Tidak tersedia',
        ];
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    /** @return Builder<GroceryRedemption> */
    public static function getEloquentQuery(): Builder
    {
        return app(GroceryService::class)->visibleFor(self::actor());
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageGroceryRedemptions::route('/')];
    }
}
