<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Models\Reconciliation as ReconciliationModel;
use App\Domain\AuditReconciliation\Services\FinancialReconciliationService;
use App\Models\User;
use App\Support\LocalInput;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class Reconciliation extends Page
{
    public string $businessDate = '';

    public string $cashTotal = '';

    public string $notes = '';

    /** @var array<int, string> */
    public array $cashCounts = [];

    /** @var array<int, string> */
    public array $decisionReasons = [];

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
        $this->cashTotal = LocalInput::rupiah($this->cashTotal);
        $this->validate([
            'businessDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'cashTotal' => ['nullable', 'integer', 'min:0', 'regex:/^\d+$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        /** @var User $actor */
        $actor = auth()->user();
        $cash = trim($this->cashTotal) === '' ? null : (int) $this->cashTotal;
        $service->create($actor, CarbonImmutable::parse($this->businessDate, 'Asia/Jakarta'), $cash, $this->notes);
        session()->flash('reconciliation-success', 'Pembanding siap. Periksa selisih dan catat hasilnya.');
    }

    public function saveCashCount(int $reconciliationId, FinancialReconciliationService $service): void
    {
        $field = 'cashCounts.'.$reconciliationId;
        LocalInput::field($this->cashCounts, $reconciliationId, 'rupiah');
        $this->validate([$field => ['required', 'string', 'integer', 'min:0', 'regex:/^\d+$/']]);
        /** @var User $actor */
        $actor = auth()->user();
        $service->setCashTotal($actor, ReconciliationModel::query()->findOrFail($reconciliationId), (int) $this->cashCounts[$reconciliationId]);
        session()->flash('reconciliation-success', 'Hitungan kas diperbarui.');
    }

    public function completeSnapshot(int $reconciliationId, FinancialReconciliationService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $field = 'decisionReasons.'.$reconciliationId;
        $this->validate([$field => ['nullable', 'string', 'max:1000']]);
        try {
            $service->complete($actor, ReconciliationModel::query()->findOrFail($reconciliationId), $this->decisionReasons[$reconciliationId] ?? '');
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $key => $errors) {
                $target = $key === 'reconciliation' ? 'cashCounts.'.$reconciliationId : $field;
                $messages[$target] = array_merge($messages[$target] ?? [], $errors);
            }
            throw ValidationException::withMessages($messages);
        }
        $this->decisionReasons[$reconciliationId] = '';
        session()->flash('reconciliation-success', 'Hasil pemeriksaan tercatat.');
    }

    /** @return array<string, bool|Collection<int, ReconciliationModel>> */
    protected function getViewData(): array
    {
        $actor = auth()->user();
        $permissions = app(PermissionChecker::class);
        $records = ReconciliationModel::query()->with(['items', 'creator', 'approver', 'rejector'])->latest('business_date')->latest('version')->limit(12)->get();
        foreach ($records as $record) {
            if (! array_key_exists($record->id, $this->cashCounts)) {
                $this->cashCounts[$record->id] = $record->cash_total === null ? '' : (string) $record->cash_total;
            }
            $this->decisionReasons[$record->id] ??= '';
        }

        return [
            'reconciliations' => $records,
            'canCreate' => $actor instanceof User && $permissions->allows($actor, 'reconciliation.create'),
            'canComplete' => $actor instanceof User && $permissions->allows($actor, 'reconciliation.complete'),
            'canReviewDeposits' => $actor instanceof User && $permissions->allows($actor, 'deposit.view'),
            'canViewLedger' => $actor instanceof User && $permissions->allows($actor, 'ledger.view'),
            'canViewHolds' => $actor instanceof User && $permissions->allows($actor, 'ledger.view'),
            'canViewAudit' => $actor instanceof User && $permissions->allows($actor, 'audit.view'),
        ];
    }
}
