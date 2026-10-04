<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomersRegions\Models\Rws;

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rw;
use App\Filament\Resources\CustomersRegions\Models\Rws\Pages\ManageRws;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

final class RwResource extends Resource
{
    protected static ?string $model = Rw::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationParentItem = 'Wilayah';

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'RW';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('dusun_id')->relationship('dusun', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))->required(),
            TextInput::make('code')->required()->maxLength(30),
            TextInput::make('name')->required()->maxLength(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->columns([
            TextColumn::make('dusun.name')->label('Dusun')->searchable(),
            TextColumn::make('code')->searchable(),
            TextColumn::make('name')->searchable(),
            IconColumn::make('is_active')->boolean(),
        ])->recordActions([
            EditAction::make()->using(fn (Rw $record, array $data): Rw => tap($record, fn () => app(ManageRegions::class)->updateRw(auth()->user(), $record, Dusun::query()->findOrFail($data['dusun_id']), $data['code'], $data['name']))),
            Action::make('activate')->label('Aktifkan kembali')->icon(Heroicon::OutlinedCheckCircle)->color('success')->authorize('activate')->requiresConfirmation()->modalHeading(fn (Rw $record): string => "Aktifkan RW {$record->name}?")->modalDescription('RW ini kembali tersedia untuk data wilayah baru. Data historis tetap tersimpan.')->modalSubmitActionLabel('Aktifkan RW')->visible(fn (Rw $record): bool => ! $record->is_active)->action(fn (Rw $record) => app(ManageRegions::class)->activate(auth()->user(), $record)),
            Action::make('deactivate')->label('Nonaktifkan')->authorize('deactivate')->requiresConfirmation()->modalHeading(fn (Rw $record): string => "Nonaktifkan RW {$record->name}?")->modalDescription('RW ini tidak tersedia untuk data wilayah baru. Data historis tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan RW')->visible(fn (Rw $record): bool => $record->is_active)->action(fn (Rw $record) => app(ManageRegions::class)->deactivate(auth()->user(), $record)),
        ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageRws::route('/')];
    }
}
