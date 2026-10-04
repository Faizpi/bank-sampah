<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomersRegions\Models\Rts;

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Filament\Resources\CustomersRegions\Models\Rts\Pages\ManageRts;
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

final class RtResource extends Resource
{
    protected static ?string $model = Rt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'RT';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('rw_id')->relationship('rw', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))->required(),
            TextInput::make('code')->required()->maxLength(30),
            TextInput::make('name')->required()->maxLength(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->columns([
            TextColumn::make('rw.name')->label('RW')->searchable(),
            TextColumn::make('code')->searchable(),
            TextColumn::make('name')->searchable(),
            IconColumn::make('is_active')->boolean(),
        ])->recordActions([
            EditAction::make()->using(fn (Rt $record, array $data): Rt => tap($record, fn () => app(ManageRegions::class)->updateRt(auth()->user(), $record, Rw::query()->findOrFail($data['rw_id']), $data['code'], $data['name']))),
            Action::make('deactivate')->authorize('deactivate')->requiresConfirmation()->visible(fn (Rt $record): bool => $record->is_active)->action(fn (Rt $record) => app(ManageRegions::class)->deactivate(auth()->user(), $record)),
        ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageRts::route('/')];
    }
}
