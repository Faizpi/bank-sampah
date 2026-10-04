<?php

declare(strict_types=1);

namespace Tests\Feature\Wave8;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\MobileServices\Services\MobileDepositGuard;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Livewire\PublicSite\MobileSchedule;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class MobileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_collision_is_rejected_before_publishing_or_opening(): void
    {
        [$admin, $staff, $rt, $type] = $this->context();
        $service = app(MobileServiceService::class)->create($admin, null, $rt->id, 'Balai RT 01', '2026-08-10 09:00:00', '2026-08-10 11:00:00', 20, '', [$staff->id], [$type->id]);
        $other = app(MobileServiceService::class)->create($admin, null, $rt->id, 'Lapangan RT 02', '2026-08-10 10:00:00', '2026-08-10 12:00:00', 20, '', [$staff->id], [$type->id]);
        app(MobileServiceService::class)->transition($admin, $service, MobileServiceStatus::Published);
        $this->expectException(ValidationException::class);
        app(MobileServiceService::class)->transition($admin, $other, MobileServiceStatus::Published);
    }

    public function test_operator_can_only_see_and_open_an_assigned_service(): void
    {
        [$admin, $staff, $rt, $type] = $this->context();
        $service = app(MobileServiceService::class)->create($admin, null, $rt->id, 'Balai RT 01', '2026-08-10 09:00:00', '2026-08-10 11:00:00', 20, '', [$staff->id], [$type->id]);
        app(MobileServiceService::class)->transition($admin, $service, MobileServiceStatus::Published);
        self::assertTrue(app(MobileServiceService::class)->canOperate($staff, $service->fresh()));
        self::assertFalse(app(MobileServiceService::class)->canOperate(User::factory()->create(), $service->fresh()));
    }

    public function test_mobile_deposit_link_is_locked_idempotently_and_close_recap_is_reproducible(): void
    {
        [$admin, $staff, $rt, $type] = $this->context();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-10 10:00:00', 'Asia/Jakarta'));

        try {
            $this->grant($staff, ['deposit.create']);
            $service = app(MobileServiceService::class)->create($admin, null, $rt->id, 'Balai RT 03', '2026-08-10 09:00:00', '2026-08-10 11:00:00', 20, '', [$staff->id], [$type->id]);
            app(MobileServiceService::class)->transition($admin, $service, MobileServiceStatus::Published);
            app(MobileServiceService::class)->transition($admin, $service, MobileServiceStatus::Open);
            $customer = User::factory()->create();
            $deposit = Deposit::query()->create([
                'deposit_number' => 'DEP-MOBILE-LINK-001',
                'customer_id' => $customer->id,
                'staff_id' => $staff->id,
                'method' => 'keliling',
                'occurred_at' => now(),
                'status' => Deposit::STATUS_DRAFT,
            ]);

            $guard = app(MobileDepositGuard::class);
            $guard->attach($staff, $deposit, $service, [$type]);
            $guard->attach($staff, $deposit, $service, [$type]);
            $deposit->forceFill(['status' => Deposit::STATUS_FINAL, 'total_weight_kg' => '1.250', 'total_value' => 7_500])->save();

            self::assertSame($service->id, $deposit->fresh()->mobile_service_id);
            self::assertSame(1, $service->fresh()->served_count);
            self::assertSame(1, app(MobileServiceService::class)->recap($staff, $service)['transaction_count']);
            self::assertSame('1.250', app(MobileServiceService::class)->recap($staff, $service)['total_weight_kg']);
            self::assertSame(7_500, app(MobileServiceService::class)->recap($staff, $service)['total_value']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_public_query_excludes_expired_or_terminal_services_at_the_time_boundary(): void
    {
        [$admin, $staff, $rt, $type] = $this->context();
        $now = CarbonImmutable::parse('2026-08-10 10:00:00', 'Asia/Jakarta');
        CarbonImmutable::setTestNow($now);

        try {
            $expired = $this->mobileServiceAt($staff, $type, '2026-08-10 08:00:00', '2026-08-10 09:59:59', MobileServiceStatus::Published);
            $equalNow = $this->mobileServiceAt($staff, $type, '2026-08-10 09:00:00', '2026-08-10 10:00:00', MobileServiceStatus::Published);
            $current = $this->mobileServiceAt($staff, $type, '2026-08-10 09:00:00', '2026-08-10 11:00:00', MobileServiceStatus::Open);
            $future = $this->mobileServiceAt($staff, $type, '2026-08-10 11:00:00', '2026-08-10 12:00:00', MobileServiceStatus::Published);
            $closed = $this->mobileServiceAt($staff, $type, '2026-08-10 09:00:00', '2026-08-10 12:00:00', MobileServiceStatus::Closed);
            $cancelled = $this->mobileServiceAt($staff, $type, '2026-08-10 09:00:00', '2026-08-10 12:00:00', MobileServiceStatus::Cancelled);

            self::assertEqualsCanonicalizing(
                [$equalNow->id, $current->id, $future->id],
                app(MobileServiceService::class)->publicQuery()->pluck('id')->all(),
            );
            $publicService = app(MobileServiceService::class)->publicQuery()->whereKey($current->id)->firstOrFail();
            self::assertTrue($publicService->relationLoaded('rt'));
            self::assertTrue($publicService->relationLoaded('rw'));
            self::assertTrue($publicService->relationLoaded('wasteTypes'));
            self::assertSame([$type->name], $publicService->wasteTypes->pluck('name')->all());
            self::assertNotContains($expired->id, app(MobileServiceService::class)->publicQuery()->pluck('id')->all());
            self::assertNotContains($closed->id, app(MobileServiceService::class)->publicQuery()->pluck('id')->all());
            self::assertNotContains($cancelled->id, app(MobileServiceService::class)->publicQuery()->pluck('id')->all());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_public_schedule_renders_status_coverage_accepted_waste_types_and_notes(): void
    {
        [$admin, $staff, $rt, $type] = $this->context();
        $service = app(MobileServiceService::class)->create($admin, null, $rt->id, 'Balai RT Terbuka', now()->addHour()->toDateTimeString(), now()->addHours(2)->toDateTimeString(), 20, 'Bawa sampah yang sudah dipilah.', [$staff->id], [$type->id]);
        app(MobileServiceService::class)->transition($admin, $service, MobileServiceStatus::Published);

        Livewire::test(MobileSchedule::class)
            ->assertSee('Terjadwal')
            ->assertSee('Cakupan wilayah')
            ->assertSee($rt->name)
            ->assertSee('Sampah yang diterima')
            ->assertSee($type->name)
            ->assertSee('Bawa sampah yang sudah dipilah.');
    }

    public function test_mobile_service_acceptance_respects_end_boundary_status_and_capacity(): void
    {
        [$admin, $staff, $rt, $type] = $this->context();
        $now = CarbonImmutable::parse('2026-08-10 10:00:00', 'Asia/Jakarta');
        CarbonImmutable::setTestNow($now);

        try {
            $equalNow = $this->mobileServiceAt($staff, $type, '2026-08-10 09:00:00', '2026-08-10 10:00:00', MobileServiceStatus::Open);
            $current = $this->mobileServiceAt($staff, $type, '2026-08-10 09:00:00', '2026-08-10 11:00:00', MobileServiceStatus::Open);
            $expired = $this->mobileServiceAt($staff, $type, '2026-08-10 08:00:00', '2026-08-10 09:59:59', MobileServiceStatus::Open);
            $full = $this->mobileServiceAt($staff, $type, '2026-08-10 09:00:00', '2026-08-10 11:00:00', MobileServiceStatus::Open, 1, 1);
            $closed = $this->mobileServiceAt($staff, $type, '2026-08-10 09:00:00', '2026-08-10 11:00:00', MobileServiceStatus::Closed);
            $cancelled = $this->mobileServiceAt($staff, $type, '2026-08-10 09:00:00', '2026-08-10 11:00:00', MobileServiceStatus::Cancelled);
            $service = app(MobileServiceService::class);

            self::assertTrue($equalNow->isOpen());
            self::assertTrue($service->canAcceptDeposit($staff, $equalNow, $type->id));
            self::assertTrue($service->canAcceptDeposit($staff, $current, $type->id));
            self::assertFalse($service->canAcceptDeposit($staff, $expired, $type->id));
            self::assertFalse($service->canAcceptDeposit($staff, $full, $type->id));
            self::assertFalse($service->canAcceptDeposit($staff, $closed, $type->id));
            self::assertFalse($service->canAcceptDeposit($staff, $cancelled, $type->id));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_mobile_deposit_guard_rejects_any_unaccepted_waste_type_without_linking(): void
    {
        [$admin, $staff, $rt, $accepted] = $this->context();
        $this->grant($staff, ['deposit.create']);
        $rejected = WasteType::factory()->create();
        $service = app(MobileServiceService::class)->create($admin, null, $rt->id, 'Balai RT 04', now()->subHour()->toDateTimeString(), now()->addHour()->toDateTimeString(), 20, '', [$staff->id], [$accepted->id]);
        app(MobileServiceService::class)->transition($admin, $service, MobileServiceStatus::Published);
        app(MobileServiceService::class)->transition($admin, $service, MobileServiceStatus::Open);
        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-MOBILE-MULTI-REJECT-001',
            'customer_id' => User::factory()->create()->id,
            'staff_id' => $staff->id,
            'method' => 'keliling',
            'occurred_at' => now(),
            'status' => Deposit::STATUS_DRAFT,
        ]);

        $this->expectException(ValidationException::class);
        try {
            app(MobileDepositGuard::class)->attach($staff, $deposit, $service, [$accepted, $rejected]);
        } finally {
            self::assertNull($deposit->fresh()->mobile_service_id);
            self::assertSame(0, $service->fresh()->served_count);
        }
    }

    public function test_mobile_deposit_guard_rejects_expired_and_full_services(): void
    {
        [$admin, $staff, $rt, $type] = $this->context();
        $this->grant($staff, ['deposit.create']);
        $now = CarbonImmutable::parse('2026-08-10 10:00:00', 'Asia/Jakarta');
        CarbonImmutable::setTestNow($now);

        try {
            $expired = $this->mobileServiceAt($staff, $type, '2026-08-10 08:00:00', '2026-08-10 09:59:59', MobileServiceStatus::Open);
            $full = $this->mobileServiceAt($staff, $type, '2026-08-10 09:00:00', '2026-08-10 11:00:00', MobileServiceStatus::Open, 1, 1);
            $guard = app(MobileDepositGuard::class);

            foreach ([$expired, $full] as $service) {
                $deposit = Deposit::query()->create([
                    'deposit_number' => 'DEP-MOBILE-REJECT-'.str()->upper(str()->random(8)),
                    'customer_id' => User::factory()->create()->id,
                    'staff_id' => $staff->id,
                    'method' => 'keliling',
                    'occurred_at' => now(),
                    'status' => Deposit::STATUS_DRAFT,
                ]);

                try {
                    $guard->attach($staff, $deposit, $service, [$type]);
                    self::fail('Expected the mobile deposit guard to reject the service.');
                } catch (ValidationException) {
                    self::assertNull($deposit->fresh()->mobile_service_id);
                }
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function mobileServiceAt(User $staff, WasteType $type, string $startsAt, string $endsAt, MobileServiceStatus $status, int $capacity = 20, int $servedCount = 0): MobileService
    {
        $service = MobileService::query()->create([
            'service_number' => 'MOB-TIME-'.str()->upper(str()->random(10)),
            'point' => 'Titik layanan waktu',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
            'capacity' => $capacity,
            'served_count' => $servedCount,
            'created_by' => $staff->id,
        ]);
        $service->staff()->attach($staff->id);
        $service->wasteTypes()->attach($type->id);

        return $service->fresh();
    }

    private function context(): array
    {
        $admin = $this->userWith('mobile-service.manage', 'mobile-service.operate', 'waste.manage');
        $staff = $this->userWith('mobile-service.operate');
        $dusun = Dusun::query()->create(['code' => 'W8-DS-'.uniqid(), 'name' => 'W8 Dusun', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'W8-RW-'.uniqid(), 'name' => 'W8 RW', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'W8-RT-'.uniqid(), 'name' => 'W8 RT', 'is_active' => true]);
        $category = app(ManageWasteMaster::class)->createCategory($admin, 'W8-CAT-'.uniqid(), 'W8');
        $unit = app(ManageWasteMaster::class)->createUnit($admin, 'W8-UNIT-'.uniqid(), 'Kilogram', 'kg', WasteUnit::CLASSIFICATION_WEIGHT, '1.000000');
        $condition = app(ManageWasteMaster::class)->createCondition($admin, 'W8-COND-'.uniqid(), 'Bersih', null);
        $type = app(ManageWasteMaster::class)->createType($admin, $category, $unit, 'W8-TYPE-'.uniqid(), 'Plastik', null, 0, true, true, [$condition->id]);
        $staff->staffProfile()->create(['staff_number' => 'W8-STF-'.uniqid(), 'service_area_id' => null, 'active_from' => today(), 'active_to' => null]);

        return [$admin, $staff, $rt, $type];
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $this->grant($user, $permissions);

        return $user;
    }

    /** @param list<string> $permissions */
    private function grant(User $user, array $permissions): void
    {
        $role = Role::query()->create(['name' => 'w8-mobile-'.uniqid(), 'description' => 'W8 mobile test']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
