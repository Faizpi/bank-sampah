<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditReconciliation\Models\Reconciliations\Pages;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\ReconciliationService;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Filament\Resources\AuditReconciliation\Models\Reconciliations\ReconciliationResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

final class ManageReconciliations extends ListRecords
{
    protected static string $resource = ReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Buat rekonsiliasi')
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => app(PermissionChecker::class)->allows(auth()->user(), 'reconciliation.create'))
                ->schema([
                    DatePicker::make('business_date')->label('Tanggal pelayanan')->required(),
                    Select::make('service_area_id')->label('Area pelayanan')->options(fn (): array => $this->areaOptions())->nullable()->searchable(),
                    TextInput::make('cash_total')->label('Kas fisik penutupan (Rp)')->numeric()->integer()->minValue(0),
                    Textarea::make('notes')->label('Catatan awal')->maxLength(2000)->rows(3),
                ])
                ->action(function (array $data): void {
                    /** @var User $actor */
                    $actor = auth()->user();
                    app(ReconciliationService::class)->create($actor, (string) $data['business_date'], isset($data['service_area_id']) ? (int) $data['service_area_id'] : null, (string) ($data['notes'] ?? ''), isset($data['cash_total']) ? (int) $data['cash_total'] : null);
                })
                ->successNotificationTitle('Rekonsiliasi dibuat.'),
        ];
    }

    /** @return array<int|string, string> */
    private function areaOptions(): array
    {
        $query = ServiceArea::query()->where('is_active', true);
        /** @var User $actor */
        $actor = auth()->user();
        if (! app(PermissionChecker::class)->allows($actor, 'user.view.all')) {
            $query->when($actor->staffProfile?->service_area_id !== null, fn ($areas) => $areas->whereKey($actor->staffProfile?->service_area_id));
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }
}
