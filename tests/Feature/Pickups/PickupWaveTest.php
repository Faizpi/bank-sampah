<?php

declare(strict_types=1);

namespace Tests\Feature\Pickups;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Exceptions\PickupCapacityUnavailable;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Actions\ManageWastePricing;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PickupWaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_lane_a_submits_private_pickup_with_active_area_items_estimate_and_idempotency(): void
    {
        [$customer, $area, $type, $condition] = $this->context();
        $this->grant($customer, ['pickup.request', 'pickup.view']);
        $this->capacity($area, 5, '50.000');
        Storage::fake('media_private');
        Event::fake([NotificationRequested::class]);

        $service = app(PickupService::class);
        $data = $this->requestData($customer, $area);
        $photo = UploadedFile::fake()->image('pickup.jpg', 20, 20);
        $pickup = $service->submit($customer, $data, [['waste_type_id' => $type->id, 'estimated_weight_kg' => '2.500']], [$photo], 'w5-request-key-0001');
        $retry = $service->submit($customer, $data, [['waste_type_id' => $type->id, 'estimated_weight_kg' => '2.500']], [UploadedFile::fake()->image('pickup.jpg', 20, 20)], 'w5-request-key-0001');

        self::assertSame($pickup->id, $retry->id);
        self::assertSame(PickupStatus::PendingReview, $pickup->status);
        self::assertSame('2.500', (string) $pickup->estimated_weight_kg);
        self::assertSame(1, $pickup->items()->count());
        self::assertSame(1, $pickup->media()->count());
        self::assertSame('private', $pickup->media->sole()->getRawOriginal('visibility'));
        self::assertSame(1, AuditLog::query()->where('action', 'pickup.requested')->count());
        Event::assertNothingDispatched();
    }

    public function test_lane_a_rejects_missing_photo_inactive_area_and_estimate_without_financial_effect(): void
    {
        [$customer, $area, $type] = $this->context();
        $this->grant($customer, ['pickup.request']);
        $this->capacity($area, 5, '50.000');
        $data = $this->requestData($customer, $area);
        $service = app(PickupService::class);

        try {
            $service->submit($customer, $data, [['waste_type_id' => $type->id, 'estimated_weight_kg' => '1.000']], [], 'w5-missing-photo-0001');
            self::fail('Expected missing photo validation.');
        } catch (ValidationException) {
            self::assertDatabaseCount('pickup_requests', 0);
        }

        $regionManager = User::factory()->create();
        $this->grant($regionManager, ['region.manage']);
        app(ManageRegions::class)->deactivate($regionManager, $area);
        $this->expectException(ValidationException::class);
        $service->submit($customer, $data, [['waste_type_id' => $type->id, 'estimated_quantity' => 2]], [UploadedFile::fake()->image('pickup.png')], 'w5-inactive-area-0001');
        self::assertDatabaseCount('ledger_entries', 0);
    }

    public function test_lane_a_idor_does_not_allow_customer_to_read_or_download_other_customer_media(): void
    {
        [$customer, $area, $type] = $this->context();
        $other = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($customer, ['pickup.request', 'pickup.view']);
        $this->grant($other, ['pickup.view']);
        $this->capacity($area, 5, '50.000');
        Storage::fake('media_private');
        $pickup = app(PickupService::class)->submit($customer, $this->requestData($customer, $area), [['waste_type_id' => $type->id, 'estimated_quantity' => 2]], [UploadedFile::fake()->image('pickup.png')], 'w5-media-owner-0001');

        self::assertFalse(app(PickupService::class)->canView($other, $pickup));
        self::assertFalse(app(PickupService::class)->canDownloadMedia($other, $pickup->media->sole()));
    }

    public function test_lane_b_full_capacity_returns_alternatives_without_creating_request(): void
    {
        [$customer, $area, $type] = $this->context();
        $this->grant($customer, ['pickup.request']);
        $date = today()->addDay()->toDateString();
        $this->capacity($area, 1, '2.000', $date);
        $this->capacity($area, 3, '10.000', today()->addDays(2)->toDateString());
        $existing = PickupRequest::query()->create([
            'request_number' => 'PUP-EXISTING-0001', 'customer_id' => $customer->id, 'rt_id' => $customer->customerProfile->rt_id, 'service_area_id' => $area->id,
            'address' => 'Alamat existing', 'selected_date' => $date, 'estimated_weight_kg' => '2.000', 'status' => PickupStatus::PendingReview,
        ]);
        $service = app(PickupService::class);

        try {
            $service->submit($customer, $this->requestData($customer, $area, $date), [['waste_type_id' => $type->id, 'estimated_weight_kg' => '1.000']], [UploadedFile::fake()->image('pickup.png')], 'w5-capacity-full-0001');
            self::fail('Expected capacity failure.');
        } catch (PickupCapacityUnavailable $exception) {
            self::assertContains(today()->addDays(2)->toDateString(), $exception->alternatives);
        }

        self::assertDatabaseCount('pickup_requests', 1);
        self::assertSame(PickupStatus::PendingReview, $existing->fresh()->status);
    }

    public function test_lane_b_rejects_duplicate_idempotency_payload_and_rolls_back_capacity_request(): void
    {
        [$customer, $area, $type] = $this->context();
        $this->grant($customer, ['pickup.request']);
        $this->capacity($area, 2, '10.000');
        $service = app(PickupService::class);
        $data = $this->requestData($customer, $area);
        $service->submit($customer, $data, [['waste_type_id' => $type->id, 'estimated_quantity' => 1]], [UploadedFile::fake()->image('pickup.png')], 'w5-payload-key-0001');

        $this->expectException(ValidationException::class);
        $service->submit($customer, $data, [['waste_type_id' => $type->id, 'estimated_quantity' => 9]], [UploadedFile::fake()->image('pickup.png')], 'w5-payload-key-0001');
    }

    public function test_lane_c_review_reject_requires_reason_and_schedule_requires_area_assigned_active_staff(): void
    {
        [$customer, $area, $type] = $this->context();
        $admin = User::factory()->create();
        $staff = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($customer, ['pickup.request']);
        $this->grant($admin, ['pickup.review', 'pickup.schedule', 'pickup.view', 'user.view.all']);
        $this->grant($staff, ['pickup.view', 'pickup.execute', 'pickup.complete', 'user.view.area', 'user.view']);
        StaffProfile::query()->create(['user_id' => $staff->id, 'staff_number' => 'STF-W5-0001', 'service_area_id' => $area->id, 'active_from' => today(), 'active_to' => null]);
        $this->capacity($area, 3, '10.000');
        $pickup = app(PickupService::class)->submit($customer, $this->requestData($customer, $area), [['waste_type_id' => $type->id, 'estimated_quantity' => 1]], [UploadedFile::fake()->image('pickup.png')], 'w5-review-schedule-0001');
        $service = app(PickupService::class);

        try {
            $service->review($admin, $pickup, false, 'singkat');
            self::fail('Expected rejection reason validation.');
        } catch (ValidationException) {
            self::assertSame(PickupStatus::PendingReview, $pickup->fresh()->status);
        }
        $scheduled = $service->schedule($admin, $service->review($admin, $pickup, true), $staff);

        self::assertSame(PickupStatus::Scheduled, $scheduled->status);
        self::assertSame($staff->id, $scheduled->assigned_staff_id);
        self::assertSame(3, $scheduled->statusHistory()->count());
    }

    public function test_lane_c_acceptance_rechecks_capacity_after_new_reservations(): void
    {
        [$customer, $area, $type] = $this->context();
        $admin = User::factory()->create();
        $this->grant($customer, ['pickup.request']);
        $this->grant($admin, ['pickup.review', 'pickup.view', 'user.view.all']);
        $this->capacity($area, 2, '10.000');
        $service = app(PickupService::class);
        $pickup = $service->submit($customer, $this->requestData($customer, $area), [['waste_type_id' => $type->id, 'estimated_quantity' => 1]], [UploadedFile::fake()->image('pickup.png')], 'w5-accept-recheck-0001');

        PickupRequest::query()->create([
            'request_number' => 'PUP-BLOCKER-0001', 'customer_id' => $customer->id, 'rt_id' => $customer->customerProfile->rt_id, 'service_area_id' => $area->id,
            'address' => 'Alamat blocker satu', 'selected_date' => today()->addDay(), 'estimated_weight_kg' => '1.000', 'status' => PickupStatus::PendingReview,
        ]);
        PickupRequest::query()->create([
            'request_number' => 'PUP-BLOCKER-0002', 'customer_id' => $customer->id, 'rt_id' => $customer->customerProfile->rt_id, 'service_area_id' => $area->id,
            'address' => 'Alamat blocker dua', 'selected_date' => today()->addDay(), 'estimated_weight_kg' => '1.000', 'status' => PickupStatus::PendingReview,
        ]);

        $this->expectException(PickupCapacityUnavailable::class);
        $service->review($admin, $pickup, true);
        self::assertSame(PickupStatus::PendingReview, $pickup->fresh()->status);
    }

    public function test_lane_c_reject_is_terminal_and_never_schedules_or_creates_deposit(): void
    {
        [$customer, $area, $type] = $this->context();
        $admin = User::factory()->create();
        $this->grant($customer, ['pickup.request']);
        $this->grant($admin, ['pickup.review', 'pickup.schedule', 'pickup.view', 'user.view.all']);
        $this->capacity($area, 3, '10.000');
        $pickup = app(PickupService::class)->submit($customer, $this->requestData($customer, $area), [['waste_type_id' => $type->id, 'estimated_quantity' => 1]], [UploadedFile::fake()->image('pickup.png')], 'w5-reject-terminal-0001');
        $rejected = app(PickupService::class)->review($admin, $pickup, false, 'Alamat tidak sesuai kebijakan area layanan.');

        self::assertSame(PickupStatus::Rejected, $rejected->status);
        self::assertNull($rejected->scheduled_date);
        self::assertDatabaseCount('deposits', 0);
    }

    public function test_lane_d_assigned_staff_transitions_to_actual_weighted_deposit_and_completion_is_idempotent(): void
    {
        [$customer, $area, $type, $condition] = $this->pricedContext();
        $admin = User::factory()->create();
        $staff = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($customer, ['pickup.request']);
        $this->grant($admin, ['pickup.review', 'pickup.schedule', 'pickup.view', 'user.view.all']);
        $this->grant($staff, ['pickup.view', 'pickup.execute', 'pickup.complete', 'deposit.create', 'deposit.update-draft', 'deposit.finalize', 'user.view', 'user.view.all']);
        StaffProfile::query()->create(['user_id' => $staff->id, 'staff_number' => 'STF-W5-0002', 'service_area_id' => $area->id, 'active_from' => today(), 'active_to' => null]);
        $this->capacity($area, 3, '50.000');
        $pickup = app(PickupService::class)->submit($customer, $this->requestData($customer, $area), [['waste_type_id' => $type->id, 'estimated_weight_kg' => '9.000']], [UploadedFile::fake()->image('pickup.png')], 'w5-field-flow-0001');
        $service = app(PickupService::class);
        $pickup = $service->schedule($admin, $service->review($admin, $pickup, true), $staff);
        $pickup = $service->begin($staff, $pickup);
        $pickup = $service->markPickedUp($staff, $pickup);
        $completed = $service->complete($staff, $pickup, [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.250']], 'w5-complete-key-0001');
        $retry = $service->complete($staff, $pickup, [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.250']], 'w5-complete-key-0001');

        self::assertSame(PickupStatus::Completed, $completed->status);
        self::assertSame('1.250', (string) $completed->deposit->total_weight_kg);
        self::assertSame($completed->deposit_id, $retry->deposit_id);
        self::assertSame(1, Deposit::query()->where('method', 'penjemputan')->count());
        self::assertSame(3_750, (int) $customer->fresh()->ledgerAccount?->availableBalance());
        self::assertSame(1, AuditLog::query()->where('action', 'pickup.completed')->count());
    }

    public function test_lane_d_expiry_is_idempotent_terminal_and_audited_without_deposit_or_balance(): void
    {
        [$customer, $area, $type] = $this->context();
        $this->grant($customer, ['pickup.request']);
        $this->capacity($area, 3, '10.000');
        $pickup = app(PickupService::class)->submit($customer, $this->requestData($customer, $area), [['waste_type_id' => $type->id, 'estimated_quantity' => 1]], [UploadedFile::fake()->image('pickup.png')], 'w5-expiry-key-0001');
        $service = app(PickupService::class);

        $expired = $service->expire($pickup);
        $retry = $service->expire($expired);

        self::assertSame(PickupStatus::Cancelled, $expired->status);
        self::assertSame(PickupStatus::Cancelled, $retry->status);
        self::assertSame(2, $expired->statusHistory()->count());
        self::assertSame(1, AuditLog::query()->where('action', 'pickup.expired')->count());
        self::assertDatabaseCount('deposits', 0);
        self::assertDatabaseCount('ledger_entries', 0);
    }

    public function test_lane_d_invalid_transition_cancel_after_departure_and_estimate_not_balance_are_rejected(): void
    {
        [$customer, $area, $type] = $this->context();
        $admin = User::factory()->create();
        $staff = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($customer, ['pickup.request', 'pickup.cancel', 'pickup.view']);
        $this->grant($admin, ['pickup.review', 'pickup.schedule', 'pickup.view', 'user.view.all']);
        $this->grant($staff, ['pickup.view', 'pickup.execute']);
        StaffProfile::query()->create(['user_id' => $staff->id, 'staff_number' => 'STF-W5-0003', 'service_area_id' => $area->id, 'active_from' => today(), 'active_to' => null]);
        $this->capacity($area, 3, '50.000');
        $service = app(PickupService::class);
        $pickup = $service->submit($customer, $this->requestData($customer, $area), [['waste_type_id' => $type->id, 'estimated_weight_kg' => '9.000']], [UploadedFile::fake()->image('pickup.png')], 'w5-invalid-state-0001');
        $pickup = $service->schedule($admin, $service->review($admin, $pickup, true), $staff);
        $pickup = $service->begin($staff, $pickup);

        $this->expectException(AuthorizationException::class);
        $service->cancel($customer, $pickup, 'Tidak jadi');
        self::assertSame(PickupStatus::EnRoute, $pickup->fresh()->status);
        self::assertNull($customer->fresh()->ledgerAccount);
    }

    /** @return array{User, ServiceArea, WasteType, WasteCondition} */
    private function pricedContext(): array
    {
        [$customer, $area, $type, $condition] = $this->context();
        $manager = User::factory()->create();
        $this->grant($manager, ['price.manage']);
        app(ManageWastePricing::class)->createPeriod($manager, $type, $condition, 3_000, CarbonImmutable::parse(today()->toDateString(), 'Asia/Jakarta'), null, (string) str()->uuid());

        return [$customer, $area, $type, $condition];
    }

    /** @return array{User, ServiceArea, WasteType, WasteCondition} */
    private function context(): array
    {
        $customer = User::factory()->create(['status' => UserStatus::Active]);
        $manager = User::factory()->create();
        $this->grant($manager, ['region.manage']);
        $regions = app(ManageRegions::class);
        $dusun = $regions->createDusun($manager, 'W5-DS-'.$customer->id, 'Dusun W5');
        $rw = $regions->createRw($manager, $dusun, 'W5-RW-'.$customer->id, 'RW W5');
        $rt = $regions->createRt($manager, $rw, 'W5-RT-'.$customer->id, 'RT W5');
        $customer->customerProfile()->create(['rt_id' => $rt->id, 'address' => 'Alamat warga W5']);
        $area = $regions->createServiceArea($manager, 'Area W5 '.$customer->id, [$rt]);
        $category = WasteCategory::factory()->create(['is_active' => true]);
        $unit = WasteUnit::factory()->weight()->create(['code' => 'KG-W5-'.$customer->id, 'symbol' => 'kg']);
        $condition = WasteCondition::factory()->create(['is_active' => true]);
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create(['is_active' => true]);
        WasteMasterMutationGuard::run(fn (): array => $type->conditions()->sync([$condition->id]));

        return [$customer, $area, $type, $condition];
    }

    private function capacity(ServiceArea $area, int $maxAddresses, string $maxWeight, ?string $date = null): PickupCapacity
    {
        return PickupCapacity::query()->create(['service_area_id' => $area->id, 'service_date' => $date ?? today()->addDay()->toDateString(), 'max_addresses' => $maxAddresses, 'max_weight_kg' => $maxWeight, 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    private function requestData(User $customer, ServiceArea $area, ?string $date = null): array
    {
        return ['customer_id' => $customer->id, 'service_area_id' => $area->id, 'address' => 'Alamat penjemputan warga yang lengkap', 'selected_date' => $date ?? today()->addDay()->toDateString(), 'notes' => 'Catatan akses rumah'];
    }

    /** @param list<string> $names */
    private function grant(User $user, array $names): void
    {
        $role = Role::query()->create(['name' => 'w5-role-'.$user->id.'-'.str()->random(5), 'description' => 'W5']);
        foreach ($names as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
