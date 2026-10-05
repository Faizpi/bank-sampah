<?php

declare(strict_types=1);

namespace App\Filament\Resources\Deposits\Models\Deposits;

use App\Authorization\PermissionChecker;
use App\Domain\Corrections\Services\TransactionCorrectionService;
use App\Domain\Deposits\Actions\RotateDepositVerificationToken;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositReviewService;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Filament\Resources\Deposits\Models\Deposits\Pages\ManageDeposits;
use App\Models\User;
use App\Support\StatusLabel;
use App\Support\WeightFormatter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use UnitEnum;

final class DepositResource extends Resource
{
    protected static ?string $model = Deposit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Setoran';

    protected static ?string $modelLabel = 'setoran';

    protected static ?string $pluralModelLabel = 'setoran';

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'deposit.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transaksi')->schema([
                TextInput::make('deposit_number')->label('Nomor setoran')->disabled(),
                TextInput::make('customer.name')->label('Nasabah')->disabled(),
                TextInput::make('staff.name')->label('Petugas')->disabled(),
                TextInput::make('status')->label('Status')->disabled(),
                TextInput::make('total_weight_kg')->label('Berat total (kg)')->formatStateUsing(fn (?string $state): string => WeightFormatter::format($state))->disabled(),
                TextInput::make('effective_total_value')->label('Nilai akhir')->disabled(),
                Textarea::make('items')->label('Rincian saat transaksi')->disabled()->rows(8),
                Textarea::make('ledgerEntries')->label('Riwayat mutasi saldo')->disabled()->rows(5),
                Textarea::make('media')->label('Bukti')->disabled()->rows(3),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('deposit_number')
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('deposit_number')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Nasabah')->searchable(),
                TextColumn::make('occurred_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('effective_total_value')->label('Nilai akhir')->state(fn (Deposit $record): int => $record->effectiveTotalValue())->money('IDR'),
                TextColumn::make('total_weight_kg')->label('Berat (kg)')->formatStateUsing(fn (?string $state): string => WeightFormatter::format($state))->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Deposit::STATUS_PENDING_REVIEW => 'Menunggu persetujuan',
                    Deposit::STATUS_FINAL => 'Final',
                    Deposit::STATUS_CORRECTED => 'Dikoreksi',
                    Deposit::STATUS_REVERSED => 'Dibalik',
                    Deposit::STATUS_REJECTED => 'Ditolak',
                ]),
            ])
            ->recordActions([
                Action::make('inspect')
                    ->label('Tinjau setoran')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Tinjau setoran')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->schema([
                        Textarea::make('snapshot')->label('Rincian saat transaksi')->disabled()->rows(8),
                        Textarea::make('ledger')->label('Mutasi dan saldo akhir')->disabled()->rows(5),
                        Textarea::make('holds')->label('Dana terkait yang ditahan')->disabled()->rows(4),
                        Textarea::make('evidence')->label('Bukti')->disabled()->rows(3),
                    ])
                    ->fillForm(fn (Deposit $record): array => self::inspectionData($record)),
                Action::make('rotateVerificationQr')
                    ->label('Rotasi QR verifikasi')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (Deposit $record): bool => self::verificationRotation()->canRotate(self::actor(), $record))
                    ->authorize(fn (Deposit $record): bool => self::verificationRotation()->canRotate(self::actor(), $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Deposit $record): string => "Rotasi QR setoran {$record->deposit_number}?")
                    ->modalDescription('QR lama akan langsung tidak aktif. Nasabah dapat membuka bukti setoran untuk mendapatkan QR baru.')
                    ->schema([
                        Textarea::make('reason')->label('Alasan rotasi')->required()->minLength(10)->maxLength(1000)->rows(4),
                    ])
                    ->action(fn (Deposit $record, array $data): Deposit => self::verificationRotation()->handle(self::actor(), $record, (string) $data['reason']))
                    ->successNotificationTitle('QR verifikasi setoran dirotasi.'),
                Action::make('approveHighValue')
                    ->label('Setujui nilai tinggi')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Deposit $record): bool => self::reviewService()->canReview(self::actor(), $record))
                    ->authorize(fn (Deposit $record): bool => self::reviewService()->canReview(self::actor(), $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Deposit $record): string => "Setujui setoran {$record->deposit_number}?")
                    ->modalDescription('Saldo warga akan ditambahkan hanya setelah pemeriksaan kedua ini dicatat.')
                    ->schema([
                        Textarea::make('reason')->label('Catatan pemeriksaan')->required()->minLength(10)->maxLength(1000)->rows(4),
                    ])
                    ->action(fn (Deposit $record, array $data): Deposit => self::reviewService()->approve(self::actor(), $record, (string) $data['reason'], self::reviewIdempotencyKey()))
                    ->successNotificationTitle('Setoran bernilai tinggi disetujui dan saldo ditambahkan.'),
                Action::make('rejectHighValue')
                    ->label('Tolak nilai tinggi')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Deposit $record): bool => self::reviewService()->canReview(self::actor(), $record))
                    ->authorize(fn (Deposit $record): bool => self::reviewService()->canReview(self::actor(), $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Deposit $record): string => "Tolak setoran {$record->deposit_number}?")
                    ->modalDescription('Setoran tidak akan mengubah saldo. Catatan pemeriksaan wajib ditulis.')
                    ->schema([
                        Textarea::make('reason')->label('Alasan penolakan')->required()->minLength(10)->maxLength(1000)->rows(4),
                    ])
                    ->action(fn (Deposit $record, array $data): Deposit => self::reviewService()->reject(self::actor(), $record, (string) $data['reason'], self::reviewIdempotencyKey()))
                    ->successNotificationTitle('Setoran bernilai tinggi ditolak.'),
                Action::make('correct')
                    ->label('Koreksi')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('warning')
                    ->visible(fn (Deposit $record): bool => self::correctionService()->canCorrect(self::actor(), $record) && $record->status === Deposit::STATUS_FINAL)
                    ->authorize(fn (Deposit $record): bool => self::correctionService()->canCorrect(self::actor(), $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Deposit $record): string => "Koreksi setoran {$record->deposit_number}?")
                    ->modalDescription('Transaksi asli tetap tersimpan. Sistem menambahkan catatan koreksi dan mutasi penyesuaian baru.')
                    ->modalSubmitActionLabel('Catat koreksi')
                    ->schema([
                        TextInput::make('new_value')->label('Nilai benar')->numeric()->integer()->minValue(0)->required(),
                        Textarea::make('reason')->label('Alasan dan referensi pemeriksaan')->required()->minLength(10)->maxLength(1000)->rows(4),
                        FileUpload::make('evidence')->label('Bukti koreksi')->required()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->maxSize(5120)->storeFiles(false),
                    ])
                    ->action(fn (Deposit $record, array $data): mixed => self::correctionService()->correct(self::actor(), $record, (int) $data['new_value'], (string) $data['reason'], self::idempotencyKey(), self::uploadedFile($data['evidence'] ?? null)))
                    ->successNotificationTitle('Koreksi setoran tercatat.'),
                Action::make('reverse')
                    ->label('Balikkan')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (Deposit $record): bool => self::correctionService()->canReverse(self::actor(), $record) && $record->status === Deposit::STATUS_FINAL)
                    ->authorize(fn (Deposit $record): bool => self::correctionService()->canReverse(self::actor(), $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Deposit $record): string => "Balikkan setoran {$record->deposit_number}?")
                    ->modalDescription('Sistem menambahkan mutasi pembalik. Transaksi asli tetap tersimpan.')
                    ->modalSubmitActionLabel('Balikkan setoran')
                    ->schema([
                        Textarea::make('reason')->label('Alasan dan referensi pemeriksaan')->required()->minLength(10)->maxLength(1000)->rows(4),
                        FileUpload::make('evidence')->label('Bukti pembalikan')->required()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->maxSize(5120)->storeFiles(false),
                    ])
                    ->action(fn (Deposit $record, array $data): mixed => self::correctionService()->reverse(self::actor(), $record, (string) $data['reason'], self::idempotencyKey(), self::uploadedFile($data['evidence'] ?? null)))

                    ->successNotificationTitle('Pembalikan setoran tercatat.'),
            ]);
    }

    /** @return Builder<Deposit> */
    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();

        return $actor instanceof User
            ? self::correctionService()->visibleDeposits($actor)
            : Deposit::query()->whereKey([]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageDeposits::route('/')];
    }

    /** @return array<string, string> */
    private static function inspectionData(Deposit $record): array
    {
        $record->loadMissing(['items', 'ledgerEntries', 'media', 'customer.ledgerAccount.holds']);

        return [
            'snapshot' => $record->items->map(static fn ($item): string => sprintf(
                '%s · Kondisi: %s · Berat: %s kg · Harga per kg: Rp %s · Subtotal: Rp %s',
                $item->waste_type_name,
                $item->condition_name,
                WeightFormatter::format($item->weight_kg),
                number_format((int) $item->price_per_unit, 0, ',', '.'),
                number_format((int) $item->subtotal, 0, ',', '.'),
            ))->implode("\n"),
            'ledger' => $record->ledgerEntries->map(static fn (LedgerEntry $entry): string => sprintf(
                '%s · %s · %s · Nominal: Rp %s · Saldo setelah: Rp %s · Efektif: %s',
                $entry->entry_number,
                $entry->direction === LedgerEntry::DIRECTION_IN ? 'Masuk' : 'Keluar',
                match ($entry->kind) {
                    LedgerEntry::KIND_DEPOSIT => 'Setoran',
                    LedgerEntry::KIND_CORRECTION => 'Koreksi',
                    LedgerEntry::KIND_REVERSAL => 'Pembalikan',
                    LedgerEntry::KIND_ADJUSTMENT => 'Penyesuaian',
                    default => $entry->kind,
                },
                number_format((int) $entry->amount, 0, ',', '.'),
                number_format((int) $entry->balance_after, 0, ',', '.'),
                $entry->effective_at->format('d M Y H:i'),
            ))->implode("\n"),
            'holds' => $record->customer?->ledgerAccount?->holds->map(static fn ($hold): string => sprintf(
                '%s · Nominal: Rp %s · Status: %s · Referensi: %s',
                $hold->hold_number,
                number_format((int) $hold->amount, 0, ',', '.'),
                StatusLabel::for($hold->status),
                $hold->source_key,
            ))->implode("\n") ?? 'Tidak ada dana yang ditahan.',
            'evidence' => $record->media->map(static fn ($media): string => $media->original_name.' ('.$media->mime_type.')')->implode("\n"),
        ];
    }

    private static function correctionService(): TransactionCorrectionService
    {
        return app(TransactionCorrectionService::class);
    }

    private static function verificationRotation(): RotateDepositVerificationToken
    {
        return app(RotateDepositVerificationToken::class);
    }

    private static function reviewService(): DepositReviewService
    {
        return app(DepositReviewService::class);
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    private static function idempotencyKey(): string
    {
        return 'filament-correction-'.Str::lower(Str::random(24));
    }

    private static function reviewIdempotencyKey(): string
    {
        return 'filament-deposit-review-'.Str::lower(Str::random(24));
    }

    private static function uploadedFile(mixed $value): ?UploadedFile
    {
        return $value instanceof UploadedFile ? $value : null;
    }
}
