<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WastePrices;

use App\Domain\WasteMaster\Models\WastePrice;
use App\Filament\Resources\WasteMaster\Models\WastePrices\Pages\ManageWastePrices;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class WastePriceResource extends Resource
{
    protected static ?string $model = WastePrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

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
                TextInput::make('price')->label('Harga rupiah per satuan')->numeric()->integer()->minValue(0)->maxValue(9_000_000_000_000_000)->required(),
                Checkbox::make('zero_price_confirmed')->label('Saya mengonfirmasi harga nol sebagai kebijakan penerimaan tanpa nilai.')->visible(fn ($get): bool => (int) $get('price') === 0)->accepted(),
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
            TextColumn::make('effective_to')->label('Sampai')->dateTime('d M Y, H:i')->placeholder('Terbuka')->sortable(),
            TextColumn::make('createdBy.name')->label('Dibuat oleh')->placeholder('Sistem'),
        ])->defaultSort('effective_from', 'desc');
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
}
