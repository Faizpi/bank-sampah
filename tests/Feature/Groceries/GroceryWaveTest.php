<?php

declare(strict_types=1);

namespace Tests\Feature\Groceries;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\Groceries\Actions\ManageGroceryPackages;
use App\Domain\Groceries\Enums\GrocerySource;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

final class GroceryWaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_lane_a_catalog_requires_permission_and_exposes_no_stock_detail(): void
    {
        $viewer = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($viewer, ['grocery.package.view']);
        $package = GroceryPackage::query()->create([
            'code' => 'GRC-A-001',
            'name' => 'Paket Hemat',
            'contents' => 'Beras, minyak, dan gula',
            'value' => 75_000,
            'status' => 'aktif',
        ]);

        self::assertTrue(app(GroceryService::class)->activePackages($viewer)->whereKey($package->id)->exists());
        self::assertFalse(array_key_exists('stock', $package->getAttributes()));
        self::assertFalse(array_key_exists('quantity', $package->getAttributes()));
    }

    public function test_lane_a_package_validation_rejects_non_positive_value_and_invalid_period(): void
    {
        $manager = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($manager, ['grocery.package.manage']);
        $action = app(ManageGroceryPackages::class);

        try {
            $action->create($manager, 'GRC-A-002', 'Paket Valid', 'Beras dan minyak', 0);
            self::fail('A package value must be positive.');
        } catch (ValidationException) {
            self::assertDatabaseCount('grocery_packages', 0);
        }

        $this->expectException(ValidationException::class);
        $action->create($manager, 'GRC-A-003', 'Paket Valid', 'Beras dan minyak', 50_000, '2026-08-10', '2026-08-09');
    }

    public function test_lane_b_request_snapshots_value_creates_one_hold_and_same_retry_returns_original(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view', 'grocery.cancel']);
        $this->credit($customer, 100_000);
        Event::fake([NotificationRequested::class]);
        $service = app(GroceryService::class);

        $created = $service->request($customer, ['package_id' => $package->id, 'source_type' => GrocerySource::Balance->value], 'w7-request-key-0001');
        $retry = $service->request($customer, ['package_id' => $package->id, 'source_type' => GrocerySource::Balance->value], 'w7-request-key-0001');

        self::assertSame($created->id, $retry->id);
        self::assertSame(GroceryStatus::PendingVerification, $created->status);
        self::assertSame(75_000, $created->value_snapshot);
        self::assertSame(75_000, $created->package_snapshot['value']);
        self::assertSame(1, BalanceHold::query()->where('source_type', GroceryRedemption::class)->where('source_id', $created->id)->count());
        self::assertSame(25_000, $customer->ledgerAccount()->firstOrFail()->fresh()->availableBalance());
        Event::assertDispatchedTimes(NotificationRequested::class, 1);
    }

    public function test_lane_b_rejects_inactive_package_insufficient_balance_and_conflicting_retry_without_effects(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $package->forceFill(['status' => 'nonaktif'])->save();
        $service = app(GroceryService::class);

        try {
            $service->request($customer, ['package_id' => $package->id], 'w7-inactive-key-0001');
            self::fail('An inactive package must be rejected.');
        } catch (ValidationException) {
            self::assertDatabaseCount('grocery_redemptions', 0);
            self::assertDatabaseCount('balance_holds', 0);
        }

        $package->forceFill(['status' => 'aktif'])->save();
        $this->credit($customer, 50_000);
        try {
            $service->request($customer, ['package_id' => $package->id], 'w7-insufficient-key-0001');
            self::fail('Insufficient balance must be rejected.');
        } catch (ValidationException) {
            self::assertDatabaseCount('grocery_redemptions', 0);
            self::assertDatabaseCount('balance_holds', 0);
        }

        $package->forceFill(['value' => 25_000])->save();
        $service->request($customer, ['package_id' => $package->id], 'w7-conflict-key-0001');
        $this->expectException(AuthorizationException::class);
        $service->request($customer, ['package_id' => $package->id, 'source_type' => GrocerySource::FreeAid->value], 'w7-conflict-key-0001');
    }

    public function test_lane_b_snapshot_is_immutable_and_approval_cannot_be_self_approved(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view', 'grocery.approve']);
        $this->credit($customer, 100_000);
        $service = app(GroceryService::class);
        $redemption = $service->request($customer, ['package_id' => $package->id], 'w7-immutable-key-0001');

        try {
            $redemption->forceFill(['value_snapshot' => 1])->save();
            self::fail('The value snapshot must be immutable.');
        } catch (LogicException) {
            self::assertSame(75_000, $redemption->fresh()->value_snapshot);
        }

        $this->expectException(AuthorizationException::class);
        $service->approve($customer, $redemption, true, 'Ketersediaan dikonfirmasi.');
    }

    public function test_lane_b_rejection_releases_hold_once_and_creates_no_outgoing_ledger(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        $approver = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($approver, ['grocery.approve', 'grocery.view', 'user.view.all']);
        $service = app(GroceryService::class);
        $redemption = $service->request($customer, ['package_id' => $package->id], 'w7-reject-key-0001');

        $rejected = $service->approve($approver, $redemption, false, null, 'Ketersediaan manual tidak mencukupi.');
        $retry = $service->approve($approver, $rejected, false, null, 'Percobaan penolakan kedua.');

        self::assertSame(GroceryStatus::Rejected, $retry->status);
        self::assertSame(BalanceHold::STATUS_RELEASED, $retry->balanceHold()->firstOrFail()->status);
        self::assertSame(0, LedgerEntry::query()->where('source_type', GroceryRedemption::class)->count());
        self::assertSame(100_000, $customer->ledgerAccount()->firstOrFail()->fresh()->availableBalance());
        self::assertSame(1, AuditLog::query()->where('action', 'grocery.rejected')->count());
    }

    public function test_lane_c_approval_prepare_ready_and_handover_convert_hold_once_with_private_proof(): void
    {
        Storage::fake('media_private');
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 150_000);
        $approver = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($approver, ['grocery.approve', 'grocery.view', 'user.view.all']);
        $staff = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($staff, ['grocery.prepare', 'grocery.handover', 'grocery.view', 'user.view.all']);
        $service = app(GroceryService::class);
        $redemption = $service->request($customer, ['package_id' => $package->id], 'w7-handover-request-0001');
        $redemption = $service->approve($approver, $redemption, true, 'Ketersediaan manual dikonfirmasi.');
        $redemption = $service->prepare($staff, $redemption);
        $redemption = $service->ready($staff, $redemption);
        $proof = UploadedFile::fake()->image('bukti-sembako.png');
        $customerNumber = (string) $customer->customerProfile?->customer_number;

        $completed = $service->handover($staff, $redemption, 'nomor_nasabah', $customerNumber, $proof, 'w7-handover-key-0001');
        $retry = $service->handover($staff, $completed, 'nomor_nasabah', $customerNumber, UploadedFile::fake()->image('retry.png'), 'w7-handover-key-0001');

        self::assertSame($completed->id, $retry->id);
        self::assertSame(GroceryStatus::Completed, $completed->status);
        self::assertSame(BalanceHold::STATUS_CONVERTED, $completed->balanceHold()->firstOrFail()->status);
        self::assertSame(1, LedgerEntry::query()->where('source_type', GroceryRedemption::class)->where('direction', LedgerEntry::DIRECTION_OUT)->count());
        self::assertSame(75_000, $customer->ledgerAccount()->firstOrFail()->fresh()->availableBalance());
        self::assertSame('private', $completed->proofMedia()->firstOrFail()->getRawOriginal('visibility'));
        Storage::disk('media_private')->assertExists($completed->proofMedia->path);
        self::assertSame(1, AuditLog::query()->where('action', 'grocery.handed_over')->count());
    }

    public function test_lane_c_rejects_invalid_recipient_and_approval_handover_self_action(): void
    {
        Storage::fake('media_private');
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        $approver = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($approver, ['grocery.approve', 'grocery.view', 'user.view', 'user.view.all', 'grocery.prepare', 'grocery.handover']);
        $service = app(GroceryService::class);
        $redemption = $service->approve($approver, $service->request($customer, ['package_id' => $package->id], 'w7-invalid-recipient-request-0001'), true, 'Ketersediaan dikonfirmasi.');
        $redemption = $service->prepare($approver, $redemption);
        $redemption = $service->ready($approver, $redemption);

        $this->expectException(AuthorizationException::class);
        $service->handover($approver, $redemption, 'nomor_nasabah', 'CST-INVALID', UploadedFile::fake()->image('proof.png'), 'w7-invalid-recipient-key-0001');
    }

    public function test_lane_c_handover_rolls_back_private_proof_when_hold_conversion_fails(): void
    {
        Storage::fake('media_private');
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        $approver = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($approver, ['grocery.approve', 'grocery.view', 'user.view.all']);
        $staff = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($staff, ['grocery.prepare', 'grocery.handover', 'grocery.view', 'user.view.all']);
        $service = app(GroceryService::class);
        $redemption = $service->ready($staff, $service->prepare($staff, $service->approve($approver, $service->request($customer, ['package_id' => $package->id], 'w7-rollback-request-0001'), true, 'Tersedia.')));
        app(LedgerService::class)->releaseHold($redemption->balanceHold()->firstOrFail());

        $this->expectException(ValidationException::class);
        try {
            $service->handover($staff, $redemption, 'nomor_nasabah', (string) $customer->customerProfile?->customer_number, UploadedFile::fake()->image('rollback-proof.png'), 'w7-rollback-handover-0001');
        } finally {
            $fresh = $redemption->fresh(['balanceHold', 'proofMedia']);
            self::assertSame(GroceryStatus::ReadyForPickup, $fresh->status);
            self::assertSame(0, LedgerEntry::query()->where('source_type', GroceryRedemption::class)->where('source_id', $redemption->id)->count());
            self::assertSame(0, $fresh->proofMedia()->count());
            self::assertSame(0, AuditLog::query()->where('action', 'grocery.handed_over')->count());
            Storage::disk('media_private')->assertDirectoryEmpty('/');
        }
    }

    public function test_notification_dispatch_waits_for_the_outer_transaction_to_commit(): void
    {
        Event::fake([NotificationRequested::class]);
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        $service = app(GroceryService::class);

        DB::transaction(function () use ($customer, $package, $service): void {
            $service->request($customer, ['package_id' => $package->id], 'w7-after-commit-request-0001');
            Event::assertNotDispatched(NotificationRequested::class);
        });

        Event::assertDispatched(NotificationRequested::class);
    }

    public function test_grocery_audit_is_append_only(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        app(GroceryService::class)->request($customer, ['package_id' => $package->id], 'w7-audit-request-0001');
        $audit = AuditLog::query()->where('action', 'grocery.requested')->sole();

        try {
            $audit->forceFill(['action' => 'grocery.changed'])->save();
            self::fail('Audit logs must be append-only.');
        } catch (LogicException) {
            self::assertSame('grocery.requested', $audit->fresh()->action);
        }

        $this->expectException(LogicException::class);
        $audit->delete();
    }

    public function test_lane_d_free_aid_has_no_hold_or_outgoing_ledger_and_handover_still_completes(): void
    {
        Storage::fake('media_private');
        [$customer, $package] = $this->customerAndPackage();
        $requester = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($requester, ['grocery.request', 'grocery.view', 'user.view', 'user.view.all']);
        $approver = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($approver, ['grocery.approve', 'grocery.view', 'user.view', 'user.view.all']);
        $staff = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($staff, ['grocery.prepare', 'grocery.handover', 'grocery.view', 'user.view', 'user.view.all']);
        $service = app(GroceryService::class);
        $redemption = $service->request($requester, ['customer_id' => $customer->id, 'package_id' => $package->id, 'source_type' => GrocerySource::FreeAid->value], 'w7-free-aid-request-0001');

        self::assertNull($redemption->balance_hold_id);
        self::assertSame(0, BalanceHold::query()->where('source_type', GroceryRedemption::class)->where('source_id', $redemption->id)->count());
        $redemption = $service->approve($approver, $redemption, true, 'Bantuan gratis tersedia.');
        $redemption = $service->prepare($staff, $redemption);
        $redemption = $service->ready($staff, $redemption);
        $redemption = $service->handover($staff, $redemption, 'nomor_nasabah', (string) $customer->customerProfile?->customer_number, UploadedFile::fake()->image('free-aid.png'), 'w7-free-aid-handover-0001');

        self::assertSame(GroceryStatus::Completed, $redemption->status);
        self::assertSame(0, LedgerEntry::query()->where('source_type', GroceryRedemption::class)->where('source_id', $redemption->id)->count());
    }

    public function test_lane_d_cancel_and_expiry_release_hold_once_and_invalid_transition_stops(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view', 'grocery.cancel', 'grocery.prepare']);
        $this->credit($customer, 200_000);
        $service = app(GroceryService::class);
        $cancelled = $service->request($customer, ['package_id' => $package->id], 'w7-cancel-key-0001');
        $cancelled = $service->cancel($customer, $cancelled, 'Warga menunda pengambilan paket.');
        $retry = $service->cancel($customer, $cancelled, 'Percobaan pembatalan kedua.');

        self::assertSame(GroceryStatus::Cancelled, $retry->status);
        self::assertSame(BalanceHold::STATUS_RELEASED, $retry->balanceHold()->firstOrFail()->status);
        self::assertSame(0, LedgerEntry::query()->where('source_type', GroceryRedemption::class)->count());

        $second = $service->request($customer, ['package_id' => $package->id], 'w7-expiry-key-0001');
        $second->forceFill(['expires_at' => now()->subDay()])->save();
        $expired = $service->expire($second);
        $expiredAgain = $service->expire($expired);
        self::assertSame(GroceryStatus::Expired, $expiredAgain->status);
        self::assertSame(BalanceHold::STATUS_RELEASED, $expiredAgain->balanceHold()->firstOrFail()->status);

        $this->expectException(ValidationException::class);
        $service->prepare($customer, $expiredAgain);
    }

    public function test_lane_d_private_proof_and_record_scope_fail_closed_for_other_user(): void
    {
        Storage::fake('media_private');
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        $approver = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($approver, ['grocery.approve', 'grocery.view', 'user.view.all']);
        $staff = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($staff, ['grocery.prepare', 'grocery.handover', 'grocery.view', 'user.view.all']);
        $service = app(GroceryService::class);
        $redemption = $service->ready($staff, $service->prepare($staff, $service->approve($approver, $service->request($customer, ['package_id' => $package->id], 'w7-idor-request-0001'), true, 'Tersedia.')));
        $redemption = $service->handover($staff, $redemption, 'nomor_nasabah', (string) $customer->customerProfile?->customer_number, UploadedFile::fake()->image('idor-proof.png'), 'w7-idor-handover-0001');
        $media = $redemption->proofMedia()->firstOrFail();
        $other = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($other, ['grocery.view']);

        self::assertFalse(app(GroceryService::class)->canView($other, $redemption));
        self::assertFalse(app(GroceryService::class)->canDownloadProof($other, $media));
        $this->grant($other, ['grocery.handover', 'grocery.view']);
        try {
            app(GroceryService::class)->handover($other, $redemption, 'nomor_nasabah', (string) $customer->customerProfile?->customer_number, UploadedFile::fake()->image('idor-handover.png'), 'w7-idor-handover-0002');
            self::fail('An out-of-scope handover must be rejected.');
        } catch (AuthorizationException) {
            self::assertSame(GroceryStatus::Completed, $redemption->fresh()->status);
        }
        $this->actingAs($other)->get(route('citizen.grocery.show', $redemption))->assertNotFound();
        $this->actingAs($other)->get(route('grocery.proof', $media))->assertNotFound();
        $this->actingAs($customer)->get(route('citizen.grocery.receipt', $redemption))->assertOk()->assertSee('Penyerahan berhasil');
    }

    /** @return array{User, GroceryPackage} */
    private function customerAndPackage(): array
    {
        $customer = User::factory()->create(['status' => UserStatus::Active]);
        $manager = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($manager, ['region.manage']);
        $regions = app(ManageRegions::class);
        $dusun = $regions->createDusun($manager, 'W7-DS-'.$customer->id, 'Dusun W7');
        $rw = $regions->createRw($manager, $dusun, 'W7-RW-'.$customer->id, 'RW W7');
        $rt = $regions->createRt($manager, $rw, 'W7-RT-'.$customer->id, 'RT W7');
        $customer->customerProfile()->create([
            'customer_number' => 'CST-'.str_pad((string) $customer->id, 8, '0', STR_PAD_LEFT),
            'rt_id' => $rt->id,
            'address' => 'Alamat warga W7',
        ]);
        $package = GroceryPackage::query()->create([
            'code' => 'GRC-W7-'.$customer->id,
            'name' => 'Paket W7',
            'contents' => 'Beras, minyak, gula, dan telur',
            'value' => 75_000,
            'status' => 'aktif',
        ]);

        return [$customer, $package];
    }

    private function credit(User $customer, int $amount): void
    {
        $account = LedgerAccount::query()->create(['user_id' => $customer->id, 'status' => 'aktif', 'currency' => 'IDR']);
        LedgerEntry::query()->create([
            'entry_number' => 'LED-W7-'.$customer->id,
            'ledger_account_id' => $account->id,
            'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => 'deposit',
            'amount' => $amount,
            'source_type' => User::class,
            'source_id' => $customer->id,
            'source_key' => 'w7-credit-'.$customer->id,
            'effective_at' => now(),
            'balance_after' => $amount,
        ]);
    }

    /** @param list<string> $permissions */
    private function grant(User $user, array $permissions): void
    {
        $role = Role::query()->create(['name' => 'w7-role-'.$user->id.'-'.str()->random(5), 'description' => 'W7']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
