<?php

declare(strict_types=1);

namespace App\Filament\Resources\Groceries\Models\GroceryRedemptions;

use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Filament\Actions\ResolveServiceException;
use App\Filament\Resources\Groceries\Models\GroceryRedemptions\Pages\ManageGroceryRedemptions;
use App\Models\User;
use App\Support\StatusLabel;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class GroceryRedemptionResource extends Resource
{
    protected static ?string $model = GroceryRedemption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Penukaran Sembako';

    protected static ?string $modelLabel = 'penukaran sembako';

    protected static ?string $pluralModelLabel = 'penukaran sembako';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('request_number')->label('Nomor')->disabled(), TextInput::make('status')->label('Status')->disabled()]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('request_number')->label('Nomor')->searchable(),
            TextColumn::make('customer.name')->label('Warga')->searchable(),
            TextColumn::make('value_snapshot')->label('Nilai')->money('IDR'),
            TextColumn::make('status')->label('Status')->formatStateUsing(fn ($state): string => StatusLabel::for($state))->badge(),
            TextColumn::make('claimant.name')->label('Pengambil tugas')->placeholder('Belum diambil'),
            TextColumn::make('serviceArea.name')->label('Area'),
            TextColumn::make('balanceHold.status')->label('Saldo tertahan')->badge(),
            TextColumn::make('exception_reason')->label('Pengecualian')->wrap(),
        ])->filters([
            SelectFilter::make('status')->label('Status')->options(collect(GroceryStatus::cases())->mapWithKeys(fn ($status): array => [$status->value => StatusLabel::for($status)])->all()),
            Filter::make('exception')->label('Perlu pemeriksaan Admin')->query(fn (Builder $query): Builder => $query->whereNotNull('exception_reported_at')->whereNull('exception_resolved_at')),
        ])->recordActions([ResolveServiceException::freeze(), ResolveServiceException::make()]);
    }

    /** @return Builder<GroceryRedemption> */
    public static function getEloquentQuery(): Builder
    {
        return app(GroceryService::class)->visibleFor(self::actor());
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageGroceryRedemptions::route('/')];
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
