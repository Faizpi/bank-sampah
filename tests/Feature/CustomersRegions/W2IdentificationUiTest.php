<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Livewire\Citizen\CustomerCard;
use App\Livewire\Officer\CustomerIdentification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class W2IdentificationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_card_shows_masked_reference_and_fallback_without_exposing_qr_token(): void
    {
        $citizen = User::factory()->create(['name' => 'Siti Aminah']);
        $profile = CustomerProfile::factory()->for($citizen)->create([
            'customer_number' => 'CST-12345678',
            'qr_token_hash' => hash('sha256', QrToken::generate()->value()),
        ]);
        $this->grant($citizen, 'customer.view');

        Livewire::actingAs($citizen)
            ->test(CustomerCard::class)
            ->assertSee('Kartu Digital Nasabah')
            ->assertSee('CST-****78')
            ->assertSee('Nomor alternatif')
            ->assertSee('QR Nasabah Warga')
            ->assertDontSee($profile->qr_token_hash)
            ->assertDontSee('token');
    }

    public function test_officer_identification_requires_name_confirmation_after_search_and_scan(): void
    {
        $officer = User::factory()->create(['name' => 'Petugas']);
        $customer = User::factory()->create(['name' => 'Siti Aminah']);
        CustomerProfile::factory()->for($customer)->create([
            'customer_number' => 'CST-12345678',
            'qr_token_hash' => hash('sha256', QrToken::generate()->value()),
        ]);
        $this->grant($officer, 'customer.view', 'user.view', 'user.view.all');

        Livewire::actingAs($officer)
            ->test(CustomerIdentification::class)
            ->set('search', 'CST-1234')
            ->call('find')
            ->assertSet('candidate.name', 'Siti Aminah')
            ->assertSet('confirmed', false)
            ->assertSee('Konfirmasi nama warga')
            ->call('confirm')
            ->assertSet('confirmed', true)
            ->assertSee('Identitas warga terkonfirmasi')
            ->assertSeeHtml('href="'.route('officer.deposit-form', ['customerId' => $customer->getKey()]).'"');
    }

    public function test_officer_identification_has_safe_empty_and_error_states(): void
    {
        $officer = User::factory()->create();
        $this->grant($officer, 'customer.view', 'user.view', 'user.view.all');

        Livewire::actingAs($officer)
            ->test(CustomerIdentification::class)
            ->call('find')
            ->assertHasErrors(['search'])
            ->set('search', 'CST-0000')
            ->call('find')
            ->assertSet('candidate', null)
            ->assertSee('Nasabah tidak ditemukan');
    }

    private function grant(User $user, string ...$permissions): void
    {
        $role = Role::query()->create(['name' => 'w2-ui-'.fake()->unique()->numerify('####'), 'description' => 'W2 UI test role']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => "W2 {$permissionName}"]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
