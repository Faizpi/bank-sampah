<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Corrections\Models\TransactionCorrection;
use App\Domain\Corrections\Models\TransactionReversal;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Filament\Resources\Deposits\Models\Deposits\DepositResource;
use App\Filament\Resources\Deposits\Models\Deposits\Pages\ManageDeposits;
use App\Filament\Resources\Groceries\Models\GroceryRedemptions\GroceryRedemptionResource;
use App\Filament\Resources\Groceries\Models\GroceryRedemptions\Pages\ManageGroceryRedemptions;
use App\Filament\Resources\Ledger\Models\BalanceHolds\BalanceHoldResource;
use App\Filament\Resources\Ledger\Models\BalanceHolds\Pages\ManageBalanceHolds;
use App\Filament\Resources\Ledger\Models\LedgerEntries\LedgerEntryResource;
use App\Filament\Resources\Ledger\Models\LedgerEntries\Pages\ManageLedgerEntries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

final class TransactionLaneResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_backoffice_user_can_browse_deposits_entries_and_holds_without_mutation_pages(): void
    {
        [$customer, $deposit, $account, $entry, $hold] = $this->transactionContext();
        $admin = User::factory()->create();
        $this->grant($admin, 'ledger-admin', 'backoffice.access', 'deposit.view', 'ledger.view', 'user.view', 'user.view.all');
        $this->actingAs($admin);

        self::assertTrue(DepositResource::canViewAny());
        self::assertTrue(LedgerEntryResource::canViewAny());
        self::assertTrue(BalanceHoldResource::canViewAny());
        self::assertSame(['index'], array_keys(DepositResource::getPages()));
        self::assertSame(['index'], array_keys(LedgerEntryResource::getPages()));
        self::assertSame(['index'], array_keys(BalanceHoldResource::getPages()));

        Livewire::test(ManageDeposits::class)
            ->assertCanSeeTableRecords([$deposit])
            ->assertTableActionVisible('inspect', $deposit);
        Livewire::test(ManageLedgerEntries::class)
            ->assertCanSeeTableRecords([$entry])
            ->assertTableActionVisible('inspect', $entry);
        Livewire::test(ManageBalanceHolds::class)
            ->assertCanSeeTableRecords([$hold])
            ->assertTableActionVisible('inspect', $hold);

        self::assertSame($customer->id, $account->user_id);
        self::assertSame(LedgerEntry::KIND_DEPOSIT, $entry->kind);
        self::assertSame(BalanceHold::STATUS_ACTIVE, $hold->status);
    }

    public function test_deposits_default_to_newest_finalized_setoran_for_all_scope_backoffice_actor(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create();
        $earlier = Deposit::query()->create([
            'deposit_number' => 'DEP-20260813145455-EARLIER',
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'method' => 'langsung',
            'occurred_at' => '2026-08-13 14:54:55',
            'status' => Deposit::STATUS_FINAL,
            'total_value' => 10_000,
            'finalized_at' => '2026-08-13 14:54:55',
        ]);
        $later = Deposit::query()->create([
            'deposit_number' => 'DEP-20260814145455-LATER',
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'method' => 'langsung',
            'occurred_at' => '2026-08-14 14:54:55',
            'status' => Deposit::STATUS_FINAL,
            'total_value' => 10_000,
            'finalized_at' => '2026-08-14 14:54:55',
        ]);
        $admin = User::factory()->create();
        $this->grant($admin, 'deposit-viewer-all', 'backoffice.access', 'deposit.view', 'user.view', 'user.view.all');
        $this->actingAs($admin);

        Livewire::test(ManageDeposits::class)
            ->assertCanSeeTableRecords([$later, $earlier], inOrder: true);
    }

    public function test_grocery_redemptions_default_to_newest_requests_for_all_scope_backoffice_actor(): void
    {
        $customer = User::factory()->create();
        $package = GroceryPackage::query()->create([
            'code' => 'GRC-TABLE-ORDER',
            'name' => 'Paket Urutan Tabel',
            'contents' => 'Beras dan minyak',
            'value' => 50_000,
            'status' => 'aktif',
        ]);
        $earlier = GroceryRedemption::query()->create([
            'request_number' => 'GRC-20260813-EARLIER',
            'customer_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'grocery_package_id' => $package->id,
            'value_snapshot' => $package->value,
            'package_snapshot' => ['code' => $package->code, 'name' => $package->name, 'contents' => $package->contents, 'value' => $package->value],
            'status' => GroceryStatus::PendingVerification,
            'created_at' => '2026-08-13 14:54:55',
            'updated_at' => '2026-08-13 14:54:55',
        ]);
        $later = GroceryRedemption::query()->create([
            'request_number' => 'GRC-20260814-LATER',
            'customer_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'grocery_package_id' => $package->id,
            'value_snapshot' => $package->value,
            'package_snapshot' => ['code' => $package->code, 'name' => $package->name, 'contents' => $package->contents, 'value' => $package->value],
            'status' => GroceryStatus::PendingVerification,
            'created_at' => '2026-08-14 14:54:55',
            'updated_at' => '2026-08-14 14:54:55',
        ]);
        $admin = User::factory()->create();
        $this->grant($admin, 'grocery-viewer-all', 'backoffice.access', 'grocery.view', 'user.view', 'user.view.all');
        $this->actingAs($admin);

        Livewire::test(ManageGroceryRedemptions::class)
            ->assertCanSeeTableRecords([$later, $earlier], inOrder: true);
    }

    public function test_grocery_redemption_resource_exposes_operational_triage_controls(): void
    {
        $customer = User::factory()->create();
        $requester = User::factory()->create();
        $package = GroceryPackage::query()->create([
            'code' => 'GRC-TRIAGE',
            'name' => 'Paket Triage',
            'contents' => 'Beras dan minyak',
            'value' => 50_000,
            'status' => 'aktif',
        ]);
        $redemption = GroceryRedemption::query()->create([
            'request_number' => 'GRC-TRIAGE-001',
            'customer_id' => $customer->id,
            'requested_by_id' => $requester->id,
            'grocery_package_id' => $package->id,
            'value_snapshot' => $package->value,
            'package_snapshot' => ['code' => $package->code, 'name' => $package->name, 'contents' => $package->contents, 'value' => $package->value],
            'status' => GroceryStatus::PendingVerification,
        ]);
        $admin = User::factory()->create();
        $this->grant($admin, 'grocery-triage', 'backoffice.access', 'grocery.view', 'grocery.approve', 'grocery.prepare', 'user.view', 'user.view.all');
        $this->actingAs($admin);

        Livewire::test(ManageGroceryRedemptions::class)
            ->assertCanSeeTableRecords([$redemption])
            ->assertTableFilterExists('status')
            ->assertTableFilterExists('grocery_package_id')
            ->assertTableActionVisible('inspect', $redemption)
            ->assertTableActionVisible('approve', $redemption)
            ->assertTableActionVisible('reject', $redemption)
            ->assertTableActionHidden('prepare', $redemption)
            ->assertTableActionHidden('ready', $redemption)
            ->mountTableAction('inspect', $redemption)
            ->assertSchemaStateSet([
                'package' => 'Paket Triage',
                'held_amount' => 'Rp 50.000',
                'status' => 'Menunggu verifikasi',
                'customer' => $customer->name,
                'requested_by' => $requester->name,
            ]);

        self::assertTrue(GroceryRedemptionResource::canViewAny());
    }

    public function test_missing_permission_and_missing_explicit_scope_fail_closed(): void
    {
        [, $deposit, $account, $entry, $hold] = $this->transactionContext();
        $actor = User::factory()->create();
        $this->grant($actor, 'backoffice-only', 'backoffice.access');
        $this->actingAs($actor);

        self::assertFalse(DepositResource::canViewAny());
        self::assertFalse(LedgerEntryResource::canViewAny());
        self::assertFalse(BalanceHoldResource::canViewAny());
        self::assertFalse(DepositResource::getEloquentQuery()->whereKey($deposit)->exists());
        self::assertFalse(LedgerEntryResource::getEloquentQuery()->whereKey($entry)->exists());
        self::assertFalse(BalanceHoldResource::getEloquentQuery()->whereKey($hold)->exists());
        self::assertNotSame($actor->id, $account->user_id);
    }

    public function test_area_scope_is_applied_before_filters_for_all_three_financial_resources(): void
    {
        $area = $this->areaContext();
        $operator = $area['operator'];
        $allowedCustomer = $area['allowed'];
        $outsideCustomer = $area['outside'];
        $allowed = $this->createTransaction($allowedCustomer, $operator);
        $outside = $this->createTransaction($outsideCustomer, $operator);
        $this->grant($operator, 'area-ledger-viewer', 'backoffice.access', 'deposit.view', 'ledger.view', 'user.view', 'user.view.area');
        $this->actingAs($operator->fresh());

        Livewire::test(ManageDeposits::class)
            ->assertCanSeeTableRecords([$allowed['deposit']])
            ->assertCanNotSeeTableRecords([$outside['deposit']]);
        Livewire::test(ManageLedgerEntries::class)
            ->assertCanSeeTableRecords([$allowed['entry']])
            ->assertCanNotSeeTableRecords([$outside['entry']]);
        Livewire::test(ManageBalanceHolds::class)
            ->assertCanSeeTableRecords([$allowed['hold']])
            ->assertCanNotSeeTableRecords([$outside['hold']]);
    }

    public function test_correction_action_requires_reason_and_private_evidence_and_reversal_is_service_backed(): void
    {
        [$customer, $deposit] = $this->transactionContext();
        $admin = User::factory()->create();
        $this->grant($admin, 'correction-admin', 'backoffice.access', 'deposit.view', 'ledger.view', 'user.view', 'user.view.all', 'transaction.correct', 'transaction.reverse');
        $this->actingAs($admin);

        Livewire::test(ManageDeposits::class)
            ->assertTableActionVisible('correct', $deposit)
            ->callTableAction('correct', $deposit, data: [
                'new_value' => 17_000,
                'reason' => 'Bukti timbang resmi menunjukkan nilai yang benar.',
            ])
            ->assertHasFormErrors(['evidence']);
        self::assertDatabaseCount('transaction_corrections', 0);
        self::assertDatabaseCount('ledger_entries', 1);

        $corrected = $deposit->fresh();
        $livewire = Livewire::test(ManageDeposits::class);
        $evidence = $this->evidence();
        $evidence->name = $evidence->getClientOriginalName();
        $livewire->mountTableAction('correct', $corrected)->upload('mountedActions.0.data.evidence', [$evidence]);
        $livewire
            ->setTableActionData([
                'new_value' => 17_000,
                'reason' => 'Bukti timbang resmi menunjukkan nilai yang benar.',
            ])
            ->callMountedTableAction();

        self::assertDatabaseHas('transaction_corrections', [
            'deposit_id' => $deposit->id,
            'created_by' => $admin->id,
            'delta_value' => -3_000,
        ]);
        self::assertDatabaseCount('ledger_entries', 2);
        self::assertDatabaseHas('media', ['attachable_type' => TransactionCorrection::class]);
        self::assertSame(Deposit::STATUS_CORRECTED, $deposit->fresh()->status);

        $reversalDeposit = $this->transactionContext()[1];
        $reversalDeposit->customer->ledgerAccount->holds()->firstOrFail()->forceFill(['status' => BalanceHold::STATUS_RELEASED, 'released_at' => now()])->save();
        Livewire::test(ManageDeposits::class)
            ->assertTableActionVisible('reverse', $reversalDeposit)
            ->callTableAction('reverse', $reversalDeposit, data: [
                'reason' => 'Transaksi dibalik setelah pemeriksaan resmi.',
                'evidence' => [$this->evidence()],
            ])
            ->assertNotified();

        self::assertDatabaseHas('transaction_reversals', [
            'original_deposit_id' => $reversalDeposit->id,
            'created_by' => $admin->id,
        ]);
        self::assertSame(Deposit::STATUS_REVERSED, $reversalDeposit->fresh()->status);
        self::assertSame(1, TransactionReversal::query()->where('original_deposit_id', $reversalDeposit->id)->count());
        self::assertSame(4, LedgerEntry::query()->count());
        self::assertNotNull($customer->fresh());
    }

    /** @return array{User, Deposit, LedgerAccount, LedgerEntry, BalanceHold} */
    private function transactionContext(): array
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create();
        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-RESOURCE-'.$customer->id.'-'.str()->random(6),
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'method' => 'langsung',
            'occurred_at' => now(),
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.000',
            'total_value' => 20_000,
            'finalized_at' => now(),
        ]);
        $deposit->items()->create([
            'waste_type_id' => $this->wasteType()->id,
            'waste_condition_id' => $this->condition()->id,
            'waste_type_code' => 'PLS',
            'waste_type_name' => 'Plastik',
            'unit_code' => 'KG',
            'unit_name' => 'Kilogram',
            'unit_symbol' => 'kg',
            'condition_code' => 'BAIK',
            'condition_name' => 'Baik',
            'weight_kg' => '1.000',
            'price_per_unit' => 20_000,
            'subtotal' => 20_000,
            'rounding_version' => 'half-up-v1',
            'price_snapshot' => ['price' => 20_000, 'effective_from' => now()->toDateString()],
        ]);
        $account = LedgerAccount::query()->create(['user_id' => $customer->id, 'status' => 'aktif', 'currency' => 'IDR']);
        $entry = LedgerEntry::query()->create([
            'entry_number' => 'LED-RESOURCE-'.$customer->id.'-'.str()->random(6),
            'ledger_account_id' => $account->id,
            'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => LedgerEntry::KIND_DEPOSIT,
            'amount' => 20_000,
            'source_type' => Deposit::class,
            'source_id' => $deposit->id,
            'source_key' => 'deposit:resource:'.$deposit->id,
            'effective_at' => $deposit->occurred_at,
            'balance_after' => 20_000,
        ]);
        $hold = BalanceHold::query()->create([
            'hold_number' => 'HLD-RESOURCE-'.$customer->id.'-'.str()->random(6),
            'ledger_account_id' => $account->id,
            'source_type' => Deposit::class,
            'source_id' => $deposit->id,
            'source_key' => 'hold:resource:'.$deposit->id,
            'amount' => 2_000,
            'status' => BalanceHold::STATUS_ACTIVE,
            'held_at' => now(),
        ]);

        return [$customer, $deposit->fresh(['items']), $account, $entry, $hold];
    }

    /** @return array{operator: User, allowed: User, outside: User} */
    private function areaContext(): array
    {
        $operator = User::factory()->create();
        $allowed = User::factory()->create();
        $outside = User::factory()->create();
        $area = ServiceArea::query()->create(['name' => 'Area resource '.$operator->id, 'is_active' => true]);
        $dusun = Dusun::query()->create(['code' => 'DS-RES-'.$operator->id, 'name' => 'Dusun Resource', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-RES-'.$operator->id, 'name' => 'RW Resource', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-RES-'.$operator->id, 'name' => 'RT Resource', 'is_active' => true]);
        RegionMutationGuard::run(fn (): array => $area->rts()->sync([$rt->id]));
        StaffProfile::query()->create(['user_id' => $operator->id, 'staff_number' => 'STF-RES-'.$operator->id, 'service_area_id' => $area->id, 'active_from' => today()->subDay(), 'active_to' => today()->addDay()]);
        $allowed->customerProfile()->create(['rt_id' => $rt->id, 'address' => 'Alamat area']);
        $outside->customerProfile()->create(['rt_id' => $this->outsideRt($operator->id)->id, 'address' => 'Alamat luar']);

        return ['operator' => $operator, 'allowed' => $allowed, 'outside' => $outside];
    }

    /** @return array{deposit: Deposit, entry: LedgerEntry, hold: BalanceHold} */
    private function createTransaction(User $customer, User $staff): array
    {
        $deposit = Deposit::query()->create(['deposit_number' => 'DEP-SCOPE-'.str()->random(12), 'customer_id' => $customer->id, 'staff_id' => $staff->id, 'method' => 'langsung', 'occurred_at' => now(), 'status' => Deposit::STATUS_FINAL, 'total_value' => 5_000, 'finalized_at' => now()]);
        $account = LedgerAccount::query()->create(['user_id' => $customer->id, 'status' => 'aktif', 'currency' => 'IDR']);
        $entry = LedgerEntry::query()->create(['entry_number' => 'LED-SCOPE-'.str()->random(12), 'ledger_account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_IN, 'kind' => LedgerEntry::KIND_DEPOSIT, 'amount' => 5_000, 'source_type' => Deposit::class, 'source_id' => $deposit->id, 'source_key' => 'deposit:scope:'.str()->random(12), 'effective_at' => now(), 'balance_after' => 5_000]);
        $hold = BalanceHold::query()->create(['hold_number' => 'HLD-SCOPE-'.str()->random(12), 'ledger_account_id' => $account->id, 'source_type' => Deposit::class, 'source_id' => $deposit->id, 'source_key' => 'hold:scope:'.str()->random(12), 'amount' => 1_000, 'status' => BalanceHold::STATUS_ACTIVE, 'held_at' => now()]);

        return ['deposit' => $deposit, 'entry' => $entry, 'hold' => $hold];
    }

    private function outsideRt(int $key): Rt
    {
        $dusun = Dusun::query()->create(['code' => 'DS-OUT-'.$key, 'name' => 'Dusun Outside', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-OUT-'.$key, 'name' => 'RW Outside', 'is_active' => true]);

        return Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-OUT-'.$key, 'name' => 'RT Outside', 'is_active' => true]);
    }

    private function wasteType(): object
    {
        return WasteType::factory()->create();
    }

    private function condition(): object
    {
        return WasteCondition::factory()->create();
    }

    private function evidence(): UploadedFile
    {
        return UploadedFile::fake()->image('correction-evidence.png', 1, 1);
    }

    private function grant(User $user, string $roleName, string ...$permissions): void
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName]);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->syncWithoutDetaching($permission);
        }
        $user->roles()->syncWithoutDetaching($role);
    }
}
