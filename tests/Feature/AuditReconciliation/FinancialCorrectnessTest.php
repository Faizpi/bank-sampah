<?php

declare(strict_types=1);

namespace Tests\Feature\AuditReconciliation;

use App\Domain\AuditReconciliation\Services\FinancialReconciliationService;
use App\Domain\Corrections\Models\TransactionReversal;
use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Reports\Services\ReportQueryService;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class FinancialCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_grocery_package_on_final_active_day_is_visible_and_requestable(): void
    {
        CarbonImmutable::setTestNow('2026-09-12 15:30:00');

        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.package.view', 'grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);

        $package->forceFill([
            'active_from' => '2026-09-01',
            'active_until' => '2026-09-12',
            'status' => 'aktif',
        ])->save();

        $groceryService = app(GroceryService::class);

        // Catalog shows the package on its final active day
        self::assertTrue($groceryService->activePackages($customer)->whereKey($package->id)->exists());

        // Package is available on this date with afternoon time-of-day
        self::assertTrue($package->isAvailableOn(now('Asia/Jakarta')->toImmutable()));

        // Customer can submit request on the final active day
        $redemption = $groceryService->request($customer, ['package_id' => $package->id], 'test-final-day-idempotency-key-1');
        self::assertSame(GroceryStatus::PendingVerification, $redemption->status);
        self::assertSame($package->id, $redemption->grocery_package_id);

        // An expired package (yesterday was last day) is not available
        $package->forceFill(['active_until' => '2026-09-11'])->save();
        self::assertFalse($package->isAvailableOn(now('Asia/Jakarta')->toImmutable()));

        $this->expectException(ValidationException::class);
        $groceryService->request($customer, ['package_id' => $package->id], 'test-final-day-idempotency-key-2');
    }

    public function test_reconciliation_difference_reflects_closing_balance_discrepancy_and_does_not_sum_unlike_items(): void
    {
        $creator = $this->userWith('reconciliation.create');
        $customer = User::factory()->create();
        $account = LedgerAccount::query()->create(['user_id' => $customer->id, 'status' => 'aktif', 'currency' => 'IDR']);

        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-DIFF-TEST-1', 'customer_id' => $customer->id, 'staff_id' => $creator->id,
            'method' => 'langsung', 'occurred_at' => '2026-08-01 10:00:00', 'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.000', 'total_value' => 50_000, 'finalized_at' => '2026-08-01 10:00:00',
        ]);
        LedgerEntry::query()->create([
            'entry_number' => 'LED-DIFF-TEST-1', 'ledger_account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => LedgerEntry::KIND_DEPOSIT, 'amount' => 50_000, 'source_type' => Deposit::class, 'source_id' => $deposit->id,
            'source_key' => 'reconciliation-diff-1', 'effective_at' => '2026-08-01 10:00:00', 'balance_after' => 50_000,
        ]);

        // Add a withdrawal of 20,000 paid out
        WithdrawalRequest::query()->create([
            'request_number' => 'WDR-DIFF-TEST-1', 'customer_id' => $customer->id, 'requested_by_id' => $customer->id,
            'amount' => 20_000, 'status' => WithdrawalStatus::Paid, 'paid_at' => '2026-08-01 11:00:00',
        ]);
        LedgerEntry::query()->create([
            'entry_number' => 'LED-DIFF-TEST-2', 'ledger_account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_OUT,
            'kind' => LedgerEntry::KIND_ADJUSTMENT, 'amount' => 20_000, 'source_type' => WithdrawalRequest::class, 'source_id' => 1,
            'source_key' => 'reconciliation-diff-2', 'effective_at' => '2026-08-01 11:00:00', 'balance_after' => 30_000,
        ]);

        $service = app(FinancialReconciliationService::class);

        // Snapshot created with cashTotal = null (cash physical not counted yet)
        $snapshot = $service->create($creator, CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'), null, 'Draf penutupan.');

        // Closing balance in ledger is 30,000, expected closing is 30,000. Discrepancy is 0.
        // If items were summed, difference would be -20,000 because cash_disbursement actual is 0 vs expected 20,000!
        self::assertSame(0, $snapshot->difference);

        $cashItem = $snapshot->items->firstWhere('item_type', 'cash_disbursement');
        self::assertNotNull($cashItem);
        self::assertSame(-20_000, $cashItem->difference); // Cash item retains its own per-item discrepancy

        // Now set physical cash count with an intentional discrepancy (counted 15,000 vs expected 20,000)
        $updated = $service->setCashTotal($creator, $snapshot, 15_000);

        // Reconciliation difference must still reflect the ledger closing balance discrepancy (0),
        // not being overwritten by the cash drawer shortfall (-5,000)
        self::assertSame(0, $updated->difference);
        self::assertSame(-5_000, $updated->items->firstWhere('item_type', 'cash_disbursement')?->difference);
    }

    public function test_reconciliation_deposit_handling_surfaces_reversals_explicitly_and_distinguishes_gross_from_active_deposits(): void
    {
        $creator = $this->userWith('reconciliation.create', 'report.view', 'user.view.all');
        $customer = User::factory()->create();
        $account = LedgerAccount::query()->create(['user_id' => $customer->id, 'status' => 'aktif', 'currency' => 'IDR']);

        // Deposit 1: Active (15,000)
        $deposit1 = Deposit::query()->create([
            'deposit_number' => 'DEP-SURFACE-1', 'customer_id' => $customer->id, 'staff_id' => $creator->id,
            'method' => 'langsung', 'occurred_at' => '2026-08-01 09:00:00', 'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.500', 'total_value' => 15_000, 'finalized_at' => '2026-08-01 09:00:00',
        ]);
        LedgerEntry::query()->create([
            'entry_number' => 'LED-SURFACE-1', 'ledger_account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => LedgerEntry::KIND_DEPOSIT, 'amount' => 15_000, 'source_type' => Deposit::class, 'source_id' => $deposit1->id,
            'source_key' => 'surface-dep-1', 'effective_at' => '2026-08-01 09:00:00', 'balance_after' => 15_000,
        ]);

        // Deposit 2: Reversed (25,000)
        $deposit2 = Deposit::query()->create([
            'deposit_number' => 'DEP-SURFACE-2', 'customer_id' => $customer->id, 'staff_id' => $creator->id,
            'method' => 'langsung', 'occurred_at' => '2026-08-01 10:00:00', 'status' => Deposit::STATUS_REVERSED,
            'total_weight_kg' => '2.500', 'total_value' => 25_000, 'finalized_at' => '2026-08-01 10:00:00',
        ]);
        $entry2 = LedgerEntry::query()->create([
            'entry_number' => 'LED-SURFACE-2', 'ledger_account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => LedgerEntry::KIND_DEPOSIT, 'amount' => 25_000, 'source_type' => Deposit::class, 'source_id' => $deposit2->id,
            'source_key' => 'surface-dep-2', 'effective_at' => '2026-08-01 10:00:00', 'balance_after' => 40_000,
        ]);
        $reversal = TransactionReversal::query()->create([
            'reversal_number' => 'REV-SURFACE-2', 'original_deposit_id' => $deposit2->id, 'original_entry_id' => $entry2->id,
            'reason' => 'Kesalahan timbangan resmi.', 'created_by' => $creator->id, 'finalized_at' => '2026-08-01 11:00:00',
        ]);
        LedgerEntry::query()->create([
            'entry_number' => 'LED-SURFACE-REV-2', 'ledger_account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_OUT,
            'kind' => LedgerEntry::KIND_REVERSAL, 'amount' => 25_000, 'source_type' => TransactionReversal::class, 'source_id' => $reversal->id,
            'source_key' => 'surface-rev-2', 'effective_at' => '2026-08-01 11:00:00', 'balance_after' => 15_000,
        ]);

        // ReportQueryService excludes the reversed deposit
        $reports = app(ReportQueryService::class);
        $period = ['start' => '2026-08-01', 'end' => '2026-08-02'];
        $reportMetrics = $reports->aggregate($creator, $period, 'deposits');
        self::assertSame(15_000, $reportMetrics['total_value']);
        self::assertSame(1, $reportMetrics['deposit_count']);

        // Financial reconciliation captures gross deposits and surfaces reversal explicitly
        $service = app(FinancialReconciliationService::class);
        $snapshot = $service->create($creator, CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'), 0, 'Uji transparansi setoran.');

        $depositItem = $snapshot->items->firstWhere('item_type', 'deposit_ledger');
        self::assertNotNull($depositItem);
        self::assertSame(40_000, $depositItem->expected_total); // Gross expected
        self::assertSame(40_000, $depositItem->actual_total);   // Gross ledger
        self::assertSame(0, $depositItem->difference);

        // Explicit note characterization prevents conflating gross with active deposits
        self::assertStringContainsString('bruto', (string) $depositItem->note);
        self::assertStringContainsString('Setoran aktif (laporan): Rp 15.000', (string) $depositItem->note);
        self::assertStringContainsString('dibalik: Rp 25.000', (string) $depositItem->note);
        self::assertStringContainsString('pembalikan ledger: Rp 25.000', (string) $depositItem->note);
    }

    public function test_idempotent_double_submit_recovers_from_concurrent_collision_without_500(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);

        $idempotencyKey = 'test-concurrent-race-key-0001';
        $payload = ['package_id' => $package->id];
        $service = app(GroceryService::class);

        // First request succeeds
        $first = $service->request($customer, $payload, $idempotencyKey);
        self::assertInstanceOf(GroceryRedemption::class, $first);

        // Simulate concurrent duplicate collision where IdempotencyKey::acquireOrCreate encounters unique constraint
        $claim = IdempotencyKey::acquireOrCreate($customer->id, 'grocery.request', $idempotencyKey, hash('sha256', json_encode([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'rt_id' => $customer->customerProfile?->rt_id,
            'service_area_id' => $first->service_area_id,
        ], JSON_THROW_ON_ERROR)));

        self::assertFalse($claim['is_new']);
        self::assertSame($first->id, $claim['key']->result_id);

        // Second request with same key returns identical replay without 500 error
        $second = $service->request($customer, $payload, $idempotencyKey);
        self::assertSame($first->id, $second->id);
        self::assertSame($first->request_number, $second->request_number);

        // A second request with a conflicting payload is rejected with ValidationException (422), not 500
        $otherPackage = GroceryPackage::query()->create([
            'code' => 'PKG-DIFF-CONFLICT', 'name' => 'Paket Beda', 'contents' => 'Minyak', 'value' => 20_000, 'status' => 'aktif',
        ]);

        try {
            $service->request($customer, ['package_id' => $otherPackage->id], $idempotencyKey);
            self::fail('Conflicting payload must throw ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->validator->errors()->has('idempotency_key'));
        }
    }

    public function test_idempotency_key_acquire_or_create_recovers_on_unique_constraint_violation_exception(): void
    {
        $user = User::factory()->create();
        $scope = 'test.scope';
        $key = 'test-race-collision-key-999';
        $hash = hash('sha256', 'payload-1');

        // First acquire creates the key
        $claim1 = IdempotencyKey::acquireOrCreate($user->id, $scope, $key, $hash);
        self::assertTrue($claim1['is_new']);
        self::assertSame('processing', $claim1['key']->status);

        // Update with result
        $claim1['key']->forceFill(['status' => 'succeeded', 'result_id' => 999])->save();

        // Second acquire recovers from collision and returns existing
        $claim2 = IdempotencyKey::acquireOrCreate($user->id, $scope, $key, $hash);
        self::assertFalse($claim2['is_new']);
        self::assertSame(999, $claim2['key']->result_id);
    }

    /** @return array{User, GroceryPackage} */
    private function customerAndPackage(): array
    {
        $customer = User::factory()->create(['status' => UserStatus::Active]);
        $manager = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($manager, ['region.manage']);
        $regions = app(ManageRegions::class);
        $dusun = $regions->createDusun($manager, 'DS-FC-'.$customer->id, 'Dusun FC');
        $rw = $regions->createRw($manager, $dusun, 'RW-FC-'.$customer->id, 'RW FC');
        $rt = $regions->createRt($manager, $rw, 'RT-FC-'.$customer->id, 'RT FC');
        $customer->customerProfile()->create([
            'customer_number' => 'CST-FC-'.str_pad((string) $customer->id, 8, '0', STR_PAD_LEFT),
            'rt_id' => $rt->id,
            'address' => 'Alamat nasabah',
        ]);
        $regions->createServiceArea($manager, 'Area FC '.$customer->id, [$rt]);
        $package = GroceryPackage::query()->create([
            'code' => 'PKG-FC-'.$customer->id,
            'name' => 'Paket Financial Correctness',
            'contents' => 'Beras dan sembako lengkap',
            'value' => 50_000,
            'status' => 'aktif',
        ]);

        return [$customer, $package];
    }

    private function credit(User $customer, int $amount): void
    {
        $account = LedgerAccount::query()->create(['user_id' => $customer->id, 'status' => 'aktif', 'currency' => 'IDR']);
        LedgerEntry::query()->create([
            'entry_number' => 'LED-FC-'.$customer->id,
            'ledger_account_id' => $account->id,
            'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => 'deposit',
            'amount' => $amount,
            'source_type' => User::class,
            'source_id' => $customer->id,
            'source_key' => 'credit-fc-'.$customer->id,
            'effective_at' => now(),
            'balance_after' => $amount,
        ]);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'role-fc-'.str()->uuid(), 'description' => 'FC test']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }

    /** @param list<string> $permissions */
    private function grant(User $user, array $permissions): void
    {
        $role = Role::query()->create(['name' => 'role-grant-'.$user->id.'-'.str()->random(5), 'description' => 'Grant']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
