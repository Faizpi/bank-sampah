<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Queries\SearchCustomers;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_area_operator_searches_only_active_customers_in_the_effective_service_area(): void
    {
        [$insideRt, $outsideRt] = $this->regionalFixture();
        $area = ServiceArea::query()->create(['name' => 'Area Utama']);
        app(ManageRegions::class)->updateServiceArea($this->regionManager(), $area, 'Area Utama', [$insideRt]);

        $operator = User::factory()->create(['name' => 'Petugas Area']);
        StaffProfile::factory()->for($operator)->create(['service_area_id' => $area->id]);
        $this->grant($operator, 'customer.view', 'user.view', 'user.view.area');

        $inside = User::factory()->create(['name' => 'Siti Aminah']);
        CustomerProfile::factory()->for($inside)->create(['customer_number' => 'CST-12345678', 'rt_id' => $insideRt->id]);
        $outside = User::factory()->create(['name' => 'Sari Amanah']);
        CustomerProfile::factory()->for($outside)->create(['customer_number' => 'CST-87654321', 'rt_id' => $outsideRt->id]);

        $results = app(SearchCustomers::class)->search($operator->fresh(), 'CST-');

        self::assertCount(1, $results);
        self::assertSame($inside->id, $results[0]->userId);
        self::assertSame('CST-****78', $results[0]->maskedNumber());
    }

    public function test_area_operator_finds_an_active_customer_by_number_through_an_effective_rt_assignment(): void
    {
        [$insideRt, $outsideRt] = $this->regionalFixture();
        $insideArea = ServiceArea::query()->create(['name' => 'Area Penugasan Efektif']);
        $outsideArea = ServiceArea::query()->create(['name' => 'Area Profil Lama']);
        app(ManageRegions::class)->updateServiceArea($this->regionManager(), $insideArea, $insideArea->name, [$insideRt]);
        app(ManageRegions::class)->updateServiceArea($this->regionManager(), $outsideArea, $outsideArea->name, [$outsideRt]);

        $operator = User::factory()->create(['name' => 'Petugas Multi Area']);
        StaffProfile::factory()->for($operator)->create(['service_area_id' => $outsideArea->id]);
        StaffServiceArea::query()->create([
            'staff_profile_user_id' => $operator->id,
            'service_area_id' => $insideArea->id,
            'active_from' => today()->subDay(),
            'active_to' => today()->addDay(),
        ]);
        $this->grant($operator, 'customer.view', 'user.view', 'user.view.area');

        $inside = User::factory()->create(['name' => 'Warga Penugasan Efektif']);
        CustomerProfile::factory()->for($inside)->create(['customer_number' => 'CST-90757568', 'rt_id' => $insideRt->id]);

        $results = app(SearchCustomers::class)->search($operator->fresh(), 'CST-90757568');

        self::assertCount(1, $results);
        self::assertSame($inside->id, $results[0]->userId);
    }

    public function test_area_visibility_rejects_inactive_ancestors_and_out_of_range_assignments(): void
    {
        [$rt] = $this->regionalFixture();
        $area = ServiceArea::query()->create(['name' => 'Area Batas Aktif']);
        app(ManageRegions::class)->updateServiceArea($this->regionManager(), $area, $area->name, [$rt]);
        $operator = User::factory()->create();
        StaffProfile::factory()->for($operator)->create(['service_area_id' => null]);
        StaffServiceArea::query()->create([
            'staff_profile_user_id' => $operator->id,
            'service_area_id' => $area->id,
            'active_from' => today()->subDays(2),
            'active_to' => today()->subDay(),
        ]);
        $this->grant($operator, 'customer.view', 'user.view', 'user.view.area');
        $customer = User::factory()->create(['name' => 'Warga Batas Aktif']);
        CustomerProfile::factory()->for($customer)->create(['customer_number' => 'CST-13572468', 'rt_id' => $rt->id]);

        self::assertSame([], app(SearchCustomers::class)->search($operator->fresh(), 'CST-13572468'));

        $operator->staffProfile->serviceAreas()->update(['active_to' => today()->addDay()]);
        RegionMutationGuard::run(fn () => $rt->rw()->update(['is_active' => false]));

        self::assertSame([], app(SearchCustomers::class)->search($operator->fresh(), 'CST-13572468'));
    }

    public function test_search_excludes_pending_and_inactive_customers_and_rejects_unprivileged_actors(): void
    {
        $rt = $this->regionalFixture()[0];
        $actor = User::factory()->create();
        $this->grant($actor, 'customer.view', 'user.view', 'user.view.all');

        $active = User::factory()->create(['name' => 'Aktif Warga']);
        CustomerProfile::factory()->for($active)->create(['customer_number' => 'CST-12345678', 'rt_id' => $rt->id]);
        $pending = User::factory()->pendingVerification()->create(['name' => 'Menunggu Warga']);
        CustomerProfile::factory()->for($pending)->create(['customer_number' => 'CST-12345679', 'rt_id' => $rt->id]);
        $inactive = User::factory()->inactive()->create(['name' => 'Nonaktif Warga']);
        CustomerProfile::factory()->for($inactive)->create(['customer_number' => 'CST-12345680', 'rt_id' => $rt->id]);

        $results = app(SearchCustomers::class)->search($actor->fresh(), 'CST-');

        self::assertCount(1, $results);
        self::assertSame($active->id, $results[0]->userId);

        $withoutPermission = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(SearchCustomers::class)->search($withoutPermission->fresh(), 'CST-');
    }

    public function test_search_normalizes_bounded_input_and_caps_result_count(): void
    {
        $rt = $this->regionalFixture()[0];
        $actor = User::factory()->create();
        $this->grant($actor, 'customer.view', 'user.view', 'user.view.all');

        foreach (range(1, 12) as $index) {
            $customer = User::factory()->create(['name' => "Warga {$index}"]);
            CustomerProfile::factory()->for($customer)->create([
                'customer_number' => sprintf('CST-%08d', $index),
                'rt_id' => $rt->id,
            ]);
        }

        $results = app(SearchCustomers::class)->search($actor->fresh(), '  CST-  ', 100);

        self::assertCount(12, $results);

        $this->expectException(ValidationException::class);
        app(SearchCustomers::class)->search($actor->fresh(), str_repeat('x', 121));
    }

    /** @return array{Rt, Rt} */
    private function regionalFixture(): array
    {
        $dusun = Dusun::query()->create(['code' => 'DS-01', 'name' => 'Dusun Satu']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-01', 'name' => 'RW Satu']);
        $inside = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-01', 'name' => 'RT Satu']);
        $outside = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-02', 'name' => 'RT Dua']);

        return [$inside, $outside];
    }

    private function regionManager(): User
    {
        $manager = User::factory()->create();
        $this->grant($manager, 'region.manage');

        return $manager;
    }

    private function grant(User $user, string ...$permissions): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'w2-search-role'], ['description' => 'W2 search test role']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => "W2 {$permissionName}"]);
            $role->permissions()->syncWithoutDetaching($permission);
        }
        $user->roles()->syncWithoutDetaching($role);
    }
}
