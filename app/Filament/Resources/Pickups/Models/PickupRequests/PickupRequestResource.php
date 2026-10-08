<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pickups\Models\PickupRequests;

use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Filament\Actions\PickupExceptionActions;
use App\Filament\Resources\Pickups\Models\PickupRequests\Pages\ManagePickupRequests;
use App\Models\User;
use App\Support\StatusLabel;
use App\Support\WorkQueue;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class PickupRequestResource extends Resource
{
    protected static ?string $model = PickupRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Penjemputan';

    protected static ?string $modelLabel = 'penjemputan';

    protected static ?string $pluralModelLabel = 'penjemputan';

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
            TextColumn::make('address')->label('Alamat'),
            TextColumn::make('status')->label('Status')->formatStateUsing(fn ($state): string => StatusLabel::for($state))->badge(),
            TextColumn::make('assignedStaff.name')->label('Pengambil tugas')->placeholder('Belum diambil'),
            TextColumn::make('serviceArea.name')->label('Area'),

        ])->filters([
            SelectFilter::make('status')->label('Status')->options(collect(PickupStatus::cases())->mapWithKeys(fn ($status): array => [$status->value => StatusLabel::for($status)])->all()),
            SelectFilter::make('queue')->label('Antrean')->options(['pickup_today' => 'Penjemputan hari ini', 'pickup_overdue' => 'Penjemputan terlambat'])->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? WorkQueue::apply($query, (string) $data['value']) : $query),

        ])->recordActions(PickupExceptionActions::make());
    }

    /** @return Builder<PickupRequest> */
    public static function getEloquentQuery(): Builder
    {
        return app(PickupService::class)->visibleFor(self::actor());
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManagePickupRequests::route('/')];
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
