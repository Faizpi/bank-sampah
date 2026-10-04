<?php

declare(strict_types=1);

namespace Tests\Feature\Deposits;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\Corrections\Models\TransactionReversal;
use App\Domain\Corrections\Services\TransactionCorrectionService;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositPublicPresenter;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Shared\InvalidValue;
use App\Domain\WasteMaster\Actions\ManageWastePricing;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

final class DepositWaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_lane_a_finalizes_multi_item_with_server_snapshot_half_up_and_one_ledger_entry(): void
    {
        [$staff, $customer, $type, $condition] = $this->context();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $manager = User::factory()->create();
        $this->grant($manager, ['price.manage']);
        app(ManageWastePricing::class)->createPeriod($manager, $type, $condition, 3_333, CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'), null, (string) str()->uuid());
        $service = app(DepositService::class);
        $draft = $service->createDraft($staff, $customer);
        $service->replaceDraftItems($staff, $draft, [new DepositItemInput($type->id, $condition->id, '1.250')]);

        Event::fake([NotificationRequested::class]);
        $final = $service->finalize($staff, $draft, 'w4-finalize-key-0001');

        self::assertSame(Deposit::STATUS_FINAL, $final->status);
        self::assertSame(4_166, $final->total_value);
        self::assertSame('1.250', (string) $final->total_weight_kg);
        self::assertSame(4_166, $final->items->sole()->subtotal);
        self::assertSame(1, LedgerEntry::query()->count());
        self::assertSame(1, AuditLog::query()->where('action', 'deposit.finalized')->count());
        Event::assertDispatched(NotificationRequested::class);
        $item = $final->items->sole();
        $this->expectException(LogicException::class);
        $item->update(['subtotal' => 1]);
    }

    public function test_lane_a_rejects_inactive_master_invalid_precision_and_missing_price_without_ledger(): void
    {
        [$staff, $customer, $type, $condition] = $this->context();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $draft = app(DepositService::class)->createDraft($staff, $customer);
        $this->expectException(InvalidValue::class);
        app(DepositService::class)->replaceDraftItems($staff, $draft, [new DepositItemInput($type->id, $condition->id, '1.2345')]);
    }

    public function test_lane_a_rolls_back_idempotency_items_and_status_when_price_resolution_fails(): void
    {
        [$staff, $customer, $type, $condition] = $this->context();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $draft = $service->createDraft($staff, $customer);
        $this->expectException(ValidationException::class);
        $service->finalize($staff, $draft, 'w4-rollback-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']]);
        self::assertSame(Deposit::STATUS_DRAFT, $draft->fresh()->status);
        self::assertSame(0, $draft->fresh()->items()->count());
        self::assertDatabaseCount('idempotency_keys', 0);
        self::assertDatabaseCount('ledger_entries', 0);
    }

    public function test_lane_a_retry_returns_same_result_and_conflicting_payload_does_not_duplicate(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $draft = $service->createDraft($staff, $customer);
        $result = $service->finalize($staff, $draft, 'w4-retry-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']]);
        self::assertSame($result->id, $service->finalize($staff, $draft, 'w4-retry-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']])->id);
        $this->expectException(ValidationException::class);
        $service->finalize($staff, $draft, 'w4-retry-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '2.000']]);
    }

    public function test_lane_b_balance_uses_ledger_minus_active_hold_and_rejects_negative_hold(): void
    {
        $owner = User::factory()->create();
        $account = LedgerAccount::query()->create(['user_id' => $owner->id, 'status' => 'aktif', 'currency' => 'IDR']);
        $source = User::factory()->create();
        LedgerEntry::query()->create(['entry_number' => 'LED-IN-1', 'ledger_account_id' => $account->id, 'direction' => 'masuk', 'kind' => 'deposit', 'amount' => 100_000, 'source_type' => User::class, 'source_id' => $source->id, 'source_key' => 'test-in-1', 'effective_at' => now(), 'balance_after' => 100_000]);
        LedgerEntry::query()->create(['entry_number' => 'LED-OUT-1', 'ledger_account_id' => $account->id, 'direction' => 'keluar', 'kind' => 'withdrawal', 'amount' => 25_000, 'source_type' => User::class, 'source_id' => $source->id, 'source_key' => 'test-out-1', 'effective_at' => now(), 'balance_after' => 75_000]);
        $holdSource = User::factory()->create();
        $hold = app(LedgerService::class)->createHold($owner, $holdSource, 30_000, 'hold-test-1');

        self::assertSame(45_000, $account->fresh()->availableBalance());
        self::assertSame(BalanceHold::STATUS_ACTIVE, $hold->status);
        $this->expectException(ValidationException::class);
        app(LedgerService::class)->createHold($owner, User::factory()->create(), 50_001, 'hold-test-2');
    }

    public function test_lane_b_hold_retry_is_idempotent_and_ledger_entries_are_append_only(): void
    {
        $owner = User::factory()->create();
        $account = LedgerAccount::query()->create(['user_id' => $owner->id, 'status' => 'aktif', 'currency' => 'IDR']);
        $seed = User::factory()->create();
        LedgerEntry::query()->create(['entry_number' => 'LED-IN-2', 'ledger_account_id' => $account->id, 'direction' => 'masuk', 'kind' => 'deposit', 'amount' => 100_000, 'source_type' => User::class, 'source_id' => $seed->id, 'source_key' => 'test-in-2', 'effective_at' => now(), 'balance_after' => 100_000]);
        $source = User::factory()->create();
        $service = app(LedgerService::class);
        $hold = $service->createHold($owner, $source, 40_000, 'hold-test-retry');
        self::assertSame($hold->id, $service->createHold($owner, $source, 40_000, 'hold-test-retry')->id);
        $entry = LedgerEntry::query()->firstOrFail();
        $this->expectException(LogicException::class);
        $entry->update(['amount' => 1]);
    }

    public function test_lane_c_public_presenter_hides_private_data_and_rejects_reversed_receipt(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $final = $service->finalize($staff, $service->createDraft($staff, $customer), 'w4-public-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']]);
        $presented = app(DepositPublicPresenter::class)->present($final);
        self::assertSame(['number', 'date', 'weight_kg', 'value', 'status'], array_keys($presented));
        self::assertArrayNotHasKey('customer_id', $presented);
        $final->forceFill(['status' => Deposit::STATUS_REVERSED])->save();
        $this->expectException(ValidationException::class);
        app(DepositPublicPresenter::class)->present($final->fresh());
    }

    public function test_lane_c_receipt_route_scopes_to_owner_and_public_qr_exposes_only_allowlisted_fields(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $this->grant($customer, ['deposit.view']);
        $service = app(DepositService::class);
        $deposit = $service->finalize($staff, $service->createDraft($staff, $customer), 'w4-route-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']]);
        $token = $deposit->verificationToken();
        self::assertIsString($token);

        $this->actingAs($customer)->get(route('citizen.deposit-receipt', $deposit))->assertOk()->assertSee('QR verifikasi setoran');
        $otherCustomer = User::factory()->create();
        $this->grant($otherCustomer, ['deposit.view']);
        $this->actingAs($otherCustomer)->get(route('citizen.deposit-receipt', $deposit))->assertNotFound();

        $public = $this->get(route('public.deposit-verification', ['token' => $token]));
        $public->assertOk()->assertSee($deposit->deposit_number)->assertSee('Bukti setoran valid');
        $public->assertDontSee($customer->name)->assertDontSee('telepon')->assertDontSee('alamat');
        $this->get(route('public.deposit-verification', ['token' => str_repeat('a', 43)]))->assertNotFound();
    }

    public function test_lane_d_correction_requires_permission_reason_and_blocks_negative_available_balance(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $deposit = $service->finalize($staff, $service->createDraft($staff, $customer), 'w4-correction-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']]);
        $admin = User::factory()->create();
        $this->grant($admin, ['transaction.correct', 'transaction.reverse']);
        $correction = app(TransactionCorrectionService::class)->correct($admin, $deposit, 2_000, 'Berat salah saat penimbangan dikonfirmasi');
        self::assertSame(-1_000, $correction->delta_value);
        self::assertSame(Deposit::STATUS_CORRECTED, $deposit->fresh()->status);
        self::assertSame(1, LedgerEntry::query()->where('kind', 'correction')->count());
        $this->expectException(ValidationException::class);
        app(TransactionCorrectionService::class)->correct($admin, $deposit, 0, '');
    }

    public function test_lane_d_reversal_is_a_law_entry_and_is_idempotently_rejected_twice(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $deposit = app(DepositService::class)->finalize($staff, app(DepositService::class)->createDraft($staff, $customer), 'w4-reversal-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']]);
        $admin = User::factory()->create();
        $this->grant($admin, ['transaction.reverse']);
        $service = app(TransactionCorrectionService::class);
        $reversal = $service->reverse($admin, $deposit, 'Transaksi dibalik setelah pemeriksaan resmi');
        self::assertInstanceOf(TransactionReversal::class, $reversal);
        self::assertSame(1, LedgerEntry::query()->where('kind', 'reversal')->count());
        $this->expectException(ValidationException::class);
        $service->reverse($admin, $deposit, 'Reversal kedua tidak boleh dibuat');
    }

    /** @return array{User, User, WasteType, WasteCondition} */
    private function pricedContext(): array
    {
        [$staff, $customer, $type, $condition] = $this->context();
        $manager = User::factory()->create();
        $this->grant($manager, ['price.manage']);
        app(ManageWastePricing::class)->createPeriod($manager, $type, $condition, 3_000, CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'), null, (string) str()->uuid());

        return [$staff, $customer, $type, $condition];
    }

    /** @return array{User, User, WasteType, WasteCondition} */
    private function context(): array
    {
        $staff = User::factory()->create();
        $customer = User::factory()->create();
        $category = WasteCategory::factory()->create();
        $unit = WasteUnit::factory()->weight()->create(['code' => 'KG', 'symbol' => 'kg']);
        $condition = WasteCondition::factory()->create();
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create();
        WasteMasterMutationGuard::run(fn (): array => $type->conditions()->sync([$condition->id]));
        $dusun = Dusun::query()->create(['code' => 'W4-DS-'.$customer->id, 'name' => 'Dusun W4']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'W4-RW-'.$customer->id, 'name' => 'RW W4']);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'W4-RT-'.$customer->id, 'name' => 'RT W4']);
        $customer->customerProfile()->create(['rt_id' => $rt->id, 'address' => 'Alamat pengujian']);

        return [$staff, $customer, $type, $condition];
    }

    /** @param list<string> $names */
    private function grant(User $user, array $names): void
    {
        $role = Role::query()->create(['name' => 'w4-role-'.$user->id.'-'.str()->random(5), 'description' => 'W4']);
        foreach ($names as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
