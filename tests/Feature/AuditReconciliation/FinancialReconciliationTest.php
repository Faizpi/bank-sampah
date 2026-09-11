<?php

declare(strict_types=1);

namespace Tests\Feature\AuditReconciliation;

use App\Domain\AuditReconciliation\Models\Reconciliation;
use App\Domain\AuditReconciliation\Services\FinancialReconciliationService;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FinancialReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_snapshot_must_match_then_be_approved_by_a_different_user(): void
    {
        $creator = $this->userWith('reconciliation.create');
        $approver = $this->userWith('reconciliation.approve');
        $customer = User::factory()->create();
        $account = LedgerAccount::query()->create(['user_id' => $customer->id, 'status' => 'aktif', 'currency' => 'IDR']);
        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-RECONCILIATION-1', 'customer_id' => $customer->id, 'staff_id' => $creator->id,
            'method' => 'langsung', 'occurred_at' => '2026-08-01 10:00:00', 'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.000', 'total_value' => 10_000, 'finalized_at' => '2026-08-01 10:00:00',
        ]);
        LedgerEntry::query()->create([
            'entry_number' => 'LED-RECONCILIATION-1', 'ledger_account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => LedgerEntry::KIND_DEPOSIT, 'amount' => 10_000, 'source_type' => Deposit::class, 'source_id' => $deposit->id,
            'source_key' => 'reconciliation-deposit-1', 'effective_at' => '2026-08-01 10:00:00', 'balance_after' => 10_000,
        ]);

        $service = app(FinancialReconciliationService::class);
        $snapshot = $service->create($creator, CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'), 0, 'Penutupan kas harian.');
        self::assertSame(Reconciliation::STATUS_DRAFT, $snapshot->status);
        self::assertCount(5, $snapshot->items);
        self::assertTrue($snapshot->items->every(static fn ($item): bool => $item->status === 'sesuai'));

        $submitted = $service->submit($creator, $snapshot);
        self::assertSame(Reconciliation::STATUS_SUBMITTED, $submitted->status);
        $approved = $service->approve($approver, $submitted, 'Seluruh pembanding dan kas fisik sudah sesuai.');
        self::assertSame(Reconciliation::STATUS_APPROVED, $approved->status);
        self::assertSame($approver->id, $approved->approver_id);
    }

    private function userWith(string $permissionName): User
    {
        $user = User::factory()->create();
        $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
        $role = Role::query()->create(['name' => 'reconciliation-'.str()->uuid(), 'description' => 'Reconciliation test']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
