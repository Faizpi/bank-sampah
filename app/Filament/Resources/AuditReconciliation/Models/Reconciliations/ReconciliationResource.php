<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditReconciliation\Models\Reconciliations;

use App\Domain\AuditReconciliation\Enums\ReconciliationStatus;
use App\Domain\AuditReconciliation\Models\Reconciliation;
use App\Domain\AuditReconciliation\Services\ReconciliationService;
use App\Filament\Resources\AuditReconciliation\Models\Reconciliations\Pages\ManageReconciliations;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class ReconciliationResource extends Resource
{
    protected static ?string $model = Reconciliation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?string $navigationLabel = 'Rekonsiliasi';

    protected static ?string $modelLabel = 'rekonsiliasi';

    protected static ?string $pluralModelLabel = 'rekonsiliasi';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('business_date')->columns([
            TextColumn::make('business_date')->label('Tanggal')->date('d M Y')->sortable(),
            TextColumn::make('version')->label('Versi'),
            TextColumn::make('status')->label('Status')->badge(),
            TextColumn::make('difference')->label('Selisih')->money('IDR'),
            TextColumn::make('creator.name')->label('Pembuat'),
            TextColumn::make('approver.name')->label('Pengesah')->placeholder('Belum disahkan'),
        ])->recordActions([
            Action::make('approve')->label('Sahkan')->icon(Heroicon::OutlinedCheckCircle)->color('success')->visible(fn (Reconciliation $record): bool => $record->status === ReconciliationStatus::Submitted)->authorize('approve')->action(fn (Reconciliation $record): Reconciliation => app(ReconciliationService::class)->approve(auth()->user(), $record)),
            Action::make('reject')->label('Tolak')->icon(Heroicon::OutlinedXCircle)->color('danger')->visible(fn (Reconciliation $record): bool => $record->status === ReconciliationStatus::Submitted)->authorize('approve')->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(10)->maxLength(1000)])->action(fn (Reconciliation $record, array $data): Reconciliation => app(ReconciliationService::class)->reject(auth()->user(), $record, (string) $data['reason'])),
        ]);
    }

    /** @return Builder<Reconciliation> */
    public static function getEloquentQuery(): Builder
    {
        return app(ReconciliationService::class)->visibleFor(auth()->user());
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageReconciliations::route('/')];
    }
}
