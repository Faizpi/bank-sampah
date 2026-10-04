<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WasteUnits;

use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Filament\Resources\WasteMaster\Models\WasteUnits\Pages\ManageWasteUnits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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

final class WasteUnitResource extends Resource
{
    protected static ?string $model = WasteUnit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationParentItem = 'Katalog Sampah';

    protected static ?int $navigationSort = 130;

    protected static ?string $navigationLabel = 'Satuan Sampah';

    protected static ?string $modelLabel = 'satuan sampah';

    protected static ?string $pluralModelLabel = 'satuan sampah';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas satuan')->schema([
                TextInput::make('code')->label('Kode')->required()->maxLength(30)->regex('/^[A-Za-z0-9_-]+$/')->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nama')->required()->minLength(2)->maxLength(120),
                TextInput::make('symbol')->label('Simbol')->required()->maxLength(30),
                Select::make('classification')->label('Klasifikasi')->required()->options([
                    WasteUnit::CLASSIFICATION_WEIGHT => 'Berat fisik',
                    WasteUnit::CLASSIFICATION_NON_WEIGHT => 'Non-berat',
                ])->live(),
                TextInput::make('conversion_factor_to_kg')->label('Faktor ke kg')->numeric()->minValue(0.000001)->visible(fn ($get): bool => $get('classification') === WasteUnit::CLASSIFICATION_WEIGHT)->helperText('Kosongkan untuk kilogram atau satuan yang belum memiliki konversi.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->columns([
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            TextColumn::make('symbol')->label('Simbol'),
            TextColumn::make('classification')->label('Klasifikasi'),
            TextColumn::make('conversion_factor_to_kg')->label('Faktor kg'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->recordActions([
            EditAction::make()->using(fn (WasteUnit $record, array $data): WasteUnit => tap($record, fn () => app(ManageWasteMaster::class)->updateUnit(auth()->user(), $record, $data['code'], $data['name'], $data['symbol'], $data['classification'], $data['conversion_factor_to_kg'] ?? null))),
            Action::make('activate')->label('Aktifkan kembali')->icon(Heroicon::OutlinedCheckCircle)->color('success')->authorize('activate')->requiresConfirmation()->modalHeading(fn (WasteUnit $record): string => "Aktifkan satuan {$record->name}?")->modalDescription('Satuan ini kembali tersedia untuk jenis sampah baru. Data historis tetap tersimpan.')->modalSubmitActionLabel('Aktifkan satuan')->visible(fn (WasteUnit $record): bool => ! (bool) $record->is_active)->action(fn (WasteUnit $record) => app(ManageWasteMaster::class)->activate(auth()->user(), $record)),
            Action::make('deactivate')->label('Nonaktifkan')->icon(Heroicon::OutlinedArchiveBox)->color('danger')->authorize('deactivate')->requiresConfirmation()->modalHeading(fn (WasteUnit $record): string => "Nonaktifkan satuan {$record->name}?")->modalDescription('Satuan ini tidak tersedia untuk jenis sampah baru. Data historis tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan satuan')->visible(fn (WasteUnit $record): bool => (bool) $record->is_active)->action(fn (WasteUnit $record) => app(ManageWasteMaster::class)->deactivate(auth()->user(), $record)),
        ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageWasteUnits::route('/')];
    }
}
