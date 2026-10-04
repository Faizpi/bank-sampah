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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class W2IdentificationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_card_shows_printable_identity_controls_without_exposing_qr_token(): void
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
            ->assertSee('Siti Aminah')
            ->assertSee('CST-12345678')
            ->assertSee('CST-****78')
            ->assertSee('Simpan PNG')
            ->assertSee('data-customer-card-preview-image', false)
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
        $depositHref = route('officer.deposit-form', ['customerId' => $customer->getKey()]);

        Livewire::actingAs($officer)
            ->test(CustomerIdentification::class)
            ->set('search', 'CST-1234')
            ->call('find')
            ->assertSet('candidate.name', 'Siti Aminah')
            ->assertSet('confirmed', false)
            ->assertSee('Konfirmasi nama warga')
            ->assertDontSee('Layanan berbantuan')
            ->assertDontSeeHtml('href="'.$depositHref.'"')
            ->call('confirm')
            ->assertSet('confirmed', true)
            ->assertSee('Identitas warga terkonfirmasi')
            ->assertSeeHtml('href="'.$depositHref.'"');
    }

    public function test_officer_can_scan_a_valid_qr_and_invalid_or_out_of_scope_tokens_are_safe(): void
    {
        $officer = User::factory()->create(['name' => 'Petugas']);
        $customer = User::factory()->create(['name' => 'Siti Aminah']);
        $token = QrToken::generate();
        $profile = CustomerProfile::factory()->for($customer)->create([
            'customer_number' => 'CST-12345678',
            'qr_token_hash' => $token->hash(),
        ]);
        $this->grant($officer, 'customer.view', 'user.view', 'user.view.all');

        $component = Livewire::actingAs($officer)
            ->test(CustomerIdentification::class)
            ->call('scan', $token->value())
            ->assertSet('candidate.name', 'Siti Aminah')
            ->assertSet('confirmed', false)
            ->assertSee('Konfirmasi nama warga');

        $profile->forceFill(['qr_token_hash' => QrToken::generate()->hash()])->save();
        $component
            ->call('scan', $token->value())
            ->assertHasErrors(['token'])
            ->assertSet('candidate', null)
            ->assertSet('scannerOpen', true)
            ->call('scan', 'invalid-token')
            ->assertHasErrors(['token'])
            ->assertSet('candidate', null);

        $scopedOfficer = User::factory()->create(['name' => 'Petugas Area']);
        $this->grant($scopedOfficer, 'customer.view');

        Livewire::actingAs($scopedOfficer)
            ->test(CustomerIdentification::class)
            ->call('scan', $token->value())
            ->assertHasErrors(['token'])
            ->assertSet('candidate', null);
    }

    public function test_assisted_service_requires_confirmed_name_consent_and_private_evidence(): void
    {
        Storage::fake('media_private');
        $officer = User::factory()->create(['name' => 'Petugas']);
        $customer = User::factory()->create(['name' => 'Warga Tanpa Smartphone']);
        $token = QrToken::generate();
        CustomerProfile::factory()->for($customer)->create([
            'customer_number' => 'CST-12345678',
            'qr_token_hash' => $token->hash(),
        ]);
        $this->grant($officer, 'customer.view', 'customer.create-assisted', 'user.view', 'user.view.all');

        $component = Livewire::actingAs($officer)
            ->test(CustomerIdentification::class)
            ->call('scan', $token->value())
            ->call('recordAssistedService')
            ->assertHasErrors(['search'])
            ->call('confirm')
            ->call('recordAssistedService')
            ->assertHasErrors(['assistedConsent', 'assistedEvidence'])
            ->set('assistedConsent', true)
            ->set('assistedEvidence', UploadedFile::fake()->image('consent.jpg'))
            ->call('recordAssistedService')
            ->assertHasNoErrors()
            ->assertSet('assistedRecorded', true)
            ->assertSee('Layanan berbantuan tercatat');

        self::assertDatabaseHas('assisted_customer_services', [
            'owner_id' => $customer->id,
            'operator_id' => $officer->id,
            'consent_version' => 'assisted-service-v1',
        ]);
        self::assertNotNull($component->get('assistedEvidence'));
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
