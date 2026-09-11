<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WasteConditions;

use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Filament\Resources\WasteMaster\Models\WasteConditions\Pages\ManageWasteConditions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
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

final class WasteConditionResource extends Resource
{
    protected static ?string $model = WasteCondition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationParentItem = 'Katalog Sampah';

    protected static ?int $navigationSort = 110;

    protected static ?string $navigationLabel = 'Kondisi Sampah';

    protected static ?string $modelLabel = 'kondisi sampah';

    protected static ?string $pluralModelLabel = 'kondisi sampah';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas kondisi')->schema([
                TextInput::make('code')->label('Kode')->required()->maxLength(30)->regex('/^[A-Za-z0-9_-]+$/')->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nama')->required()->minLength(2)->maxLength(120),
                TextInput::make('sort_order')->label('Urutan')->numeric()->integer()->minValue(0)->maxValue(9999)->default(0)->required(),
                Textarea::make('description')->label('Deskripsi')->maxLength(2000)->rows(4)->columnSpanFull(),
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
            EditAction::make()->using(fn (WasteCondition $record, array $data): WasteCondition => tap($record, fn () => app(ManageWasteMaster::class)->updateCondition(auth()->user(), $record, $data['code'], $data['name'], $data['description'] ?? null, (int) $data['sort_order']))),
            Action::make('activate')->label('Aktifkan kembali')->icon(Heroicon::OutlinedCheckCircle)->color('success')->authorize('activate')->requiresConfirmation()->modalHeading(fn (WasteCondition $record): string => "Aktifkan kondisi {$record->name}?")->modalDescription('Kondisi ini kembali tersedia untuk jenis sampah baru. Data historis tetap tersimpan.')->modalSubmitActionLabel('Aktifkan kondisi')->visible(fn (WasteCondition $record): bool => ! $record->is_active)->action(fn (WasteCondition $record) => app(ManageWasteMaster::class)->activate(auth()->user(), $record)),
            Action::make('deactivate')->label('Nonaktifkan')->authorize('deactivate')->requiresConfirmation()->modalHeading(fn (WasteCondition $record): string => "Nonaktifkan kondisi {$record->name}?")->modalDescription('Kondisi ini tidak tersedia untuk transaksi baru. Data historis tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan kondisi')->visible(fn (WasteCondition $record): bool => $record->is_active)->action(fn (WasteCondition $record) => app(ManageWasteMaster::class)->deactivate(auth()->user(), $record)),
        ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageWasteConditions::route('/')];
    }
}
