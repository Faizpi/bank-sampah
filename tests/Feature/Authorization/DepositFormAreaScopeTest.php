<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Livewire\Officer\DepositForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class DepositFormAreaScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_cannot_load_deposit_form_for_customer_outside_service_area(): void
    {
        [$staff] = $this->staffInArea('DEP-OUTSIDE');
        $outside = $this->customerInArea($this->createRt('DEP-FOREIGN'));
        $this->grant($staff, 'deposit.create', 'user.view', 'user.view.area', 'customer.view');

        $this->actingAs($staff->fresh());

        $this->get(route('officer.deposit-form', $outside->id))->assertForbidden();

        Livewire::test(DepositForm::class, ['customerId' => $outside->id])
            ->assertForbidden();
    }

    public function test_officer_can_load_deposit_form_for_customer_inside_service_area(): void
    {
        [$staff, $allowedRt] = $this->staffInArea('DEP-INSIDE');
        $inside = $this->customerInArea($allowedRt);
        $this->grant($staff, 'deposit.create', 'user.view', 'user.view.area', 'customer.view');

        Livewire::actingAs($staff->fresh())
            ->test(DepositForm::class, ['customerId' => $inside->id])
            ->assertOk()
            ->assertSee($inside->name);
    }

    public function test_user_view_all_scope_can_load_deposit_form_for_any_customer(): void
    {
        [$staff] = $this->staffInArea('DEP-GLOBAL');
        $outside = $this->customerInArea($this->createRt('DEP-GLOBAL-FOREIGN'));
        $this->grant($staff, 'deposit.create', 'user.view', 'user.view.all', 'customer.view');

        Livewire::actingAs($staff->fresh())
            ->test(DepositForm::class, ['customerId' => $outside->id])
            ->assertOk();
    }

    /** @return array{0: User, 1: Rt} */
    private function staffInArea(string $prefix): array
    {
        $area = ServiceArea::query()->create(['name' => 'Area '.$prefix, 'is_active' => true]);
        $rt = $this->createRt($prefix);
        RegionMutationGuard::run(fn () => $area->rts()->sync([$rt->id]));
        $staff = User::factory()->create();
        StaffProfile::query()->create([
            'user_id' => $staff->id,
            'staff_number' => 'STF-'.str_pad((string) $staff->id, 8, '0', STR_PAD_LEFT),
            'service_area_id' => $area->id,
            'active_from' => today()->subDay(),
            'active_to' => today()->addDay(),
        ]);

        return [$staff, $rt];
    }

    private function customerInArea(Rt $rt): User
    {
        $customer = User::factory()->create();
        $customer->customerProfile()->create(['rt_id' => $rt->id, 'address' => 'Alamat pengujian']);

        return $customer;
    }

    private function createRt(string $code): Rt
    {
        $dusun = Dusun::query()->create(['code' => $code.'-D', 'name' => $code.' Dusun']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => $code.'-W', 'name' => $code.' RW']);

        return Rt::query()->create(['rw_id' => $rw->id, 'code' => $code, 'name' => $code, 'is_active' => true]);
    }

    private function grant(User $user, string ...$permissionNames): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'deposit-area-'.$user->id, 'description' => 'Deposit area scope test']);
        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->syncWithoutDetaching($permission);
        }
        $user->roles()->attach($role);
    }
}
