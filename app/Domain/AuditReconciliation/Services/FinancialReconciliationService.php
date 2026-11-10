<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Models\Reconciliation;
use App\Domain\AuditReconciliation\Models\ReconciliationItem;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class FinancialReconciliationService
{
    public function __construct(private PermissionChecker $permissions, private AuditLogger $auditLogger) {}

    public function create(User $actor, CarbonImmutable $businessDate, ?int $cashTotal, ?string $notes = null): Reconciliation
    {
        $this->authorize($actor, 'reconciliation.create');
        if ($businessDate->isFuture()) {
            throw ValidationException::withMessages(['business_date' => 'Tanggal rekonsiliasi tidak boleh di masa depan.']);
        }
        if ($cashTotal !== null && $cashTotal < 0) {
            throw ValidationException::withMessages(['cash_total' => 'Hitungan kas tidak boleh negatif.']);
        }
        $notes = $notes === null ? null : trim($notes);
        if ($notes !== null && Str::length($notes) > 2000) {
            throw ValidationException::withMessages(['notes' => 'Catatan rekonsiliasi maksimal 2000 karakter.']);
        }

        return DB::transaction(function () use ($actor, $businessDate, $cashTotal, $notes): Reconciliation {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $this->authorize($actor, 'reconciliation.create');
            // This prevents an entry or hold from crossing the snapshot while totals are read.
            LedgerAccount::query()->lockForUpdate()->get(['id']);
            $scope = 'global';
            $previous = Reconciliation::query()->whereDate('business_date', $businessDate->toDateString())->where('scope_key', $scope)->latest('version')->lockForUpdate()->firstOrNew();
            $snapshot = $this->snapshot($businessDate, $cashTotal);
            $reconciliation = Reconciliation::query()->create([
                'uuid' => (string) Str::uuid(), 'business_date' => $businessDate->toDateString(), 'scope_key' => $scope,
                'status' => Reconciliation::STATUS_DRAFT, 'version' => $previous->exists ? $previous->version + 1 : 1, 'parent_id' => $previous->exists ? $previous->id : null,
                ...$snapshot['totals'], 'notes' => $notes === '' ? null : $notes, 'created_by' => $actor->id,
            ]);
            $reconciliation->items()->createMany($snapshot['items']);
            $this->refreshDifference($reconciliation);
            $this->auditLogger->record($actor, 'reconciliation.created', $reconciliation, [], ['business_date' => $businessDate->toDateString(), 'version' => $reconciliation->version], $this->correlationId());

            return $reconciliation->fresh('items');
        }, 5);
    }

    public function setCashTotal(User $actor, Reconciliation $reconciliation, int $cashTotal): Reconciliation
    {
        $this->authorize($actor, 'reconciliation.create');
        if ($cashTotal < 0) {
            throw ValidationException::withMessages(['cash_total' => 'Hitungan kas tidak boleh negatif.']);
        }

        return DB::transaction(function () use ($actor, $reconciliation, $cashTotal): Reconciliation {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $this->authorize($actor, 'reconciliation.create');
            $locked = Reconciliation::query()->with('items')->whereKey($reconciliation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== Reconciliation::STATUS_DRAFT || $locked->created_by !== $actor->id) {
                throw new AuthorizationException('Hanya pembuat yang dapat melengkapi draf rekonsiliasi.');
            }
            $cash = $locked->items->firstWhere('item_type', 'cash_disbursement');
            if (! $cash instanceof ReconciliationItem) {
                throw new \LogicException('Item hitungan kas tidak ditemukan.');
            }
            $cash->forceFill(['actual_total' => $cashTotal, 'difference' => $cashTotal - $cash->expected_total, 'status' => $cashTotal === $cash->expected_total ? ReconciliationItem::STATUS_VERIFIED : ReconciliationItem::STATUS_DIFFERENCE])->save();
            $locked->forceFill(['cash_total' => $cashTotal])->save();
            $this->refreshDifference($locked);
            $this->auditLogger->record($actor, 'reconciliation.cash_counted', $locked, [], ['cash_total' => $cashTotal], $this->correlationId());

            return $locked->fresh('items');
        });
    }

    public function complete(User $actor, Reconciliation $reconciliation, string $reason = ''): Reconciliation
    {
        return DB::transaction(function () use ($actor, $reconciliation, $reason): Reconciliation {
            $currentActor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $this->authorize($currentActor, 'reconciliation.complete');
            $locked = Reconciliation::query()->with('items')->whereKey($reconciliation->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [Reconciliation::STATUS_COMPLETED, Reconciliation::STATUS_COMPLETED_WITH_DIFFERENCE], true) && $locked->approver_id === $currentActor->id) {
                return $locked;
            }
            if (! in_array($locked->status, [Reconciliation::STATUS_DRAFT, Reconciliation::STATUS_SUBMITTED], true)) {
                throw ValidationException::withMessages(['reconciliation' => 'Catatan pemeriksaan ini sudah ditutup. Buat versi baru bila perlu memeriksa ulang.']);
            }
            if ($locked->cash_total === null || $locked->items->contains(fn (ReconciliationItem $item): bool => $item->status === ReconciliationItem::STATUS_OPEN)) {
                throw ValidationException::withMessages(['reconciliation' => 'Isi hitungan kas pencairan sebelum mencatat hasil pemeriksaan.']);
            }
            $hasDifference = $locked->items->contains(fn (ReconciliationItem $item): bool => $item->difference !== 0);
            $reason = trim($reason);
            if (mb_strlen($reason) > 1000 || ($hasDifference && mb_strlen($reason) < 10)) {
                throw ValidationException::withMessages(['decisionReason' => 'Jika ada selisih, jelaskan hasil pemeriksaan minimal 10 karakter. Catatan maksimal 1000 karakter.']);
            }
            $oldStatus = $locked->status;
            $status = $hasDifference ? Reconciliation::STATUS_COMPLETED_WITH_DIFFERENCE : Reconciliation::STATUS_COMPLETED;
            // Reuse historical checker columns; this is a recorded check, not an approval chain.
            $locked->forceFill(['status' => $status, 'approver_id' => $currentActor->id, 'approved_at' => now(), 'notes' => $reason === '' ? $locked->notes : $this->appendNote($locked->notes, 'Hasil pemeriksaan: '.$reason)])->save();
            $this->auditLogger->record($currentActor, 'reconciliation.completed', $locked, ['status' => $oldStatus], ['status' => $status, 'reason' => $reason, 'has_difference' => $hasDifference], $this->correlationId());

            return $locked->fresh('items');
        });
    }

    /** @return array{totals: array{opening_total: int, deposit_total: int, withdrawal_total: int, grocery_total: int, hold_total: int, cash_total: ?int, closing_total: int, difference: int}, items: list<array<string, int|string|null>>} */
    private function snapshot(CarbonImmutable $businessDate, ?int $cashTotal): array
    {
        $start = $businessDate->setTimezone('Asia/Jakarta')->startOfDay();
        $end = $start->addDay();
        $opening = $this->availableAt($start);
        $closing = $this->availableAt($end);
        $depositExpected = (int) Deposit::query()->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED, Deposit::STATUS_REVERSED])->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)->sum('total_value');
        $depositActual = $this->ledgerTotal(Deposit::class, $start, $end);
        $withdrawalExpected = (int) WithdrawalRequest::query()->whereNotNull('paid_at')->where('paid_at', '>=', $start)->where('paid_at', '<', $end)->sum('amount');
        $withdrawalActual = $this->ledgerTotal(WithdrawalRequest::class, $start, $end);
        $groceryExpected = (int) GroceryRedemption::query()->whereNotNull('handed_over_at')->where('handed_over_at', '>=', $start)->where('handed_over_at', '<', $end)->sum('value_snapshot');
        $groceryActual = $this->ledgerTotal(GroceryRedemption::class, $start, $end);
        $holdTotal = $this->holdAt($end);
        $expectedClosing = $opening + $this->netLedgerTotal($start, $end) - $this->holdDelta($start, $end);
        $cashStatus = $cashTotal === null ? ReconciliationItem::STATUS_OPEN : ($cashTotal === $withdrawalExpected ? ReconciliationItem::STATUS_VERIFIED : ReconciliationItem::STATUS_DIFFERENCE);
        $digitalItems = [];
        $payouts = SandboxPayout::query();
        if ((clone $payouts)->where(fn ($q) => $q->whereBetween('settled_at', [$start, $end->subMicrosecond()])->orWhereBetween('reversed_at', [$start, $end->subMicrosecond()]))->exists()) {
            $settled = (int) (clone $payouts)->where('settled_at', '>=', $start)->where('settled_at', '<', $end)->sum('amount');
            $returned = (int) (clone $payouts)->where('reversed_at', '>=', $start)->where('reversed_at', '<', $end)->sum('amount');
            $entries = LedgerEntry::query()->where('source_type', SandboxPayout::class)->where('effective_at', '>=', $start)->where('effective_at', '<', $end);
            $net = (int) (clone $entries)->where('direction', LedgerEntry::DIRECTION_OUT)->sum('amount') - (int) (clone $entries)->where('direction', LedgerEntry::DIRECTION_IN)->sum('amount');
            $digitalItems[] = $this->item('payout_ledger', $settled - $returned, $net, 'Mutasi pencairan digital simulasi dibandingkan penyelesaian dan pengembalian. Bukan kas fisik.');
        }

        return [
            'totals' => ['opening_total' => max(0, $opening), 'deposit_total' => $depositActual, 'withdrawal_total' => $withdrawalActual, 'grocery_total' => $groceryActual, 'hold_total' => $holdTotal, 'cash_total' => $cashTotal, 'closing_total' => max(0, $closing), 'difference' => $closing - $expectedClosing],
            'items' => [
                $this->item('deposit_ledger', $depositExpected, $depositActual, 'Mutasi masuk setoran dibandingkan snapshot setoran.'),
                $this->item('withdrawal_ledger', $withdrawalExpected, $withdrawalActual, 'Mutasi keluar pencairan dibandingkan transaksi dibayar.'),
                $this->item('grocery_ledger', $groceryExpected, $groceryActual, 'Mutasi keluar sembako dibandingkan serah-terima.'),
                $this->item('cash_disbursement', $withdrawalExpected, $cashTotal ?? 0, $cashTotal === null ? 'Hitungan kas belum diisi.' : 'Hitungan kas fisik dibandingkan pencairan dibayar.', $cashStatus),
                $this->item('available_balance', max(0, $expectedClosing), max(0, $closing), 'Saldo tersedia setelah mutasi dan perubahan dana ditahan.'),
                ...$digitalItems,
            ],
        ];
    }

    /** @return array<string, int|string|null> */
    private function item(string $type, int $expected, int $actual, string $note, ?string $status = null): array
    {
        $difference = $actual - $expected;

        return ['item_type' => $type, 'expected_total' => $expected, 'actual_total' => $actual, 'difference' => $difference, 'status' => $status ?? ($difference === 0 ? ReconciliationItem::STATUS_VERIFIED : ReconciliationItem::STATUS_DIFFERENCE), 'note' => $note];
    }

    private function ledgerTotal(string $sourceType, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) LedgerEntry::query()->where('source_type', $sourceType)->where('effective_at', '>=', $start)->where('effective_at', '<', $end)->sum('amount');
    }

    private function availableAt(CarbonImmutable $at): int
    {
        $entries = LedgerEntry::query()->where('effective_at', '<', $at)->selectRaw('COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE -amount END), 0) AS total', [LedgerEntry::DIRECTION_IN])->value('total');

        return (int) $entries - $this->holdAt($at);
    }

    private function netLedgerTotal(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) LedgerEntry::query()->where('effective_at', '>=', $start)->where('effective_at', '<', $end)->selectRaw('COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE -amount END), 0) AS total', [LedgerEntry::DIRECTION_IN])->value('total');
    }

    private function holdAt(CarbonImmutable $at): int
    {
        return (int) BalanceHold::query()->where('held_at', '<', $at)->where(static function ($query) use ($at): void {
            $query->whereNull('released_at')->orWhere('released_at', '>=', $at);
        })->where(static function ($query) use ($at): void {
            $query->whereNull('converted_at')->orWhere('converted_at', '>=', $at);
        })->sum('amount');
    }

    private function holdDelta(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return $this->holdAt($end) - $this->holdAt($start);
    }

    private function refreshDifference(Reconciliation $reconciliation): void
    {
        $difference = (int) $reconciliation->items()->sum('difference');
        $reconciliation->forceFill(['difference' => $difference])->save();
    }

    private function appendNote(?string $existing, string $line): string
    {
        return trim(($existing === null ? '' : $existing."\n").$line);
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Permission rekonsiliasi tidak tersedia.');
        }
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
