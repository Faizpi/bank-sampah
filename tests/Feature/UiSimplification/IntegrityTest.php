<?php

declare(strict_types=1);

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\Deposits\Actions\RotateDepositVerificationToken;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Domain\Programs\Services\TargetService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Filament\Resources\MobileServices\Models\MobileServices\Pages\ManageMobileServices;
use App\Filament\Resources\Programs\Models\CollectionTargets\Pages\ManageCollectionTargets;
use App\Livewire\Officer\CustomerIdentification;
use App\Livewire\Officer\MobileServiceTasks;
use App\Livewire\PublicSite\DepositVerification;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Fixtures\WorkflowFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->officer = User::where('username', 'petugas')->firstOrFail();
    $this->citizen = User::where('username', 'warga')->firstOrFail();
    $this->type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
    $this->rt = Rt::firstOrFail();
    Filament::setCurrentPanel(Filament::getPanel('backoffice'));
});

it('preserves every saved mobile schedule field when opening and saving an edit', function (): void {
    $service = app(MobileServiceService::class)->create($this->admin, $this->rt->rw_id, $this->rt->id, 'Lokasi tersimpan', now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 20, 'Catatan tersimpan', [$this->officer->id], [$this->type->id]);
    $this->actingAs($this->admin);
    Livewire::test(ManageMobileServices::class)->mountTableAction('edit', $service)
        ->assertTableActionDataSet(['point' => 'Lokasi tersimpan', 'capacity' => 20, 'notes' => 'Catatan tersimpan', 'rw_id' => $this->rt->rw_id, 'rt_id' => $this->rt->id])
        ->callMountedAction()->assertHasNoErrors();
    expect($service->fresh()->point)->toBe('Lokasi tersimpan')
        ->and($service->fresh()->notes)->toBe('Catatan tersimpan')
        ->and($service->fresh()->staff()->count())->toBe(1);
});

it('preserves target scopes when an admin saves without changing them', function (): void {
    $target = app(TargetService::class)->create($this->admin, 'Target wilayah', 'Pertahankan lingkup target', today()->toDateString(), today()->addMonth()->toDateString(), '100.000', true, [['rt_id' => $this->rt->id]]);
    $this->actingAs($this->admin);
    Livewire::test(ManageCollectionTargets::class)->mountTableAction('edit', $target)->callMountedAction()->assertHasNoErrors();
    expect($target->scopes()->count())->toBe(1)->and($target->scopes()->first()->rt_id)->toBe($this->rt->id);
});

it('lets an officer select an active assigned mobile context but not forge the locked context', function (): void {
    $service = app(MobileServiceService::class)->create($this->admin, $this->rt->rw_id, $this->rt->id, 'Titik aktif', now()->subHour()->toDateTimeString(), now()->addHour()->toDateTimeString(), 20, '', [$this->officer->id], [$this->type->id]);
    app(MobileServiceService::class)->transition($this->admin, $service, MobileServiceStatus::Published);
    app(MobileServiceService::class)->transition($this->officer, $service->fresh(), MobileServiceStatus::Open);
    $this->actingAs($this->officer);
    Livewire::test(CustomerIdentification::class)->set('selectedMobileServiceId', (string) $service->id)->assertSet('mobileServiceId', $service->id)->assertHasNoErrors();
    $this->get(route('officer.customer-identification', ['mobileServiceNumber' => $service->service_number]))->assertOk();
    $this->get(route('officer.deposit-form', ['customerId' => $this->citizen->customerProfile->customer_number, 'mobileServiceNumber' => $service->service_number]))->assertOk();
    $this->get(route('officer.customer-identification', ['mobileServiceNumber' => 'MOB-UNKNOWN']))->assertNotFound();
    expect(fn () => Livewire::test(CustomerIdentification::class)->set('mobileServiceId', $service->id))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('rejects nonexistent mobile choices without silently retaining an old context', function (): void {
    $this->actingAs($this->officer);
    Livewire::test(CustomerIdentification::class)->set('selectedMobileServiceId', '999999')->assertSet('mobileServiceId', null)->assertHasErrors('mobileServiceId');
});

it('keeps an expired open service visible until its officer closes it', function (): void {
    $this->travel(-2)->days();
    $service = app(MobileServiceService::class)->create($this->admin, $this->rt->rw_id, $this->rt->id, 'Belum ditutup', now()->subHour()->toDateTimeString(), now()->addHour()->toDateTimeString(), 20, '', [$this->officer->id], [$this->type->id]);
    app(MobileServiceService::class)->transition($this->admin, $service, MobileServiceStatus::Published);
    app(MobileServiceService::class)->transition($this->officer, $service->fresh(), MobileServiceStatus::Open);
    $this->travelBack();
    $this->actingAs($this->officer);
    Livewire::test(MobileServiceTasks::class)->assertSee('Belum ditutup')->assertSee('Tutup Layanan')->call('close', $service->id)->assertHasNoErrors();
    expect($service->fresh()->status)->toBe(MobileServiceStatus::Closed);
});

it('never offers a draft as a citizen receipt and direct access returns not found', function (): void {
    $draft = app(DepositService::class)->createDraft($this->officer, $this->citizen);
    $this->actingAs($this->citizen)->get('/dashboard/warga')->assertOk()->assertDontSee($draft->deposit_number);
    $this->get('/warga/riwayat-setoran')->assertOk()->assertDontSee($draft->deposit_number);
    $this->get('/warga/setoran/'.$draft->id)->assertNotFound();
});

it('locks public receipt values and invalidates an already open component after token rotation', function (): void {
    $deposits = app(DepositService::class);
    $deposit = $deposits->finalize($this->officer, $deposits->createDraft($this->officer, $this->citizen), (string) str()->uuid(), [['waste_type_id' => $this->type->id, 'condition_id' => $this->type->conditions()->firstOrFail()->id, 'weight_kg' => '1.000']]);
    $screen = Livewire::test(DepositVerification::class, ['token' => $deposit->verificationToken()])->assertSee('Bukti setoran valid');
    expect(fn () => $screen->set('receipt.value', 987654321))->toThrow(CannotUpdateLockedPropertyException::class);
    $open = Livewire::test(DepositVerification::class, ['token' => $deposit->verificationToken()]);
    app(RotateDepositVerificationToken::class)->handle($this->admin, $deposit, 'Rotasi token untuk pengujian');
    $open->call('$refresh')->assertStatus(404);
    expect($deposit->fresh()->total_value)->toBe(1500);
});
