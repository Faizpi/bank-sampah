<?php

declare(strict_types=1);

namespace Tests\Feature\Groceries;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Groceries\Actions\ManageGroceryPackages;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Livewire\Citizen\GroceryRequestForm;
use App\Livewire\Citizen\GroceryShow;
use App\Livewire\Officer\GroceryTasks;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
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

    public function test_citizen_request_form_shows_package_contents_and_value_before_selection(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.package.view', 'grocery.request']);

        Livewire::actingAs($customer)
            ->test(GroceryRequestForm::class)
            ->assertSee($package->name)
            ->assertSee($package->contents)
            ->assertSee('Rp75.000')
            ->assertDontSee('Bantuan gratis');
    }

    public function test_citizen_request_form_marks_the_selected_package_and_blocks_packages_above_available_balance(): void
    {
        [$customer, $expensivePackage] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.package.view', 'grocery.request']);
        $this->credit($customer, 50_000);
        $affordablePackage = GroceryPackage::query()->create([
            'code' => 'GRC-W7-AFFORDABLE-'.$customer->id,
            'name' => 'Paket Terjangkau',
            'contents' => 'Beras dan minyak',
            'value' => 25_000,
            'status' => 'aktif',
        ]);

        $component = Livewire::actingAs($customer)
            ->test(GroceryRequestForm::class)
            ->assertSee('Saldo tersedia')
            ->assertSee('Rp50.000')
            ->assertSee('Saldo belum cukup')
            ->assertSeeHtml('wire:model.live="packageId"')
            ->set('packageId', (string) $affordablePackage->id)
            ->assertSet('packageId', (string) $affordablePackage->id)
            ->assertSee('Paket dipilih');

        $component
            ->set('packageId', (string) $expensivePackage->id)
            ->assertSee('Saldo belum cukup')
            ->assertSee('kurang Rp25.000');
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

    public function test_lane_b_ready_for_handover_query_requires_direct_handover_permission(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);

        $this->expectException(AuthorizationException::class);
        app(GroceryService::class)->readyForHandover($customer)->get();
    }

    public function test_officer_grocery_lane_accepts_handover_only_staff_and_denies_users_without_staff_action_permission(): void
    {
        $handoverOfficer = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($handoverOfficer, ['user.view', 'user.view.all', 'grocery.view', 'grocery.handover']);

        Livewire::actingAs($handoverOfficer)
            ->test(GroceryTasks::class)
            ->assertSee('Tugas sembako')
            ->assertSee('Serah-terima hanya tersedia bagi petugas dengan izin penyerahan dan memerlukan verifikasi penerima serta bukti privat.')
            ->assertDontSee('Catat bantuan gratis');

        $unauthorized = User::factory()->create(['status' => UserStatus::Active]);

        Livewire::actingAs($unauthorized)
            ->test(GroceryTasks::class)
            ->assertForbidden();
        $this->actingAs($unauthorized)->get(route('officer.grocery.tasks'))->assertForbidden();
    }

    public function test_filament_grocery_path_denies_handover_without_direct_handover_permission(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        $redemption = app(GroceryService::class)->request($customer, ['package_id' => $package->id], 'w7-filament-handover-denial-0001');
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($admin, ['user.view', 'user.view.all', 'grocery.view', 'grocery.approve', 'grocery.prepare']);

        self::assertFalse(Gate::forUser($admin)->allows('handover', $redemption));
    }

    public function test_authorized_handover_ui_exposes_ready_action_and_private_proof_form(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        $approver = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($approver, ['grocery.approve', 'grocery.view', 'user.view.all']);
        $officer = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($officer, ['grocery.view', 'grocery.prepare', 'grocery.handover', 'grocery.package.view', 'user.view.all']);
        $service = app(GroceryService::class);
        $redemption = $service->request($customer, ['package_id' => $package->id], 'w7-ui-handover-request-0001');
        $redemption = $service->approve($approver, $redemption, true, 'Ketersediaan UI dikonfirmasi.');
        $redemption = $service->ready($officer, $service->prepare($officer, $redemption));

        Livewire::actingAs($officer)
            ->test(GroceryTasks::class)
            ->assertSee($redemption->request_number)
            ->assertSee('Proses serah-terima')
            ->call('select', $redemption->id)
            ->assertSee('Verifikasi penerima dan bukti')
            ->assertSee('Bukti serah-terima')
            ->assertSee('Bukti disimpan privat');
    }

    public function test_lane_b_request_snapshots_value_creates_one_hold_and_same_retry_returns_original(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view', 'grocery.cancel']);
        $this->credit($customer, 100_000);
        Event::fake([NotificationRequested::class]);
        $service = app(GroceryService::class);

        $created = $service->request($customer, ['package_id' => $package->id], 'w7-request-key-0001');
        $retry = $service->request($customer, ['package_id' => $package->id], 'w7-request-key-0001');

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
        $otherPackage = GroceryPackage::query()->create([
            'code' => 'GRC-W7-CONFLICT-'.$customer->id,
            'name' => 'Paket Konflik',
            'contents' => 'Beras dan minyak',
            'value' => 25_000,
            'status' => 'aktif',
        ]);
        $this->expectException(ValidationException::class);
        $service->request($customer, ['package_id' => $otherPackage->id], 'w7-conflict-key-0001');
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

    public function test_ready_redemption_shows_citizen_handover_guidance_and_notification(): void
    {
        Event::fake([NotificationRequested::class]);
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        $approver = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($approver, ['grocery.approve', 'grocery.view', 'user.view.all']);
        $officer = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($officer, ['grocery.prepare', 'grocery.view', 'user.view.all']);
        $service = app(GroceryService::class);
        $redemption = $service->approve($approver, $service->request($customer, ['package_id' => $package->id], 'w7-ready-guidance-request-0001'), true, 'Paket tersedia.');
        $ready = $service->ready($officer, $service->prepare($officer, $redemption));

        Livewire::actingAs($customer)
            ->test(GroceryShow::class, ['redemption' => $ready])
            ->assertSee('Langkah selanjutnya')
            ->assertSee('Bawa kartu nasabah atau siapkan nomor nasabah Anda')
            ->assertSee('tunggu petugas melakukan serah-terima paket');

        Event::assertDispatched(NotificationRequested::class, function (NotificationRequested $event): bool {
            return str_contains($event->payload->body, 'Bawa kartu nasabah atau siapkan nomor nasabah Anda')
                && str_contains($event->payload->body, 'tunggu petugas melakukan serah-terima paket');
        });
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

    public function test_terminal_handover_with_a_different_idempotency_key_does_not_swallow_validation(): void
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
        $redemption = $service->ready($staff, $service->prepare($staff, $service->approve($approver, $service->request($customer, ['package_id' => $package->id], 'w7-reconcile-request-0001'), true, 'Tersedia.')));
        $reference = (string) $customer->customerProfile?->customer_number;
        $service->handover($staff, $redemption, 'nomor_nasabah', $reference, UploadedFile::fake()->image('committed.png'), 'w7-reconcile-original-0001');

        Livewire::actingAs($staff)
            ->test(GroceryTasks::class)
            ->set('selectedRedemptionId', $redemption->id)
            ->set('recipientVerification', 'nomor_nasabah')
            ->set('recipientReference', $reference)
            ->set('proof', UploadedFile::fake()->image('different.png'))
            ->set('idempotencyKey', 'w7-reconcile-different-0001')
            ->set('handoverReviewOpen', true)
            ->call('handover')
            ->assertHasErrors(['status']);
    }

    public function test_same_area_officers_can_continue_preparation_and_handover_without_a_personal_assignment(): void
    {
        Storage::fake('media_private');
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        $approver = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($approver, ['grocery.approve', 'grocery.view', 'user.view.all']);
        $areaManager = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($areaManager, ['region.manage']);
        $rt = $customer->customerProfile()->firstOrFail()->rt()->firstOrFail();
        $area = ServiceArea::query()->whereHas('rts', static fn ($rts) => $rts->whereKey($rt->id))->sole();
        $preparer = User::factory()->create(['status' => UserStatus::Active]);
        $handoverOfficer = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($preparer, ['user.view', 'user.view.area', 'grocery.view', 'grocery.prepare']);
        $this->grant($handoverOfficer, ['user.view', 'user.view.area', 'grocery.view', 'grocery.prepare', 'grocery.handover']);
        StaffProfile::query()->create(['user_id' => $preparer->id, 'staff_number' => 'STF-W7-P-'.$preparer->id, 'service_area_id' => $area->id, 'active_from' => today()->subDay()]);
        StaffProfile::query()->create(['user_id' => $handoverOfficer->id, 'staff_number' => 'STF-W7-H-'.$handoverOfficer->id, 'service_area_id' => $area->id, 'active_from' => today()->subDay()]);
        $service = app(GroceryService::class);
        $redemption = $service->approve($approver, $service->request($customer, ['package_id' => $package->id], 'w7-shared-area-request-0001'), true, 'Paket tersedia.');

        $preparing = $service->prepare($preparer, $redemption);
        $ready = $service->ready($handoverOfficer, $preparing);
        $completed = $service->handover($handoverOfficer, $ready, 'nomor_nasabah', (string) $customer->customerProfile?->customer_number, UploadedFile::fake()->image('shared-area-proof.png'), 'w7-shared-area-handover-0001');

        self::assertSame($preparer->id, $completed->prepared_by_id);
        self::assertSame($handoverOfficer->id, $completed->handover_actor_id);
        self::assertSame(GroceryStatus::Completed, $completed->status);
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

    public function test_lane_d_rejects_removed_free_aid_and_assisted_request_paths(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $requester = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($requester, ['grocery.request', 'grocery.view', 'user.view', 'user.view.all']);
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        $service = app(GroceryService::class);

        try {
            $service->request($customer, ['package_id' => $package->id, 'source_type' => 'bantuan_gratis'], 'w7-removed-source-request-0001');
            self::fail('Bantuan gratis must not be accepted.');
        } catch (ValidationException) {
            self::assertDatabaseCount('grocery_redemptions', 0);
            self::assertDatabaseCount('balance_holds', 0);
        }

        $this->expectException(AuthorizationException::class);
        $service->request($requester, ['customer_id' => $customer->id, 'package_id' => $package->id], 'w7-assisted-request-0001');
    }

    public function test_lane_d_cancel_and_expiry_release_hold_once_and_invalid_transition_stops(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view', 'grocery.cancel', 'grocery.prepare', 'user.view.all']);
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
        $this->actingAs($customer)->get(route('citizen.grocery.receipt', $redemption))
            ->assertOk()
            ->assertSee('Penyerahan berhasil')
            ->assertSee($redemption->request_number)
            ->assertSee('Rp75.000')
            ->assertSee('Berhasil')
            ->assertDontSee('Nomor bukti');
    }

    public function test_snapshot_scope_survives_relocation_and_requires_an_effective_assignment(): void
    {
        [$customer, $package] = $this->customerAndPackage();
        $this->grant($customer, ['grocery.request', 'grocery.view']);
        $this->credit($customer, 100_000);
        $manager = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($manager, ['region.manage']);
        $regions = app(ManageRegions::class);
        $newDusun = $regions->createDusun($manager, 'W7-MOVE-DS-'.$customer->id, 'Dusun Pindah');
        $newRw = $regions->createRw($manager, $newDusun, 'W7-MOVE-RW-'.$customer->id, 'RW Pindah');
        $newRt = $regions->createRt($manager, $newRw, 'W7-MOVE-RT-'.$customer->id, 'RT Pindah');
        $oldRt = $customer->customerProfile()->firstOrFail()->rt()->firstOrFail();
        $oldArea = ServiceArea::query()->whereHas('rts', static fn ($rts) => $rts->whereKey($oldRt->id))->sole();
        $newArea = $regions->createServiceArea($manager, 'Area Pindah '.$customer->id, [$newRt]);
        $oldOfficer = User::factory()->create(['status' => UserStatus::Active]);
        $newOfficer = User::factory()->create(['status' => UserStatus::Active]);
        $expiredOfficer = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($oldOfficer, ['grocery.view', 'grocery.approve', 'grocery.prepare']);
        $this->grant($newOfficer, ['grocery.view', 'grocery.approve', 'grocery.prepare']);
        $this->grant($expiredOfficer, ['grocery.view', 'grocery.approve']);
        StaffProfile::query()->create(['user_id' => $oldOfficer->id, 'staff_number' => 'STF-OLD-'.$oldOfficer->id, 'service_area_id' => $oldArea->id, 'active_from' => today()->subDay()]);
        StaffProfile::query()->create(['user_id' => $newOfficer->id, 'staff_number' => 'STF-NEW-'.$newOfficer->id, 'service_area_id' => $newArea->id, 'active_from' => today()->subDay()]);
        StaffProfile::query()->create(['user_id' => $expiredOfficer->id, 'staff_number' => 'STF-EXP-'.$expiredOfficer->id, 'service_area_id' => $oldArea->id, 'active_from' => today()->subDays(2), 'active_to' => today()->subDay()]);
        $service = app(GroceryService::class);
        $redemption = $service->request($customer, ['package_id' => $package->id], 'w7-area-snapshot-request-0001');
        self::assertSame($oldRt->id, $redemption->rt_id);
        self::assertSame($oldArea->id, $redemption->service_area_id);
        $customer->customerProfile()->firstOrFail()->forceFill(['rt_id' => $newRt->id])->save();
        self::assertFalse($service->canView($newOfficer, $redemption));
        self::assertFalse($service->canView($expiredOfficer, $redemption));
        try {
            $service->approve($newOfficer, $redemption, true, 'Paket tersedia.');
            self::fail('A staff member in only the customer\'s new area must be denied.');
        } catch (AuthorizationException) {
            self::assertSame(GroceryStatus::PendingVerification, $redemption->fresh()->status);
        }
        self::assertTrue($service->canView($oldOfficer, $redemption));
        self::assertSame(GroceryStatus::Approved, $service->approve($oldOfficer, $redemption, true, 'Paket tersedia.')->status);
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
        $regions->createServiceArea($manager, 'Area W7 '.$customer->id, [$rt]);
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
