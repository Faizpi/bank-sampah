<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\RoleFixtures;
use Tests\TestCase;

final class W2IdentificationRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_card_route_requires_customer_view_and_renders_for_authorized_citizen(): void
    {
        $citizen = User::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'petugas']))->get(route('citizen.customer-card'))->assertForbidden();

        $this->grant($citizen, 'customer.view');
        $this->actingAs($citizen)->get(route('citizen.customer-card'))->assertOk()->assertSee('Kartu nasabah');
    }

    public function test_officer_identification_route_requires_customer_view(): void
    {
        $officer = User::factory()->create();

        $this->actingAs($officer)->get(route('officer.customer-identification'))->assertForbidden();

        $this->grant($officer, 'customer.view', 'user.view.area');
        $this->actingAs($officer)
            ->get(route('officer.customer-identification'))
            ->assertOk()
            ->assertSee('Pilih warga untuk setoran')
            ->assertHeader('Permissions-Policy', 'accelerometer=(), autoplay=(), camera=(self), display-capture=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
    }

    private function grant(User $user, string ...$permissions): void
    {
        $user = RoleFixtures::assign($user, $permissions);
    }
}
