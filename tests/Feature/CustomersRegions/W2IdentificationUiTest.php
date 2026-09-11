<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
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

    public function test_assigned_mobile_service_context_identifies_its_active_rt_customer_by_number_and_qr_only_for_assigned_staff(): void
    {
        $assigned = User::factory()->create(['name' => 'Petugas Keliling']);
        $other = User::factory()->create(['name' => 'Petugas Lain']);
        $customer = User::factory()->create(['name' => 'Warga Keliling']);
        $token = QrToken::generate();
        $dusun = Dusun::query()->create(['code' => 'DS-MOB-ID', 'name' => 'Dusun Mobile', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-MOB-ID', 'name' => 'RW Mobile', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-MOB-ID', 'name' => 'RT Mobile', 'is_active' => true]);
        CustomerProfile::factory()->for($customer)->create([
            'customer_number' => 'CST-24681357',
            'rt_id' => $rt->id,
            'qr_token_hash' => $token->hash(),
        ]);
        $service = MobileService::query()->create([
            'service_number' => 'MOB-IDENTIFICATION-001',
            'rt_id' => $rt->id,
            'point' => 'Balai RT Mobile',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => MobileServiceStatus::Open,
            'capacity' => 20,
            'served_count' => 0,
            'created_by' => $assigned->id,
        ]);
        $service->staff()->attach($assigned->id);
        $this->grant($assigned, 'customer.view', 'user.view', 'mobile-service.operate');
        $this->grant($other, 'customer.view', 'user.view', 'mobile-service.operate');

        $component = Livewire::actingAs($assigned)
            ->withQueryParams(['mobileServiceId' => $service->id])
            ->test(CustomerIdentification::class)
            ->assertSet('mobileServiceId', $service->id)
            ->assertSeeInOrder(['Pilih konteks setoran', 'Pindai dengan kamera', 'Cari dengan nomor nasabah'])
            ->assertSee($service->point)
            ->set('mobileServiceId', $service->id)
            ->set('search', 'CST-24681357')
            ->call('find')
            ->assertSet('candidate.name', 'Warga Keliling')
            ->call('confirm')
            ->assertSet('confirmed', true)
            ->set('mobileServiceId', null)
            ->assertSet('candidate', null)
            ->assertSet('confirmed', false);

        $component
            ->set('mobileServiceId', $service->id)
            ->call('scan', $token->value())
            ->assertSet('candidate.name', 'Warga Keliling');

        Livewire::actingAs($other)
            ->test(CustomerIdentification::class)
            ->assertDontSee($service->point)
            ->set('mobileServiceId', $service->id)
            ->set('search', 'CST-24681357')
            ->call('find')
            ->assertSet('candidate', null)
            ->call('scan', $token->value())
            ->assertHasErrors(['token'])
            ->assertSet('candidate', null);
    }

    public function test_assigned_actor_without_mobile_service_permission_cannot_list_or_inject_mobile_context(): void
    {
        $assigned = User::factory()->create(['name' => 'Petugas Tanpa Izin Keliling']);
        $customer = User::factory()->create(['name' => 'Warga Mobile Terbatas']);
        $token = QrToken::generate();
        $dusun = Dusun::query()->create(['code' => 'DS-MOB-DENY', 'name' => 'Dusun Mobile Deny', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-MOB-DENY', 'name' => 'RW Mobile Deny', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-MOB-DENY', 'name' => 'RT Mobile Deny', 'is_active' => true]);
        CustomerProfile::factory()->for($customer)->create([
            'customer_number' => 'CST-11223344',
            'rt_id' => $rt->id,
            'qr_token_hash' => $token->hash(),
        ]);
        $service = MobileService::query()->create([
            'service_number' => 'MOB-IDENTIFICATION-DENY',
            'rt_id' => $rt->id,
            'point' => 'Titik Mobile Terbatas',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => MobileServiceStatus::Open,
            'capacity' => 20,
            'served_count' => 0,
            'created_by' => $assigned->id,
        ]);
        $service->staff()->attach($assigned->id);
        $this->grant($assigned, 'customer.view', 'user.view');

        Livewire::actingAs($assigned)
            ->test(CustomerIdentification::class)
            ->assertDontSee($service->point)
            ->set('mobileServiceId', $service->id)
            ->set('search', 'CST-11223344')
            ->call('find')
            ->assertSet('candidate', null)
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
