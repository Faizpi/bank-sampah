<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WasteCategories;

use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Filament\Resources\WasteMaster\Models\WasteCategories\Pages\ManageWasteCategories;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
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

final class WasteCategoryResource extends Resource
{
    protected static ?string $model = WasteCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationParentItem = 'Katalog Sampah';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationLabel = 'Kategori Sampah';

    protected static ?string $modelLabel = 'kategori sampah';

    protected static ?string $pluralModelLabel = 'kategori sampah';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas kategori')->schema([
                TextInput::make('code')->label('Kode')->required()->maxLength(30)->regex('/^[A-Za-z0-9_-]+$/')->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nama')->required()->minLength(2)->maxLength(120),
                TextInput::make('sort_order')->label('Urutan')->numeric()->integer()->minValue(0)->maxValue(9999)->default(0)->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->columns([
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            TextColumn::make('sort_order')->label('Urutan')->sortable(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->recordActions([
            EditAction::make()->using(fn (WasteCategory $record, array $data): WasteCategory => tap($record, fn () => app(ManageWasteMaster::class)->updateCategory(auth()->user(), $record, $data['code'], $data['name'], (int) $data['sort_order']))),
            Action::make('activate')->label('Aktifkan kembali')->icon(Heroicon::OutlinedCheckCircle)->color('success')->authorize('activate')->requiresConfirmation()->modalHeading(fn (WasteCategory $record): string => "Aktifkan kategori {$record->name}?")->modalDescription('Kategori ini kembali tersedia untuk jenis sampah baru. Data historis tetap tersimpan.')->modalSubmitActionLabel('Aktifkan kategori')->visible(fn (WasteCategory $record): bool => ! $record->is_active)->action(fn (WasteCategory $record) => app(ManageWasteMaster::class)->activate(auth()->user(), $record)),
            Action::make('deactivate')->label('Nonaktifkan')->authorize('deactivate')->requiresConfirmation()->modalHeading(fn (WasteCategory $record): string => "Nonaktifkan kategori {$record->name}?")->modalDescription('Kategori ini tidak tersedia untuk transaksi baru. Data historis tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan kategori')->visible(fn (WasteCategory $record): bool => $record->is_active)->action(fn (WasteCategory $record) => app(ManageWasteMaster::class)->deactivate(auth()->user(), $record)),
        ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageWasteCategories::route('/')];
    }
}
