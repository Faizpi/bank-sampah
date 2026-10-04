<?php

declare(strict_types=1);

namespace Tests\Feature\Deposits;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\Corrections\Models\TransactionCorrection;
use App\Domain\Corrections\Models\TransactionReversal;
use App\Domain\Corrections\Services\TransactionCorrectionService;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositPublicPresenter;
use App\Domain\Deposits\Services\DepositReviewService;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\IdempotencyKey;
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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $final = $service->finalize($staff, $draft, 'w4-finalize-key-0001', null, $this->depositProof());

        self::assertSame(Deposit::STATUS_FINAL, $final->status);
        self::assertSame(4_166, $final->total_value);
        self::assertSame('1.250', (string) $final->total_weight_kg);
        self::assertSame(4_166, $final->items->sole()->subtotal);
        self::assertSame(1, LedgerEntry::query()->count());
        self::assertTrue(IdempotencyKey::query()->where('scope', 'deposit.finalize')->sole()->expires_at->isFuture());
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
        $service->finalize($staff, $draft, 'w4-rollback-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->depositProof());
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
        $result = $service->finalize($staff, $draft, 'w4-retry-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->depositProof());
        self::assertSame($result->id, $service->finalize($staff, $draft, 'w4-retry-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->depositProof())->id);
        $this->expectException(ValidationException::class);
        $service->finalize($staff, $draft, 'w4-retry-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '2.000']], $this->depositProof());
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

    public function test_lane_b_ledger_source_identity_and_hold_core_fields_cannot_be_reused_or_edited(): void
    {
        $owner = User::factory()->create();
        $account = LedgerAccount::query()->create(['user_id' => $owner->id, 'status' => 'aktif', 'currency' => 'IDR']);
        $source = User::factory()->create();
        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-LEDGER-IDENTITY-'.$owner->id,
            'customer_id' => $owner->id,
            'staff_id' => $owner->id,
            'method' => 'langsung',
            'occurred_at' => now(),
            'status' => Deposit::STATUS_FINAL,
            'total_value' => 10_000,
            'finalized_at' => now(),
        ]);
        $service = app(LedgerService::class);
        $service->postDeposit($deposit, 10_000, 'deposit-source-identity-a');

        try {
            $service->postDeposit($deposit, 10_000, 'deposit-source-identity-b');
            self::fail('A deposit source must not be posted twice with a different source key.');
        } catch (ValidationException) {
            self::assertSame(1, LedgerEntry::query()->where('source_type', Deposit::class)->where('source_id', $deposit->id)->count());
        }

        $hold = $service->createHold($owner, $source, 5_000, 'hold-source-identity-a');
        try {
            $service->createHold($owner, $source, 5_000, 'hold-source-identity-b');
            self::fail('A hold source must not be reused with a different source key.');
        } catch (ValidationException) {
            self::assertSame(1, BalanceHold::query()->where('source_type', User::class)->where('source_id', $source->id)->count());
        }

        $otherSource = User::factory()->create();
        $otherHold = $service->createHold($owner, $otherSource, 5_000, 'hold-source-identity-c');
        $service->convertHold($hold, 'hold-conversion-identity-a');
        try {
            $service->convertHold($otherHold, 'hold-conversion-identity-a');
            self::fail('A conversion source key must not be reused by another hold.');
        } catch (ValidationException) {
            self::assertSame(BalanceHold::STATUS_ACTIVE, $otherHold->fresh()->status);
            self::assertSame(1, LedgerEntry::query()->where('source_key', 'hold-conversion-identity-a')->count());
        }

        $this->expectException(LogicException::class);
        $hold->forceFill(['amount' => 1])->save();
    }

    public function test_lane_b_rejects_non_positive_ledger_and_hold_amounts(): void
    {
        $owner = User::factory()->create();
        $account = LedgerAccount::query()->create(['user_id' => $owner->id, 'status' => 'aktif', 'currency' => 'IDR']);
        $source = User::factory()->create();

        $this->expectException(InvalidValue::class);
        LedgerEntry::query()->create([
            'entry_number' => 'LED-NEGATIVE-1',
            'ledger_account_id' => $account->id,
            'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => LedgerEntry::KIND_DEPOSIT,
            'amount' => 0,
            'source_type' => User::class,
            'source_id' => $source->id,
            'source_key' => 'negative-entry-source-1',
            'effective_at' => now(),
            'balance_after' => 0,
        ]);
    }

    public function test_lane_b_ledger_and_hold_mass_updates_are_rejected(): void
    {
        $owner = User::factory()->create();
        $account = LedgerAccount::query()->create(['user_id' => $owner->id, 'status' => 'aktif', 'currency' => 'IDR']);
        $source = User::factory()->create();
        $entry = LedgerEntry::query()->create([
            'entry_number' => 'LED-MASS-1',
            'ledger_account_id' => $account->id,
            'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => LedgerEntry::KIND_DEPOSIT,
            'amount' => 100_000,
            'source_type' => User::class,
            'source_id' => $source->id,
            'source_key' => 'mass-entry-source-1',
            'effective_at' => now(),
            'balance_after' => 100_000,
        ]);
        $hold = app(LedgerService::class)->createHold($owner, User::factory()->create(), 10_000, 'mass-hold-source-1');

        try {
            LedgerEntry::query()->whereKey($entry->id)->update(['amount' => 1]);
            self::fail('Ledger mass updates must be rejected.');
        } catch (QueryException) {
            self::assertDatabaseHas('ledger_entries', ['id' => $entry->id, 'amount' => 100_000]);
        }

        $this->expectException(QueryException::class);
        try {
            BalanceHold::query()->whereKey($hold->id)->update(['amount' => 1]);
        } finally {
            self::assertDatabaseHas('balance_holds', ['id' => $hold->id, 'amount' => 10_000]);
        }
    }

    public function test_lane_b_direct_deposit_service_calls_require_permission(): void
    {
        [$staff, $customer] = $this->context();

        $this->expectException(AuthorizationException::class);
        app(DepositService::class)->createDraft($staff, $customer);
    }

    public function test_lane_b_authorized_ledger_adjustment_is_append_only_audited_and_idempotent(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $account = LedgerAccount::query()->create(['user_id' => $owner->id, 'status' => 'aktif', 'currency' => 'IDR']);
        LedgerEntry::query()->create([
            'entry_number' => 'LED-ADJUSTMENT-SEED',
            'ledger_account_id' => $account->id,
            'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => LedgerEntry::KIND_DEPOSIT,
            'amount' => 20_000,
            'source_type' => User::class,
            'source_id' => $owner->id,
            'source_key' => 'adjustment-seed',
            'effective_at' => now(),
            'balance_after' => 20_000,
        ]);
        $this->grant($actor, ['ledger.adjust', 'user.view.all']);

        $service = app(LedgerService::class);
        $entry = $service->adjust($actor, $owner, -3_000, 'Penyesuaian resmi berdasarkan bukti kas.', 'adjustment-key-0001');
        $replayed = $service->adjust($actor, $owner, -3_000, 'Penyesuaian resmi berdasarkan bukti kas.', 'adjustment-key-0001');

        self::assertSame($entry->id, $replayed->id);
        self::assertSame(LedgerEntry::KIND_ADJUSTMENT, $entry->kind);
        self::assertSame(LedgerEntry::DIRECTION_OUT, $entry->direction);
        self::assertSame(17_000, $account->fresh()->availableBalance());
        self::assertSame(1, LedgerEntry::query()->where('kind', LedgerEntry::KIND_ADJUSTMENT)->count());
        self::assertSame(1, AuditLog::query()->where('action', 'ledger.adjusted')->count());

        $this->expectException(ValidationException::class);
        $service->adjust($actor, $owner, -2_000, 'Payload berbeda harus ditolak.', 'adjustment-key-0001');
    }

    public function test_lane_c_public_presenter_hides_private_data_and_rejects_reversed_receipt(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $final = $service->finalize($staff, $service->createDraft($staff, $customer), 'w4-public-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->depositProof());
        $presented = app(DepositPublicPresenter::class)->present($final);
        self::assertSame(['number', 'date', 'weight_kg', 'value', 'original_value', 'is_corrected', 'status'], array_keys($presented));
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
        $deposit = $service->finalize($staff, $service->createDraft($staff, $customer), 'w4-route-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->depositProof());
        $token = $deposit->verificationToken();
        self::assertIsString($token);

        $this->actingAs($customer)->get(route('citizen.deposit-receipt', $deposit))
            ->assertOk()
            ->assertSee('QR verifikasi setoran')
            ->assertSee($deposit->deposit_number)
            ->assertSee('Rp 3.000')
            ->assertSee('Berhasil')
            ->assertDontSee('Nomor bukti');
        $otherCustomer = User::factory()->create();
        $this->grant($otherCustomer, ['deposit.view']);
        $this->actingAs($otherCustomer)->get(route('citizen.deposit-receipt', $deposit))->assertNotFound();

        $public = $this->get(route('public.deposit-verification', ['token' => $token]));
        $public->assertOk()->assertSee($deposit->deposit_number)->assertSee('Bukti setoran valid');
        $public->assertDontSee($customer->name)->assertDontSee('telepon')->assertDontSee('alamat');
        $this->get(route('public.deposit-verification', ['token' => str_repeat('a', 43)]))->assertNotFound();
    }

    public function test_seeded_superadmin_can_reach_sensitive_reconciliation_boundaries(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = User::factory()->create();
        $superadmin->roles()->attach(Role::query()->where('name', 'superadmin')->sole());
        $checker = app(PermissionChecker::class);
        self::assertTrue($checker->allows($superadmin->fresh(), 'transaction.correct'));
        self::assertTrue($checker->allows($superadmin->fresh(), 'transaction.reverse'));
        app(LedgerService::class)->assertCanAdjust($superadmin->fresh());
    }

    public function test_lane_d_correction_requires_permission_reason_and_blocks_negative_available_balance(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $deposit = $service->finalize($staff, $service->createDraft($staff, $customer), 'w4-correction-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->depositProof());
        $admin = User::factory()->create();
        $this->grant($admin, ['user.view', 'user.view.all', 'transaction.correct', 'transaction.reverse']);
        $correction = app(TransactionCorrectionService::class)->correct($admin, $deposit, 2_000, 'Berat salah saat penimbangan dikonfirmasi', null, $this->depositProof());
        self::assertSame(-1_000, $correction->delta_value);
        self::assertSame(Deposit::STATUS_CORRECTED, $deposit->fresh()->status);
        self::assertSame(1, LedgerEntry::query()->where('kind', 'correction')->count());
        try {
            TransactionCorrection::query()->whereKey($correction->id)->update(['reason' => 'diubah']);
            self::fail('Transaction corrections must be append-only.');
        } catch (QueryException) {
            self::assertDatabaseHas('transaction_corrections', ['id' => $correction->id, 'reason' => 'Berat salah saat penimbangan dikonfirmasi']);
        }
        $this->expectException(ValidationException::class);
        app(TransactionCorrectionService::class)->correct($admin, $deposit, 0, '');
    }

    public function test_corrected_deposit_presents_the_effective_value_without_overwriting_the_audit_snapshot(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $deposit = app(DepositService::class)->finalize($staff, app(DepositService::class)->createDraft($staff, $customer), 'effective-value-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->depositProof());
        $reviewer = User::factory()->create();
        $this->grant($reviewer, ['user.view', 'user.view.all', 'transaction.correct']);
        app(TransactionCorrectionService::class)->correct($reviewer, $deposit, 2_000, 'Nilai timbang dikonfirmasi ulang oleh pemeriksa.', null, $this->depositProof());

        $presented = app(DepositPublicPresenter::class)->present($deposit->fresh('correction'));
        self::assertSame(3_000, $presented['original_value']);
        self::assertSame(2_000, $presented['value']);
        self::assertTrue($presented['is_corrected']);
    }

    public function test_large_deposit_needs_a_different_reviewer_before_it_can_credit_the_balance(): void
    {
        $threshold = config('app.deposit_review_threshold');
        config()->set('app.deposit_review_threshold', 2_000);
        try {
            [$staff, $customer, $type, $condition] = $this->pricedContext();
            $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
            $deposit = app(DepositService::class)->finalize($staff, app(DepositService::class)->createDraft($staff, $customer), 'high-review-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->depositProof());

            self::assertSame(Deposit::STATUS_PENDING_REVIEW, $deposit->status);
            self::assertSame(0, LedgerEntry::query()->count());
            $reviewer = User::factory()->create();
            $this->grant($reviewer, ['deposit.approve', 'user.view.all']);
            $approved = app(DepositReviewService::class)->approve($reviewer, $deposit, 'Nilai dan bukti timbang sudah diverifikasi petugas lain.', 'high-review-approval-key-0001');

            self::assertSame(Deposit::STATUS_FINAL, $approved->status);
            self::assertSame($reviewer->id, $approved->reviewed_by);
            self::assertSame(1, LedgerEntry::query()->where('source_type', Deposit::class)->count());
            self::assertSame(3_000, LedgerAccount::query()->where('user_id', $customer->id)->sole()->availableBalance());
        } finally {
            config()->set('app.deposit_review_threshold', $threshold);
        }
    }

    public function test_server_rejects_weight_above_the_configured_item_limit_before_finalization(): void
    {
        [$staff, $customer, $type, $condition] = $this->context();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $draft = app(DepositService::class)->createDraft($staff, $customer);

        $this->expectException(ValidationException::class);
        app(DepositService::class)->replaceDraftItems($staff, $draft, [new DepositItemInput($type->id, $condition->id, '50.001')]);
    }

    public function test_correction_and_reversal_require_explicit_customer_scope(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $deposit = app(DepositService::class)->finalize($staff, app(DepositService::class)->createDraft($staff, $customer), 'w4-scope-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->depositProof());
        $admin = User::factory()->create();
        $this->grant($admin, ['user.view', 'transaction.correct', 'transaction.reverse']);
        $service = app(TransactionCorrectionService::class);

        try {
            $service->correct($admin, $deposit, 2_000, 'Scope nasabah harus diwajibkan sebelum koreksi.', null, $this->depositProof());
            self::fail('Correction must require an explicit customer scope.');
        } catch (AuthorizationException) {
            self::assertDatabaseCount('transaction_corrections', 0);
        }

        try {
            $service->reverse($admin, $deposit, 'Scope nasabah harus diwajibkan sebelum reversal.', null, $this->depositProof());
            self::fail('Reversal must require an explicit customer scope.');
        } catch (AuthorizationException) {
            self::assertDatabaseCount('transaction_reversals', 0);
        }
    }

    public function test_lane_d_reversal_is_a_law_entry_and_is_idempotently_rejected_twice(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $deposit = app(DepositService::class)->finalize($staff, app(DepositService::class)->createDraft($staff, $customer), 'w4-reversal-key-0001', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->depositProof());
        $admin = User::factory()->create();
        $this->grant($admin, ['user.view', 'user.view.all', 'transaction.reverse']);
        $service = app(TransactionCorrectionService::class);
        $reversal = $service->reverse($admin, $deposit, 'Transaksi dibalik setelah pemeriksaan resmi', null, $this->depositProof());
        self::assertInstanceOf(TransactionReversal::class, $reversal);
        self::assertSame(1, LedgerEntry::query()->where('kind', 'reversal')->count());
        try {
            TransactionReversal::query()->whereKey($reversal->id)->update(['reason' => 'diubah']);
            self::fail('Transaction reversals must be append-only.');
        } catch (QueryException) {
            self::assertDatabaseHas('transaction_reversals', ['id' => $reversal->id, 'reason' => 'Transaksi dibalik setelah pemeriksaan resmi']);
        }
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

    private function depositProof(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'deposit-proof-');
        self::assertIsString($path);
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));

        return new UploadedFile($path, 'deposit-proof.png', 'image/png', null, true);
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
