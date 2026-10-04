<?php

declare(strict_types=1);

namespace App\Filament\Resources\Groceries\Models\GroceryPackages;

use App\Domain\Groceries\Actions\ManageGroceryPackages as ManageGroceryPackageAction;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Filament\Resources\Groceries\Models\GroceryPackages\Pages\ManageGroceryPackages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class GroceryPackageResource extends Resource
{
    protected static ?string $model = GroceryPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Paket Sembako';

    protected static ?string $modelLabel = 'paket sembako';

    protected static ?string $pluralModelLabel = 'paket sembako';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Definisi paket')->schema([
                TextInput::make('code')->label('Kode')->required()->minLength(2)->maxLength(40)->regex('/^[A-Za-z0-9_-]+$/')->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nama')->required()->minLength(2)->maxLength(120),
                Textarea::make('contents')->label('Isi deskriptif')->required()->minLength(3)->maxLength(1000)->rows(5),
                TextInput::make('value')->label('Nilai penukaran (rupiah)')->required()->numeric()->integer()->minValue(1),
                DatePicker::make('active_from')->label('Mulai aktif'),
                DatePicker::make('active_until')->label('Berakhir aktif'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->columns([
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            TextColumn::make('value')->label('Nilai')->money('IDR')->sortable(),
            TextColumn::make('active_from')->label('Mulai')->date('d M Y'),
            TextColumn::make('active_until')->label('Berakhir')->date('d M Y')->placeholder('Tidak dibatasi'),
            IconColumn::make('status')->label('Aktif')->boolean(fn (string $state): bool => $state === 'aktif'),
        ])->recordActions([
            EditAction::make()->using(fn (GroceryPackage $record, array $data): GroceryPackage => app(ManageGroceryPackageAction::class)->update(auth()->user(), $record, (string) $data['code'], (string) $data['name'], (string) $data['contents'], (int) $data['value'], $data['active_from'] ?? null, $data['active_until'] ?? null)),
            Action::make('deactivate')
                ->label('Nonaktifkan')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('danger')
                ->authorize('deactivate')
                ->requiresConfirmation()
                ->visible(fn (GroceryPackage $record): bool => $record->status === 'aktif')
                ->action(fn (GroceryPackage $record): GroceryPackage => app(ManageGroceryPackageAction::class)->deactivate(auth()->user(), $record)),
        ]);
    }

    /** @return Builder<GroceryPackage> */
    public static function getEloquentQuery(): Builder
    {
        return GroceryPackage::query()->with('media');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageGroceryPackages::route('/')];
    }
}
