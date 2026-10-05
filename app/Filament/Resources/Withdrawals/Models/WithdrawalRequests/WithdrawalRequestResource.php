<?php

declare(strict_types=1);

namespace App\Filament\Resources\Withdrawals\Models\WithdrawalRequests;

use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Filament\Actions\ResolveServiceException;
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

final class WithdrawalRequestResource extends Resource
{
    protected static ?string $model = WithdrawalRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Pencairan';

    protected static ?string $modelLabel = 'pencairan';

    protected static ?string $pluralModelLabel = 'pencairan';

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
            TextColumn::make('amount')->label('Nilai')->money('IDR'),
            TextColumn::make('status')->label('Status')->formatStateUsing(fn ($state): string => StatusLabel::for($state))->badge(),
            TextColumn::make('payer.name')->label('Pengambil tugas')->placeholder('Belum diambil'),
            TextColumn::make('serviceArea.name')->label('Area'),
            TextColumn::make('balanceHold.status')->label('Saldo tertahan')->badge(),
            TextColumn::make('exception_reason')->label('Pengecualian')->wrap(),
        ])->filters([
            SelectFilter::make('status')->label('Status')->options(collect(WithdrawalStatus::cases())->mapWithKeys(fn ($status): array => [$status->value => StatusLabel::for($status)])->all()),
            Filter::make('exception')->label('Perlu pemeriksaan Admin')->query(fn (Builder $query): Builder => $query->whereNotNull('exception_reported_at')->whereNull('exception_resolved_at')),
        ])->recordActions([ResolveServiceException::freeze(), ResolveServiceException::make()]);
    }

    /** @return Builder<WithdrawalRequest> */
    public static function getEloquentQuery(): Builder
    {
        return app(WithdrawalService::class)->visibleFor(self::actor());
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => Pages\ManageWithdrawalRequests::route('/')];
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
