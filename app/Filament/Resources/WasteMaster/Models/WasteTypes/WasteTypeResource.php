<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WasteTypes;

use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Filament\Resources\WasteMaster\Models\WasteTypes\Pages\ManageWasteTypes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

final class WasteTypeResource extends Resource
{
    protected static ?string $model = WasteType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationParentItem = 'Katalog Sampah';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Jenis Sampah';

    protected static ?string $modelLabel = 'jenis sampah';

    protected static ?string $pluralModelLabel = 'jenis sampah';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas jenis')->schema([
                TextInput::make('code')->label('Kode')->required()->maxLength(30)->regex('/^[A-Za-z0-9_-]+$/')->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nama')->required()->minLength(2)->maxLength(120),
                Select::make('waste_category_id')->label('Kategori')->relationship('category', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))->required()->searchable()->preload(),
                Select::make('waste_unit_id')->label('Satuan')->relationship('unit', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))->required()->searchable()->preload(),
                Select::make('condition_ids')->label('Kondisi diterima')->relationship('conditions', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))->multiple()->required()->minItems(1)->searchable()->preload(),
                TextInput::make('sort_order')->label('Urutan')->numeric()->integer()->minValue(0)->maxValue(9999)->default(0)->required(),
                Checkbox::make('is_plastic')->label('Kelompok plastik'),
                Checkbox::make('is_active')->label('Aktif')->default(true),
                Textarea::make('education_description')->label('Edukasi kontekstual')->maxLength(5000)->rows(5)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->columns([
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            TextColumn::make('category.name')->label('Kategori')->sortable(),
            TextColumn::make('unit.symbol')->label('Satuan'),
            TextColumn::make('conditions.name')->label('Kondisi'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->recordActions([
            EditAction::make()->using(fn (WasteType $record, array $data): WasteType => tap($record, function () use ($record, $data): void {
                /** @var WasteCategory $category */
                $category = WasteCategory::query()->findOrFail($data['waste_category_id']);
                /** @var WasteUnit $unit */
                $unit = WasteUnit::query()->findOrFail($data['waste_unit_id']);
                app(ManageWasteMaster::class)->updateType(auth()->user(), $record, $category, $unit, $data['code'], $data['name'], $data['education_description'] ?? null, (int) $data['sort_order'], (bool) ($data['is_plastic'] ?? false), (bool) ($data['is_active'] ?? false), array_map('intval', $data['condition_ids'] ?? []));
            })),
            Action::make('activate')->label('Aktifkan kembali')->icon(Heroicon::OutlinedCheckCircle)->color('success')->authorize('activate')->requiresConfirmation()->modalHeading(fn (WasteType $record): string => "Aktifkan jenis {$record->name}?")->modalDescription('Jenis ini kembali tersedia untuk transaksi baru. Kategori dan kondisi yang dipakai harus aktif.')->modalSubmitActionLabel('Aktifkan jenis')->visible(fn (WasteType $record): bool => ! $record->is_active)->action(fn (WasteType $record) => app(ManageWasteMaster::class)->activate(auth()->user(), $record)),
            Action::make('deactivate')->label('Nonaktifkan')->authorize('deactivate')->requiresConfirmation()->modalHeading(fn (WasteType $record): string => "Nonaktifkan jenis {$record->name}?")->modalDescription('Jenis ini tidak tersedia untuk transaksi baru. Data transaksi lama tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan jenis')->visible(fn (WasteType $record): bool => $record->is_active)->action(fn (WasteType $record) => app(ManageWasteMaster::class)->deactivate(auth()->user(), $record)),
        ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageWasteTypes::route('/')];
    }
}
