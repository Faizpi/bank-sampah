<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Enums\ReconciliationItemStatus;
use App\Domain\AuditReconciliation\Enums\ReconciliationStatus;
use App\Domain\AuditReconciliation\Models\Reconciliation;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Reports\Services\ReportQueryService;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ReconciliationService
{
    public function __construct(private PermissionChecker $permissions, private AuditLogger $auditLogger, private ReportQueryService $reports) {}

    /** @return Builder<Reconciliation> */
    public function visibleFor(User $actor): Builder
    {
        $this->authorize($actor, 'reconciliation.view');
        $query = Reconciliation::query();
        if ($this->permissions->allows($actor, 'user.view.all')) {
            return $query;
        }
        $areaId = $actor->staffProfile?->service_area_id;
        if ($areaId !== null) {
            return $query->where('service_area_id', $areaId)->orWhere('created_by', $actor->id);
        }

        return $query->where('created_by', $actor->id);
    }

    public function create(User $actor, string $businessDate, ?int $serviceAreaId, string $notes = ''): Reconciliation
    {
        $this->authorize($actor, 'reconciliation.create');
        $date = $this->date($businessDate);
        if ($serviceAreaId !== null && ! $this->inScope($actor, $serviceAreaId)) {
            throw new AuthorizationException('Wilayah rekonsiliasi berada di luar scope Anda.');
        }

        return DB::transaction(function () use ($actor, $date, $serviceAreaId, $notes): Reconciliation {
            $scopeKey = $serviceAreaId === null ? 'all' : 'area-'.$serviceAreaId;
            $version = ((int) Reconciliation::query()->whereDate('business_date', $date)->where('scope_key', $scopeKey)->max('version')) + 1;
            $totals = $this->totals($actor, $date, $serviceAreaId);
            $record = new Reconciliation;
            $record->forceFill(['uuid' => (string) Str::uuid(), 'business_date' => $date, 'service_area_id' => $serviceAreaId, 'scope_key' => $scopeKey, 'status' => ReconciliationStatus::Draft, 'version' => $version, 'opening_total' => 0, 'deposit_total' => $totals['deposit_total'], 'withdrawal_total' => $totals['withdrawal_total'], 'grocery_total' => $totals['grocery_total'], 'hold_total' => $totals['hold_total'], 'closing_total' => $totals['closing_total'], 'difference' => $totals['difference'], 'notes' => trim($notes) !== '' ? trim($notes) : null, 'created_by' => $actor->id])->save();
            if ($record->difference !== 0) {
                $record->items()->create(['item_type' => 'cash_difference', 'expected_total' => $record->closing_total, 'actual_total' => $record->closing_total + $record->difference, 'difference' => $record->difference, 'status' => ReconciliationItemStatus::Open]);
            }
            $this->auditLogger->record($actor, 'reconciliation.created', $record, [], ['version' => $version, 'difference' => $record->difference], $this->correlationId());

            return $record->fresh('items');
        });
    }

    public function submit(User $actor, Reconciliation $record): Reconciliation
    {
        $this->authorize($actor, 'reconciliation.create');

        return $this->transition($actor, $record, ReconciliationStatus::Submitted);
    }

    public function approve(User $actor, Reconciliation $record, string $notes = ''): Reconciliation
    {
        $this->authorize($actor, 'reconciliation.approve');
        if ($record->created_by === $actor->id) {
            throw new AuthorizationException('Pembuat rekonsiliasi tidak dapat mengesahkan record sendiri.');
        }
        if ($record->hasOpenDiscrepancy()) {
            throw ValidationException::withMessages(['reconciliation' => 'Selisih terbuka harus ditelusuri sebelum rekonsiliasi disahkan.']);
        }

        return DB::transaction(function () use ($actor, $record, $notes): Reconciliation {
            $locked = Reconciliation::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ReconciliationStatus::Submitted || $locked->created_by === $actor->id || $locked->hasOpenDiscrepancy()) {
                throw ValidationException::withMessages(['reconciliation' => 'Rekonsiliasi tidak dapat disahkan.']);
            }
            $locked->forceFill(['status' => ReconciliationStatus::Approved, 'approver_id' => $actor->id, 'approved_at' => now(), 'notes' => trim($notes) !== '' ? trim($notes) : $locked->notes])->save();
            $this->auditLogger->record($actor, 'reconciliation.approved', $locked, ['status' => ReconciliationStatus::Submitted->value], ['status' => ReconciliationStatus::Approved->value], $this->correlationId());

            return $locked->fresh('items');
        });
    }

    public function reject(User $actor, Reconciliation $record, string $reason): Reconciliation
    {
        $this->authorize($actor, 'reconciliation.approve');
        if (mb_strlen(trim($reason)) < 10 || $record->created_by === $actor->id) {
            throw ValidationException::withMessages(['reason' => 'Alasan penolakan wajib dan pembuat tidak dapat menolak sendiri.']);
        }

        return DB::transaction(function () use ($actor, $record, $reason): Reconciliation {
            $locked = Reconciliation::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($locked, ReconciliationStatus::Rejected);
            $locked->forceFill(['status' => ReconciliationStatus::Rejected, 'rejector_id' => $actor->id, 'rejected_at' => now(), 'notes' => trim($reason)])->save();
            $this->auditLogger->record($actor, 'reconciliation.rejected', $locked, ['status' => ReconciliationStatus::Submitted->value], ['status' => ReconciliationStatus::Rejected->value], $this->correlationId());

            return $locked->fresh('items');
        });
    }

    /** @param array<string, mixed> $item */
    public function resolveDiscrepancy(User $actor, Reconciliation $record, array $item): Reconciliation
    {
        $this->authorize($actor, 'reconciliation.create');
        if (! $this->visibleFor($actor)->whereKey($record->id)->exists()) {
            throw new AuthorizationException('Rekonsiliasi berada di luar scope Anda.');
        }
        if ($record->status !== ReconciliationStatus::Draft || ! isset($item['note']) || mb_strlen(trim((string) $item['note'])) < 10) {
            throw ValidationException::withMessages(['item' => 'Selisih hanya dapat ditelusuri pada draf dengan catatan memadai.']);
        }
        DB::transaction(function () use ($actor, $record, $item): void {
            $record->items()->create(['item_type' => (string) ($item['item_type'] ?? 'cash_difference'), 'reference_type' => isset($item['reference_type']) ? (string) $item['reference_type'] : null, 'reference_id' => isset($item['reference_id']) ? (int) $item['reference_id'] : null, 'expected_total' => (int) ($item['expected_total'] ?? 0), 'actual_total' => (int) ($item['actual_total'] ?? 0), 'difference' => 0, 'status' => ReconciliationItemStatus::Resolved, 'note' => trim((string) $item['note'])]);
            $this->auditLogger->record($actor, 'reconciliation.discrepancy.resolved', $record, [], ['item_type' => $item['item_type'] ?? 'cash_difference', 'status' => ReconciliationItemStatus::Resolved->value], $this->correlationId());
        });

        return $record->fresh('items');
    }

    public function revise(User $actor, Reconciliation $record, string $notes = ''): Reconciliation
    {
        $this->authorize($actor, 'reconciliation.create');
        if (! $this->visibleFor($actor)->whereKey($record->id)->exists()) {
            throw new AuthorizationException('Rekonsiliasi berada di luar scope Anda.');
        }

        $revision = $this->create($actor, $record->business_date->toDateString(), $record->service_area_id, $notes ?: (string) $record->notes);
        $revision->forceFill(['parent_id' => $record->id])->save();

        return $revision->fresh('items');
    }

    private function transition(User $actor, Reconciliation $record, ReconciliationStatus $next): Reconciliation
    {
        return DB::transaction(function () use ($actor, $record, $next): Reconciliation {
            $locked = Reconciliation::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($locked, $next);
            $locked->forceFill(['status' => $next, 'submitted_at' => $next === ReconciliationStatus::Submitted ? now() : $locked->submitted_at])->save();
            $this->auditLogger->record($actor, 'reconciliation.status.changed', $locked, ['status' => $record->status->value], ['status' => $next->value], $this->correlationId());

            return $locked->fresh('items');
        });
    }

    private function assertTransition(Reconciliation $record, ReconciliationStatus $next): void
    {
        if (! $record->status->canTransitionTo($next)) {
            throw ValidationException::withMessages(['status' => 'Perubahan status rekonsiliasi tidak valid.']);
        }
    }

    /** @return array{deposit_total: int, withdrawal_total: int, grocery_total: int, hold_total: int, closing_total: int, difference: int} */
    private function totals(User $actor, CarbonImmutable $date, ?int $serviceAreaId): array
    {
        $filters = ['start' => $date->toDateString(), 'end' => $date->addDay()->toDateString()];
        if ($serviceAreaId !== null) {
            $filters['service_area_id'] = $serviceAreaId;
        }
        $depositTotal = $this->reports->aggregate($actor, $filters)['total_value'];
        $withdrawals = WithdrawalRequest::query()->whereDate('paid_at', $date)->whereNotNull('paid_at');
        $groceries = GroceryRedemption::query()->whereDate('handed_over_at', $date)->whereNotNull('handed_over_at');
        $holds = BalanceHold::query()->where('status', BalanceHold::STATUS_ACTIVE)->whereDate('held_at', '<=', $date);
        $ledger = LedgerEntry::query()->whereDate('effective_at', '<=', $date);
        if ($serviceAreaId !== null) {
            $customerIds = User::query()->whereHas('customerProfile.rt.serviceAreas', static fn (Builder $area): Builder => $area->whereKey($serviceAreaId))->select('users.id');
            $withdrawals->whereIn('customer_id', $customerIds);
            $groceries->whereIn('customer_id', $customerIds);
            $holds->whereHas('account', static fn (Builder $account): Builder => $account->whereIn('user_id', $customerIds));
            $ledger->whereHas('account', static fn (Builder $account): Builder => $account->whereIn('user_id', $customerIds));
        }
        $withdrawalTotal = (int) $withdrawals->sum('amount');
        $groceryTotal = (int) $groceries->sum('value_snapshot');
        $holdTotal = (int) $holds->sum('amount');
        $closingTotal = (int) (clone $ledger)->where('direction', LedgerEntry::DIRECTION_IN)->sum('amount') - (int) (clone $ledger)->where('direction', LedgerEntry::DIRECTION_OUT)->sum('amount');
        $difference = $depositTotal - $withdrawalTotal - $groceryTotal;

        return compact('depositTotal', 'withdrawalTotal', 'groceryTotal', 'holdTotal', 'closingTotal', 'difference') + ['deposit_total' => $depositTotal, 'withdrawal_total' => $withdrawalTotal, 'grocery_total' => $groceryTotal, 'hold_total' => $holdTotal, 'closing_total' => $closingTotal];
    }

    private function inScope(User $actor, int $serviceAreaId): bool
    {
        return $this->permissions->allows($actor, 'user.view.all') || $actor->staffProfile?->service_area_id === $serviceAreaId;
    }

    private function date(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw ValidationException::withMessages(['business_date' => 'Tanggal rekonsiliasi tidak valid.']);
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta');
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission) || $actor->status !== UserStatus::Active) {
            throw new AuthorizationException('Anda tidak memiliki akses terhadap rekonsiliasi.');
        }
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
