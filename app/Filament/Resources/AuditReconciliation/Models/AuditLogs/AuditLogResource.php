<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditReconciliation\Models\AuditLogs;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\AuditReconciliation\Services\AuditQueryService;
use App\Filament\Resources\AuditReconciliation\Models\AuditLogs\Pages\ManageAuditLogs;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit log';

    protected static ?string $pluralModelLabel = 'audit log';

    public static function shouldRegisterNavigation(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'transaction.correct');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('action')->defaultSort('occurred_at', 'desc')->columns([
            TextColumn::make('occurred_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('action')->label('Tindakan')->searchable(),
            TextColumn::make('actor_type')->label('Pelaku'),
            TextColumn::make('auditable_type')->label('Objek'),
            TextColumn::make('correlation_id')->label('Korelasi')->copyable(),
        ])->filters([
            Filter::make('audit_filters')->label('Filter audit')->form([
                DatePicker::make('start')->label('Mulai tanggal'),
                DatePicker::make('end')->label('Sampai tanggal'),
                Select::make('action')->label('Tindakan')->options(fn (): array => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action', 'action')->all())->searchable(),
                Select::make('actor_id')->label('Pelaku')->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                TextInput::make('correlation_id')->label('ID korelasi')->placeholder('UUID korelasi'),
            ])->query(static function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['action'] ?? null, static fn (Builder $query, string $action): Builder => $query->where('action', $action))
                    ->when($data['actor_id'] ?? null, static fn (Builder $query, string $actorId): Builder => $query->where('actor_id', (int) $actorId))
                    ->when($data['correlation_id'] ?? null, static fn (Builder $query, string $correlationId): Builder => $query->where('correlation_id', trim($correlationId)))
                    ->when($data['start'] ?? null, static fn (Builder $query, string $start): Builder => $query->whereDate('occurred_at', '>=', $start))
                    ->when($data['end'] ?? null, static fn (Builder $query, string $end): Builder => $query->whereDate('occurred_at', '<=', $end));
            }),
        ]);
    }

    /** @return Builder<AuditLog> */
    public static function getEloquentQuery(): Builder
    {
        return app(AuditQueryService::class)->query(auth()->user(), []);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }
}
