<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ledger\Models\LedgerEntries;

use App\Authorization\PermissionChecker;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Filament\Resources\Ledger\Models\LedgerEntries\Pages\ManageLedgerEntries;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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

final class LedgerEntryResource extends Resource
{
    protected static ?string $model = LedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Perubahan saldo';

    protected static ?string $modelLabel = 'perubahan saldo';

    protected static ?string $pluralModelLabel = 'perubahan saldo';

    public static function shouldRegisterNavigation(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'ledger.adjust');
    }

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'ledger.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('entry_number')->label('Nomor perubahan saldo')->disabled(),
            Textarea::make('source_key')->label('Kode pencegah pencatatan ganda')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('entry_number')
            ->defaultSort('effective_at', 'desc')
            ->columns([
                TextColumn::make('entry_number')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('account.user.name')->label('Nasabah')->searchable(),
                TextColumn::make('direction')->label('Arah')->badge(),
                TextColumn::make('kind')->label('Jenis')->formatStateUsing(fn (string $state): string => match ($state) {
                    LedgerEntry::KIND_DEPOSIT => 'Setoran',
                    LedgerEntry::KIND_CORRECTION => 'Koreksi',
                    LedgerEntry::KIND_REVERSAL => 'Pembalikan',
                    LedgerEntry::KIND_ADJUSTMENT => 'Penyesuaian',
                    default => $state,
                })->badge(),
                TextColumn::make('amount')->label('Nominal')->money('IDR')->sortable(),
                TextColumn::make('balance_after')->label('Saldo setelah')->money('IDR'),
                TextColumn::make('effective_at')->label('Waktu efektif')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('direction')->options([
                    LedgerEntry::DIRECTION_IN => 'Masuk',
                    LedgerEntry::DIRECTION_OUT => 'Keluar',
                ]),
                SelectFilter::make('kind')->options([
                    LedgerEntry::KIND_DEPOSIT => 'Setoran',
                    LedgerEntry::KIND_CORRECTION => 'Koreksi',
                    LedgerEntry::KIND_REVERSAL => 'Pembalikan',
                ]),
            ])
            ->headerActions([
                Action::make('adjust')
                    ->label('Penyesuaian saldo')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->visible(fn (): bool => app(PermissionChecker::class)->allows(self::actor(), 'ledger.adjust'))
                    ->schema([
                        Select::make('owner_id')->label('Nasabah')->options(fn (): array => User::query()->where('status', 'aktif')->orderBy('name')->pluck('name', 'id')->all())->searchable()->required(),
                        TextInput::make('delta')->label('Dampak saldo (Rp)')->numeric()->integer()->rule('not_in:0')->required(),
                        Textarea::make('reason')->label('Alasan')->minLength(10)->maxLength(1000)->required(),
                        TextInput::make('idempotency_key')->label('Kunci transaksi unik')->default(fn (): string => 'ledger-adjust-'.str()->uuid())->required(),
                    ])
                    ->action(function (array $data): void {
                        app(LedgerService::class)->adjust(self::actor(), User::query()->findOrFail((int) $data['owner_id']), (int) $data['delta'], (string) $data['reason'], (string) $data['idempotency_key']);
                    })
                    ->successNotificationTitle('Penyesuaian saldo tercatat.'),
            ])
            ->recordActions([
                Action::make('inspect')
                    ->label('Lihat perubahan saldo')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Lihat perubahan saldo')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->schema([
                        Textarea::make('source')->label('Sumber transaksi')->disabled()->rows(4),
                        Textarea::make('relationship')->label('Perubahan saldo terkait')->disabled()->rows(3),
                    ])
                    ->fillForm(fn (LedgerEntry $record): array => [
                        'source' => sprintf(
                            'Jenis sumber: %s\nID sumber: %s\nReferensi transaksi: %s',
                            class_basename($record->source_type),
                            $record->source_id ?? 'Tidak tersedia',
                            $record->source_key,
                        ),
                        'relationship' => sprintf(
                            'Perubahan saldo terkait: %s\nSaldo setelah mutasi: Rp %s',
                            $record->related_entry_id ?? 'Tidak ada',
                            number_format((int) $record->balance_after, 0, ',', '.'),
                        ),
                    ]),
            ]);
    }

    /** @return Builder<LedgerEntry> */
    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();

        return $actor instanceof User
            ? app(LedgerService::class)->visibleEntries($actor)
            : LedgerEntry::query()->whereKey([]);
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageLedgerEntries::route('/')];
    }
}
