<?php

declare(strict_types=1);

namespace Tests\Feature\Pickups;

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class PickupRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_request_and_detail_routes_require_permission_and_owner_scope(): void
    {
        [$customer, $area, $type] = $this->context();
        $this->actingAs($customer)->get(route('citizen.pickup.create'))->assertForbidden();
        $this->grant($customer, 'pickup.request', 'pickup.view');
        $this->capacity($area);

        $this->actingAs($customer)->get(route('citizen.pickup.create'))->assertOk()->assertSee('Ajukan penjemputan');
        $pickup = app(PickupService::class)->submit($customer, ['service_area_id' => $area->id, 'address' => 'Alamat warga route test', 'selected_date' => today()->addDay()->toDateString()], [['waste_type_id' => $type->id, 'estimated_quantity' => 1]], [UploadedFile::fake()->image('route.png')], 'w5-route-owner-0001');
        $other = User::factory()->create();
        $this->grant($other, 'pickup.view');

        $this->actingAs($customer)->get(route('citizen.pickup.show', $pickup))->assertOk()->assertSee($pickup->request_number);
        $this->actingAs($other)->get(route('citizen.pickup.show', $pickup))->assertNotFound();
    }

    public function test_officer_task_route_and_private_media_route_fail_closed_for_unassigned_user(): void
    {
        [$customer, $area, $type] = $this->context();
        $customer->forceFill(['status' => UserStatus::Active])->save();
        $this->grant($customer, 'pickup.request', 'pickup.view');
        $this->capacity($area);
        $pickup = app(PickupService::class)->submit($customer, ['service_area_id' => $area->id, 'address' => 'Alamat warga route test', 'selected_date' => today()->addDay()->toDateString()], [['waste_type_id' => $type->id, 'estimated_quantity' => 1]], [UploadedFile::fake()->image('route.png')], 'w5-route-media-0001');
        $staff = User::factory()->create();
        $this->grant($staff, 'pickup.execute', 'pickup.view');
        $media = $pickup->media()->firstOrFail();

        $this->actingAs($staff)->get(route('officer.pickup.task', $pickup))->assertNotFound();
        $this->actingAs($staff)->get(route('pickup.media', $media))->assertNotFound();
    }

    /** @return array{User, ServiceArea, WasteType} */
    private function context(): array
    {
        $customer = User::factory()->create(['status' => UserStatus::Active]);
        $manager = User::factory()->create();
        $this->grant($manager, 'region.manage');
        $regions = app(ManageRegions::class);
        $dusun = $regions->createDusun($manager, 'W5-RT-DS-'.$customer->id, 'Dusun Route');
        $rw = $regions->createRw($manager, $dusun, 'W5-RT-RW-'.$customer->id, 'RW Route');
        $rt = $regions->createRt($manager, $rw, 'W5-RT-RT-'.$customer->id, 'RT Route');
        $customer->customerProfile()->create(['rt_id' => $rt->id, 'address' => 'Alamat profile route']);
        $area = $regions->createServiceArea($manager, 'Area Route '.$customer->id, [$rt]);
        $category = WasteCategory::factory()->create(['is_active' => true]);
        $unit = WasteUnit::factory()->weight()->create(['code' => 'KG-RT-'.$customer->id, 'symbol' => 'kg']);
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create(['is_active' => true]);
        $condition = WasteCondition::factory()->create(['is_active' => true]);
        WasteMasterMutationGuard::run(fn (): array => $type->conditions()->sync([$condition->id]));

        return [$customer, $area, $type];
    }

    private function capacity(ServiceArea $area): PickupCapacity
    {
        return PickupCapacity::query()->create(['service_area_id' => $area->id, 'service_date' => today()->addDay(), 'max_addresses' => 3, 'max_weight_kg' => '10.000', 'is_active' => true]);
    }

    private function grant(User $user, string ...$permissions): void
    {
        $role = Role::query()->create(['name' => 'w5-route-role-'.$user->id.'-'.str()->random(5), 'description' => 'W5 route']);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
