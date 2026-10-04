<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditReconciliation\Models\Reconciliations;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Enums\ReconciliationStatus;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\AuditReconciliation\Models\Reconciliation;
use App\Domain\AuditReconciliation\Services\ReconciliationService;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Filament\Resources\AuditReconciliation\Models\Reconciliations\Pages\ManageReconciliations;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

final class ReconciliationResource extends Resource
{
    protected static ?string $model = Reconciliation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan & Rekonsiliasi';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Rekonsiliasi';

    protected static ?string $modelLabel = 'rekonsiliasi';

    protected static ?string $pluralModelLabel = 'rekonsiliasi';

    public static function canViewAny(): bool
    {
        return app(PermissionChecker::class)->allows(auth()->user(), 'reconciliation.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('business_date')->label('Tanggal pelayanan')->required(),
            Select::make('service_area_id')->label('Area pelayanan')->options(fn (): array => self::areaOptions())->searchable()->nullable(),
            TextInput::make('cash_total')->label('Kas fisik penutupan (Rp)')->numeric()->integer()->minValue(0),
            Textarea::make('notes')->label('Catatan awal')->rows(3)->maxLength(2000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('business_date')->columns([
            TextColumn::make('business_date')->label('Tanggal')->date('d M Y')->sortable(),
            TextColumn::make('scope_key')->label('Scope')->badge(),
            TextColumn::make('version')->label('Versi')->sortable(),
            TextColumn::make('status')->label('Status')->badge()->sortable(),
            TextColumn::make('difference')->label('Selisih')->money('IDR'),
            TextColumn::make('creator.name')->label('Pembuat'),
            TextColumn::make('approver.name')->label('Pengesah')->placeholder('Belum disahkan'),
        ])->recordActions([
            Action::make('submit')->label('Ajukan')->icon(Heroicon::OutlinedPaperAirplane)->visible(fn (Reconciliation $record): bool => $record->status === ReconciliationStatus::Draft && app(PermissionChecker::class)->allows(self::actor(), 'reconciliation.create'))
                ->action(fn (Reconciliation $record): Reconciliation => app(ReconciliationService::class)->submit(self::actor(), $record))->successNotificationTitle('Rekonsiliasi diajukan.'),
            Action::make('resolveDiscrepancy')->label('Telusuri selisih')->icon(Heroicon::OutlinedMagnifyingGlass)->color('warning')->visible(fn (Reconciliation $record): bool => $record->status === ReconciliationStatus::Draft && $record->hasOpenDiscrepancy() && app(PermissionChecker::class)->allows(self::actor(), 'reconciliation.create'))->schema([
                Select::make('item_type')->label('Jenis selisih')->options(['cash_difference' => 'Selisih kas'])->required(),
                TextInput::make('actual_total')->label('Kas aktual terverifikasi (Rp)')->numeric()->integer()->minValue(0),
                Textarea::make('note')->label('Alasan dan tindak lanjut')->required()->minLength(10)->maxLength(2000)->rows(5),
            ])->action(fn (Reconciliation $record, array $data): Reconciliation => app(ReconciliationService::class)->resolveDiscrepancy(self::actor(), $record, $data))->successNotificationTitle('Selisih ditelusuri.'),
            Action::make('approve')->label('Sahkan')->icon(Heroicon::OutlinedCheckCircle)->color('success')->visible(fn (Reconciliation $record): bool => $record->status === ReconciliationStatus::Submitted && app(PermissionChecker::class)->allows(self::actor(), 'reconciliation.approve'))->action(fn (Reconciliation $record): Reconciliation => app(ReconciliationService::class)->approve(self::actor(), $record))->successNotificationTitle('Rekonsiliasi disahkan.'),
            Action::make('reject')->label('Tolak')->icon(Heroicon::OutlinedXCircle)->color('danger')->visible(fn (Reconciliation $record): bool => $record->status === ReconciliationStatus::Submitted && app(PermissionChecker::class)->allows(self::actor(), 'reconciliation.approve'))->schema([Textarea::make('reason')->label('Alasan penolakan')->required()->minLength(10)->maxLength(1000)->rows(4)])->action(fn (Reconciliation $record, array $data): Reconciliation => app(ReconciliationService::class)->reject(self::actor(), $record, (string) $data['reason']))->successNotificationTitle('Rekonsiliasi ditolak.'),
            Action::make('revise')->label('Buat revisi')->icon(Heroicon::OutlinedDocumentDuplicate)->color('warning')->visible(fn (Reconciliation $record): bool => in_array($record->status, [ReconciliationStatus::Approved, ReconciliationStatus::Rejected], true) && app(PermissionChecker::class)->allows(self::actor(), 'reconciliation.create'))->schema([Textarea::make('notes')->label('Catatan revisi')->required()->minLength(10)->maxLength(2000)->rows(4)])->action(fn (Reconciliation $record, array $data): Reconciliation => app(ReconciliationService::class)->revise(self::actor(), $record, (string) $data['notes']))->successNotificationTitle('Revisi dibuat.'),
            Action::make('timeline')->label('Timeline audit')->icon(Heroicon::OutlinedClock)->modalHeading('Timeline audit rekonsiliasi')->modalContent(fn (Reconciliation $record): HtmlString => self::timeline($record)),
        ]);
    }

    /** @return Builder<Reconciliation> */
    public static function getEloquentQuery(): Builder
    {
        return app(ReconciliationService::class)->visibleFor(self::actor());
    }

    /** @return array<int|string, string> */
    private static function areaOptions(): array
    {
        $query = ServiceArea::query()->where('is_active', true);
        $actor = self::actor();
        if (! app(PermissionChecker::class)->allows($actor, 'user.view.all')) {
            $areaId = $actor->staffProfile?->service_area_id;
            $query->when($areaId !== null, fn (Builder $areas): Builder => $areas->whereKey($areaId));
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    private static function timeline(Reconciliation $record): HtmlString
    {
        $events = AuditLog::query()->where('auditable_type', Reconciliation::class)->where('auditable_id', $record->id)->orderBy('occurred_at')->orderBy('id')->get();
        $items = $events->map(static fn (AuditLog $event): string => '<li><strong>'.e($event->action).'</strong><br><span>'.e($event->occurred_at->setTimezone('Asia/Jakarta')->format('d M Y H:i:s')).'</span></li>')->implode('');

        return new HtmlString($items === '' ? '<p>Tidak ada aktivitas audit.</p>' : '<ol class="space-y-3">'.$items.'</ol>');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageReconciliations::route('/')];
    }
}
