<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomersRegions\Models\Dusuns;

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Filament\Resources\CustomersRegions\Models\Dusuns\Pages\ManageDusuns;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

final class DusunResource extends Resource
{
    protected static ?string $model = Dusun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationParentItem = 'Wilayah';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Dusun';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->columns([
            TextColumn::make('code')->searchable(),
            TextColumn::make('name')->searchable(),
            IconColumn::make('is_active')->boolean(),
        ])->recordActions([
            EditAction::make()->using(fn (Dusun $record, array $data): Dusun => tap($record, fn () => app(ManageRegions::class)->updateDusun(auth()->user(), $record, $data['code'], $data['name']))),
            Action::make('activate')->label('Aktifkan kembali')->icon(Heroicon::OutlinedCheckCircle)->color('success')->authorize('activate')->requiresConfirmation()->modalHeading(fn (Dusun $record): string => "Aktifkan dusun {$record->name}?")->modalDescription('Dusun ini kembali tersedia untuk wilayah baru. Data historis tetap tersimpan.')->modalSubmitActionLabel('Aktifkan dusun')->visible(fn (Dusun $record): bool => ! $record->is_active)->action(fn (Dusun $record) => app(ManageRegions::class)->activate(auth()->user(), $record)),
            Action::make('deactivate')->label('Nonaktifkan')->authorize('deactivate')->requiresConfirmation()->modalHeading(fn (Dusun $record): string => "Nonaktifkan dusun {$record->name}?")->modalDescription('Dusun ini tidak tersedia untuk data wilayah baru. Data historis tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan dusun')->visible(fn (Dusun $record): bool => $record->is_active)->action(fn (Dusun $record) => app(ManageRegions::class)->deactivate(auth()->user(), $record)),
        ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageDusuns::route('/')];
    }
}
