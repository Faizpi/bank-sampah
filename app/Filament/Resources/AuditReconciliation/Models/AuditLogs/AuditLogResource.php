<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditReconciliation\Models\AuditLogs;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\AuditReconciliation\Services\AuditQueryService;
use App\Filament\Resources\AuditReconciliation\Models\AuditLogs\Pages\ManageAuditLogs;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan & Rekonsiliasi';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit log';

    protected static ?string $pluralModelLabel = 'audit log';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('action')->columns([
            TextColumn::make('occurred_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('action')->label('Aksi')->searchable(),
            TextColumn::make('actor_type')->label('Pelaku'),
            TextColumn::make('auditable_type')->label('Objek'),
            TextColumn::make('correlation_id')->label('Korelasi')->copyable(),
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
