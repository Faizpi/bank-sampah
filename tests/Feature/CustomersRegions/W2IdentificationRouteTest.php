<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class W2IdentificationRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_card_route_requires_customer_view_and_renders_for_authorized_citizen(): void
    {
        $citizen = User::factory()->create();

        $this->actingAs($citizen)->get(route('citizen.customer-card'))->assertForbidden();

        $this->grant($citizen, 'customer.view');
        $this->actingAs($citizen)->get(route('citizen.customer-card'))->assertOk()->assertSee('Kartu nasabah');
    }

    public function test_officer_identification_route_requires_customer_view(): void
    {
        $officer = User::factory()->create();

        $this->actingAs($officer)->get(route('officer.customer-identification'))->assertForbidden();

        $this->grant($officer, 'customer.view');
        $this->actingAs($officer)->get(route('officer.customer-identification'))->assertOk()->assertSee('Identifikasi warga');
    }

    private function grant(User $user, string ...$permissions): void
    {
        $role = Role::query()->create(['name' => 'w2-route-'.fake()->unique()->numerify('####'), 'description' => 'W2 route test role']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => "W2 {$permissionName}"]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
