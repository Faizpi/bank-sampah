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
use App\Domain\MobileServices\Services\MobileDepositGuard;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
        $guard->attach($staff, $deposit, $service, $type);
        $guard->attach($staff, $deposit, $service, $type);
        $deposit->forceFill(['status' => Deposit::STATUS_FINAL, 'total_weight_kg' => '1.250', 'total_value' => 7_500])->save();

        self::assertSame($service->id, $deposit->fresh()->mobile_service_id);
        self::assertSame(1, $service->fresh()->served_count);
        self::assertSame(1, app(MobileServiceService::class)->recap($staff, $service)['transaction_count']);
        self::assertSame('1.250', app(MobileServiceService::class)->recap($staff, $service)['total_weight_kg']);
        self::assertSame(7_500, app(MobileServiceService::class)->recap($staff, $service)['total_value']);
    }

    /** @return array{0: User, 1: User, 2: Rt, 3: WasteType} */
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
