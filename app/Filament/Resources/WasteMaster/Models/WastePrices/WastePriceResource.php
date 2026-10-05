<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WastePrices;

use App\Domain\WasteMaster\Actions\ManageWastePricing;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Filament\Resources\WasteMaster\Models\WastePrices\Pages\ManageWastePrices;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class WastePriceResource extends Resource
{
    protected static ?string $model = WastePrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationParentItem = 'Katalog Sampah';

    protected static ?int $navigationSort = 120;

    protected static ?string $navigationLabel = 'Harga Sampah';

    protected static ?string $modelLabel = 'harga sampah';

    protected static ?string $pluralModelLabel = 'harga sampah';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Periode harga')->schema([
                Select::make('waste_type_id')->label('Jenis sampah')->relationship('wasteType', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true)->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('is_active', true)))->required()->searchable()->preload(),
                Select::make('waste_condition_id')->label('Kondisi')->relationship('condition', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))->required()->searchable()->preload(),
                TextInput::make('price')->label('Harga per satuan (Rp)')->numeric()->integer()->minValue(0)->maxValue(9_000_000_000_000_000)->required(),
                Checkbox::make('zero_price_confirmed')->label('Saya menyetujui harga Rp0 karena sampah ini diterima tanpa nilai.')->visible(fn ($get): bool => (int) $get('price') === 0)->accepted(),
                DateTimePicker::make('effective_from')->label('Berlaku mulai')->seconds(false)->native(false)->required(),
                DateTimePicker::make('effective_to')->label('Berlaku sampai')->seconds(false)->native(false)->after('effective_from'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('id')->columns([
            TextColumn::make('wasteType.name')->label('Jenis')->searchable()->sortable(),
            TextColumn::make('condition.name')->label('Kondisi')->searchable(),
            TextColumn::make('price')->label('Harga')->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.'))->sortable(),
            TextColumn::make('effective_from')->label('Mulai')->dateTime('d M Y, H:i')->sortable(),
            TextColumn::make('effective_to')->label('Sampai')->dateTime('d M Y, H:i')->placeholder('Tanpa batas')->sortable(),
            TextColumn::make('status')->label('Status')->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Aktif' => 'success',
                    'Akan datang' => 'info',
                    default => 'gray',
                })
                ->state(fn (WastePrice $record): string => $record->effectiveFromDate()->isFuture()
                    ? 'Akan datang'
                    : ($record->effectiveToDate() === null || $record->effectiveToDate()->isFuture() ? 'Aktif' : 'Selesai')),
            TextColumn::make('createdBy.name')->label('Dibuat oleh')->placeholder('Sistem'),
        ])->defaultSort('effective_from', 'desc')->recordActions([
            Action::make('replace')
                ->label('Ubah harga')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('warning')
                ->authorize(fn (WastePrice $record): bool => auth()->user()?->can('create', WastePrice::class) ?? false)
                ->visible(fn (WastePrice $record): bool => $record->effective_to === null)
                ->requiresConfirmation()
                ->modalHeading(fn (WastePrice $record): string => "Ubah harga {$record->wasteType->name} ({$record->condition->name})?")
                ->modalDescription('Harga lama akan ditutup pada tanggal berlaku baru dan digantikan oleh harga baru.')
                ->modalSubmitActionLabel('Simpan harga baru')
                ->schema([
                    TextInput::make('price')->label('Harga per satuan (Rp)')->numeric()->integer()->minValue(0)->maxValue(9_000_000_000_000_000)->required(),
                    Checkbox::make('zero_price_confirmed')->label('Saya menyetujui harga Rp0 karena sampah ini diterima tanpa nilai.')->visible(fn ($get): bool => (int) $get('price') === 0)->accepted(),
                    DateTimePicker::make('effective_from')->label('Berlaku mulai')->seconds(false)->native(false)->required(),
                    DateTimePicker::make('effective_to')->label('Berlaku sampai')->seconds(false)->native(false)->after('effective_from'),
                ])
                ->action(function (WastePrice $record, array $data): void {
                    $pricing = app(ManageWastePricing::class);

                    try {
                        $pricing->replacePeriod(
                            auth()->user(),
                            $record,
                            (int) $data['price'],
                            CarbonImmutable::parse((string) $data['effective_from']),
                            is_string($data['effective_to'] ?? null) ? CarbonImmutable::parse($data['effective_to']) : null,
                            self::correlationId(),
                            (bool) ($data['zero_price_confirmed'] ?? false),
                        );
                        Notification::make()
                            ->title('Harga sampah berhasil diubah')
                            ->body("Periode lama ditutup dan harga baru mulai berlaku pada {$data['effective_from']}.")
                            ->success()
                            ->send();
                    } catch (ValidationException $exception) {
                        $message = collect($exception->errors())->flatten()->first();
                        Notification::make()
                            ->title('Harga sampah belum dapat diubah')
                            ->body(is_string($message) ? $message : 'Periksa harga dan periode berlaku yang dipilih.')
                            ->danger()
                            ->send();

                        throw $exception;
                    }
                }),
            Action::make('deactivate')
                ->label('Nonaktifkan')
                ->icon(Heroicon::OutlinedPower)
                ->color('danger')
                ->authorize(fn (WastePrice $record): bool => auth()->user()?->can('create', WastePrice::class) ?? false)
                ->visible(fn (WastePrice $record): bool => $record->effective_to === null)
                ->requiresConfirmation()
                ->modalHeading(fn (WastePrice $record): string => "Nonaktifkan harga {$record->wasteType->name} ({$record->condition->name})?")
                ->modalDescription('Harga ini akan berhenti berlaku sekarang dan tidak akan muncul lagi di halaman publik.')
                ->modalSubmitActionLabel('Nonaktifkan harga')
                ->action(function (WastePrice $record): void {
                    $pricing = app(ManageWastePricing::class);

                    try {
                        $pricing->deactivatePeriod(
                            auth()->user(),
                            $record,
                            CarbonImmutable::now(),
                            self::correlationId(),
                        );
                        Notification::make()
                            ->title('Harga sampah dinonaktifkan')
                            ->body('Periode harga berakhir sekarang. Riwayat harga tetap tersimpan.')
                            ->success()
                            ->send();
                    } catch (ValidationException $exception) {
                        $message = collect($exception->errors())->flatten()->first();
                        Notification::make()
                            ->title('Harga sampah belum dapat dinonaktifkan')
                            ->body(is_string($message) ? $message : 'Periode harga tidak dapat dinonaktifkan.')
                            ->danger()
                            ->send();

                        throw $exception;
                    }
                }),
        ]);
    }

    /** @return Builder<WastePrice> */
    public static function getEloquentQuery(): Builder
    {
        return WastePrice::query()->with(['wasteType', 'condition', 'createdBy']);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageWastePrices::route('/')];
    }

    private static function correlationId(): string
    {
        $correlationId = request()->attributes->get('correlation_id');

        return is_string($correlationId) && Str::isUuid($correlationId) ? strtolower($correlationId) : (string) Str::uuid();
    }
}
