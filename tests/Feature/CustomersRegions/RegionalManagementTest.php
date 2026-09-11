<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Models\User;
use App\Policies\DusunPolicy;
use App\Policies\RtPolicy;
use App\Policies\RwPolicy;
use App\Policies\ServiceAreaPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RegionalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_region_managers_may_manage_each_region_record(): void
    {
        $manager = User::factory()->create();
        $observer = User::factory()->create();
        $dusun = Dusun::query()->create(['code' => 'DS-01', 'name' => 'Dusun Satu']);
        $this->grant($manager, 'region.manage');

        self::assertTrue($manager->can('viewAny', Dusun::class));
        self::assertTrue($manager->can('update', $dusun));
        self::assertTrue($manager->can('deactivate', $dusun));
        self::assertFalse($observer->can('viewAny', Dusun::class));
        self::assertFalse($observer->can('update', $dusun));
        self::assertFalse($observer->can('deactivate', $dusun));
    }

    public function test_rejects_rw_or_rt_under_an_inactive_parent(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, 'region.manage');
        $inactiveDusun = Dusun::query()->create(['code' => 'DS-01', 'name' => 'Dusun Satu', 'is_active' => false]);
        $inactiveRw = Rw::query()->create(['dusun_id' => $inactiveDusun->id, 'code' => 'RW-01', 'name' => 'RW Satu', 'is_active' => false]);

        $manager = app(ManageRegions::class);

        try {
            $manager->createRw($actor, $inactiveDusun, 'RW-02', 'RW Dua');
            self::fail('An inactive dusun must not accept a new RW.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('dusun_id', $exception->errors());
        }

        try {
            $manager->createRt($actor, $inactiveRw, 'RT-01', 'RT Satu');
            self::fail('An inactive RW must not accept a new RT.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('rw_id', $exception->errors());
        }
    }

    public function test_deactivation_preserves_referenced_history_and_models_reject_physical_deletion(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, 'region.manage');
        $dusun = Dusun::query()->create(['code' => 'DS-01', 'name' => 'Dusun Satu']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-01', 'name' => 'RW Satu']);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-01', 'name' => 'RT Satu']);
        $area = app(ManageRegions::class)->createServiceArea($actor, 'Area Utama', [$rt]);

        app(ManageRegions::class)->deactivate($actor, $rt);

        self::assertFalse($rt->fresh()->is_active);
        self::assertDatabaseHas('rt', ['id' => $rt->id, 'is_active' => false]);
        self::assertDatabaseHas('service_area_rt', ['service_area_id' => $area->id, 'rt_id' => $rt->id]);

        foreach ([$dusun, $rw, $rt, $area] as $region) {
            try {
                $region->delete();
                self::fail('Regional records must never be physically deleted.');
            } catch (\LogicException) {
            }
        }
    }

    public function test_service_rejects_inactive_parent_updates_and_invalid_service_area_assignments(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, 'region.manage');
        $activeDusun = Dusun::query()->create(['code' => 'DS-01', 'name' => 'Dusun Satu']);
        $inactiveDusun = Dusun::query()->create(['code' => 'DS-02', 'name' => 'Dusun Dua', 'is_active' => false]);
        $rw = Rw::query()->create(['dusun_id' => $activeDusun->id, 'code' => 'RW-01', 'name' => 'RW Satu']);
        $inactiveRw = Rw::query()->create(['dusun_id' => $activeDusun->id, 'code' => 'RW-02', 'name' => 'RW Dua', 'is_active' => false]);
        $rt = Rt::query()->create(['rw_id' => $inactiveRw->id, 'code' => 'RT-01', 'name' => 'RT Satu']);
        $area = ServiceArea::query()->create(['name' => 'Area Utama']);
        $manager = app(ManageRegions::class);

        $this->assertValidationError('dusun_id', fn () => $manager->updateRw($actor, $rw, $inactiveDusun, 'RW-01', 'RW Satu'));
        $this->assertValidationError('rts', fn () => $manager->updateServiceArea($actor, $area, 'Area Utama', [$rt]));
    }

    public function test_models_reject_direct_and_mass_assignment_mutation_paths(): void
    {
        $dusun = Dusun::query()->create(['code' => 'DS-01', 'name' => 'Dusun Satu']);

        $this->expectException(\LogicException::class);
        $dusun->update(['name' => 'Bypass']);
    }

    private function assertValidationError(string $field, callable $callback): void
    {
        try {
            $callback();
            self::fail("Expected a validation error for {$field}.");
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_policies_are_explicitly_registered_and_unauthorized_service_calls_are_denied(): void
    {
        $actor = User::factory()->create();

        self::assertSame(DusunPolicy::class, Gate::getPolicyFor(Dusun::class)::class);
        self::assertSame(RwPolicy::class, Gate::getPolicyFor(Rw::class)::class);
        self::assertSame(RtPolicy::class, Gate::getPolicyFor(Rt::class)::class);
        self::assertSame(ServiceAreaPolicy::class, Gate::getPolicyFor(ServiceArea::class)::class);

        $this->expectException(AuthorizationException::class);
        app(ManageRegions::class)->createDusun($actor, 'DS-01', 'Dusun Satu');
    }

    public function test_query_mutation_and_service_area_relation_bypasses_are_denied(): void
    {
        $dusun = Dusun::query()->create(['code' => 'DS-01', 'name' => 'Dusun Satu']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-01', 'name' => 'RW Satu']);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-01', 'name' => 'RT Satu']);
        $area = ServiceArea::query()->create(['name' => 'Area Utama']);

        foreach ([
            fn () => Dusun::query()->whereKey($dusun)->update(['is_active' => false]),
            fn () => Dusun::query()->whereKey($dusun)->delete(),
            fn () => $area->rts()->attach($rt),
            fn () => $area->rts()->sync([$rt->id]),
            fn () => $area->rts()->detach($rt),
            fn () => $area->rts()->updateExistingPivot($rt->id, ['rt_id' => $rt->id]),
            fn () => $rt->serviceAreas()->attach($area),
            fn () => $rt->serviceAreas()->sync([$area->id]),
            fn () => $rt->serviceAreas()->detach($area),
            fn () => $rt->serviceAreas()->updateExistingPivot($area->id, ['service_area_id' => $area->id]),
        ] as $bypass) {
            try {
                $bypass();
                self::fail('Regional mutation bypass must be denied.');
            } catch (\LogicException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function grant(User $user, string $permissionName): void
    {
        $role = Role::query()->create(['name' => 'regional-manager', 'description' => 'Regional manager']);
        $permission = Permission::query()->create(['name' => $permissionName, 'description' => 'Manage regions']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
    }
}
