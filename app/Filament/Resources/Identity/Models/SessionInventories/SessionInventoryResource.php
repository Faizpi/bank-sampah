<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\SessionInventories;

use App\Actions\Auth\RevokeDatabaseSession;
use App\Authorization\PermissionChecker;
use App\Domain\Identity\Models\DatabaseSession;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Filament\Resources\Identity\Models\SessionInventories\Actions\RevokeSessionAction;
use App\Filament\Resources\Identity\Models\SessionInventories\Pages\ManageSessionInventories;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

final class SessionInventoryResource extends Resource
{
    protected static ?string $model = DatabaseSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'Sesi Pengguna';

    protected static ?string $modelLabel = 'sesi pengguna';

    protected static ?string $pluralModelLabel = 'sesi pengguna';

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && app(PermissionChecker::class)->allows($actor, 'user.view')
            && (app(PermissionChecker::class)->allows($actor, 'user.view.all')
                || app(PermissionChecker::class)->allows($actor, 'user.view.area'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('last_activity')
                    ->label('Aktivitas terakhir')
                    ->dateTime('d M Y H:i'),
                TextColumn::make('expires_at')
                    ->label('Status kedaluwarsa')
                    ->state(fn (DatabaseSession $record): string => $record->expires_at === null
                        ? 'Tidak diketahui'
                        : ($record->expires_at->isPast() ? 'Kedaluwarsa' : 'Aktif'))
                    ->badge()
                    ->color(fn (DatabaseSession $record): string => $record->expires_at?->isPast() ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Pengguna')
                    ->relationship(
                        'user',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn(
                            'id',
                            app(VisibleUsers::class)->queryFor(self::actor())->select('id'),
                        ),
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                RevokeSessionAction::make('revoke')
                    ->label('Akhiri sesi')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->authorize(fn (DatabaseSession $record): bool => auth()->user() instanceof User
                        && auth()->user()->can('view', $record->user)
                        && auth()->user()->can('revokeSession', $record->user))
                    ->requiresConfirmation()
                    ->modalHeading('Akhiri sesi pengguna')
                    ->modalDescription('Sesi yang dipilih akan diakhiri. Tindakan ini tidak dapat dibatalkan.')
                    ->modalSubmitActionLabel('Akhiri sesi')
                    ->action(function (DatabaseSession $record): void {
                        app(RevokeDatabaseSession::class)->handle(
                            self::actor(),
                            $record->user,
                            (string) $record->getKey(),
                            self::correlationId(),
                        );
                    }),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageSessionInventories::route('/')];
    }

    /** @return Builder<DatabaseSession> */
    public static function getEloquentQuery(): Builder
    {
        $query = DatabaseSession::query()->select(['id', 'user_id', 'last_activity', 'expires_at']);
        $actor = auth()->user();

        if (! $actor instanceof User
            || ! app(PermissionChecker::class)->allows($actor, 'user.view')
            || (! app(PermissionChecker::class)->allows($actor, 'user.view.all')
                && ! app(PermissionChecker::class)->allows($actor, 'user.view.area'))) {
            return $query->whereIn('user_id', []);
        }

        return $query->whereIn('user_id', app(VisibleUsers::class)->queryFor($actor)->select('id'));
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    private static function correlationId(): string
    {
        $correlationId = request()->attributes->get('correlation_id');

        return is_string($correlationId) && Str::isUuid($correlationId)
            ? strtolower($correlationId)
            : (string) Str::uuid();
    }
}
