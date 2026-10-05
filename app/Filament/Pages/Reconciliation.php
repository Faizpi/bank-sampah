<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Models\Reconciliation as ReconciliationModel;
use App\Domain\AuditReconciliation\Services\FinancialReconciliationService;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

final class Reconciliation extends Page
{
    public string $businessDate = '';

    public string $cashTotal = '';

    public string $notes = '';

    public string $decisionReason = '';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Rekonsiliasi';

    protected static ?string $title = 'Rekonsiliasi';

    protected string $view = 'filament.backoffice.reconciliation';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return false;
        }

        $permissions = app(PermissionChecker::class);

        return $permissions->allows($actor, 'reconciliation.view');
    }

    public function mount(): void
    {
        $this->businessDate = now('Asia/Jakarta')->toDateString();
    }

    public function createSnapshot(FinancialReconciliationService $service): void
    {
        $this->validate([
            'businessDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'cashTotal' => ['nullable', 'regex:/^\d+$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        /** @var User $actor */
        $actor = auth()->user();
        $cash = trim($this->cashTotal) === '' ? null : (int) $this->cashTotal;
        $service->create($actor, CarbonImmutable::parse($this->businessDate, 'Asia/Jakarta'), $cash, $this->notes);
        session()->flash('reconciliation-success', 'Catatan kondisi rekonsiliasi berhasil dibuat. Lengkapi hitungan kas dan pastikan semua item sesuai sebelum diajukan.');
    }

    public function saveCashCount(int $reconciliationId, FinancialReconciliationService $service): void
    {
        $this->validate(['cashTotal' => ['required', 'regex:/^\d+$/']]);
        /** @var User $actor */
        $actor = auth()->user();
        $service->setCashTotal($actor, ReconciliationModel::query()->findOrFail($reconciliationId), (int) $this->cashTotal);
        session()->flash('reconciliation-success', 'Hitungan kas diperbarui.');
    }

    public function submitSnapshot(int $reconciliationId, FinancialReconciliationService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $service->submit($actor, ReconciliationModel::query()->findOrFail($reconciliationId));
        session()->flash('reconciliation-success', 'Rekonsiliasi diajukan untuk pemeriksaan pengguna lain.');
    }

    public function approveSnapshot(int $reconciliationId, FinancialReconciliationService $service): void
    {
        $this->validate(['decisionReason' => ['required', 'string', 'min:10', 'max:1000']]);
        /** @var User $actor */
        $actor = auth()->user();
        $service->approve($actor, ReconciliationModel::query()->findOrFail($reconciliationId), $this->decisionReason);
        $this->decisionReason = '';
        session()->flash('reconciliation-success', 'Rekonsiliasi disetujui.');
    }

    public function rejectSnapshot(int $reconciliationId, FinancialReconciliationService $service): void
    {
        $this->validate(['decisionReason' => ['required', 'string', 'min:10', 'max:1000']]);
        /** @var User $actor */
        $actor = auth()->user();
        $service->reject($actor, ReconciliationModel::query()->findOrFail($reconciliationId), $this->decisionReason);
        $this->decisionReason = '';
        session()->flash('reconciliation-success', 'Rekonsiliasi ditolak dan perlu dibuat ulang.');
    }

    /** @return array<string, bool|Collection<int, ReconciliationModel>> */
    protected function getViewData(): array
    {
        $actor = auth()->user();
        $permissions = app(PermissionChecker::class);

        return [
            'reconciliations' => ReconciliationModel::query()->with(['items', 'creator', 'approver', 'rejector'])->latest('business_date')->latest('version')->limit(12)->get(),
            'canCreate' => $actor instanceof User && $permissions->allows($actor, 'reconciliation.create'),
            'canApprove' => $actor instanceof User && $permissions->allows($actor, 'reconciliation.approve'),
            'canReviewDeposits' => $actor instanceof User && $permissions->allows($actor, 'deposit.view'),
            'canViewLedger' => $actor instanceof User && $permissions->allows($actor, 'ledger.view'),
            'canViewHolds' => $actor instanceof User && $permissions->allows($actor, 'ledger.view'),
            'canViewAudit' => $actor instanceof User && $permissions->allows($actor, 'audit.view'),
        ];
    }
}
