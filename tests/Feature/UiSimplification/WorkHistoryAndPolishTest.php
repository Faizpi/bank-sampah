<?php

declare(strict_types=1);

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Livewire\Officer\GroceryTasks;
use App\Livewire\Officer\PickupTask;
use App\Livewire\Officer\WorkHistory;
use App\Livewire\Treasurer\WithdrawalPayments;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Fixtures\WorkflowFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    $this->officer = User::where('username', 'petugas')->firstOrFail();
    $this->citizen = User::where('username', 'warga')->firstOrFail();
    $this->treasurer = User::where('username', 'bendahara')->firstOrFail();
    $this->otherOfficer = User::factory()->create(['role' => 'petugas', 'status' => UserStatus::Active]);
    $type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
    $this->items = [['waste_type_id' => $type->id, 'condition_id' => $type->conditions()->firstOrFail()->id, 'weight_kg' => '50.000']];
    $this->groceryAttributes = [
        'customer_id' => $this->citizen->id,
        'requested_by_id' => $this->citizen->id,
        'service_area_id' => $this->officer->staffProfile->service_area_id,
        'rt_id' => $this->citizen->customerProfile->rt_id,
        'status' => GroceryStatus::Completed,
        'claimed_by_id' => $this->officer->id,
        'handover_actor_id' => $this->officer->id,
        'handed_over_at' => now(),
    ];
    $this->pickupAttributes = [
        'customer_id' => $this->citizen->id,
        'service_area_id' => $this->officer->staffProfile->service_area_id,
        'rt_id' => $this->citizen->customerProfile->rt_id,
        'assigned_staff_id' => $this->officer->id,
        'scheduled_date' => today(),
        'status' => PickupStatus::Completed,
        'completed_at' => now(),
    ];
});

it('keeps final deposits discoverable in a read-only owner-scoped history', function (): void {
    $service = app(DepositService::class);
    $deposit = $service->finalize($this->officer, $service->createDraft($this->officer, $this->citizen), (string) str()->uuid(), $this->items);
    $draft = $service->createDraft($this->officer, $this->citizen);
    $other = Deposit::query()->create([
        'deposit_number' => 'DEP-OTHER-PRIVATE', 'customer_id' => $this->citizen->id, 'staff_id' => $this->otherOfficer->id,
        'method' => 'langsung', 'location' => 'Loket', 'occurred_at' => now(), 'status' => Deposit::STATUS_FINAL, 'total_value' => 1000,
    ]);
    $before = LedgerEntry::count();
    $this->actingAs($this->officer)->get(route('officer.history'))
        ->assertOk()->assertSee($deposit->deposit_number)->assertSee('Aqua Botol')->assertSee('75.000')
        ->assertDontSee($draft->deposit_number)->assertDontSee($other->deposit_number)
        ->assertDontSee('wire:click="complete"', false)->assertDontSee('wire:click="finalize"', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect(LedgerEntry::count())->toBe($before);
});

it('restores completed grocery bookmarks as read-only history and hides another officers record', function (): void {
    $own = GroceryRedemption::factory()->create($this->groceryAttributes);
    $other = GroceryRedemption::factory()->create([...$this->groceryAttributes, 'claimed_by_id' => $this->otherOfficer->id, 'handover_actor_id' => $this->otherOfficer->id]);
    $this->actingAs($this->officer)->get(route('officer.grocery.tasks', ['taskId' => $own->id]))
        ->assertRedirect(route('officer.history', ['type' => 'grocery', 'recordNumber' => $own->request_number]));
    $this->get(route('officer.history', ['type' => 'grocery', 'recordNumber' => $own->request_number]))
        ->assertOk()->assertSee($own->request_number)->assertSee('Lihat rincian penyerahan')->assertDontSee('Paket sudah diserahkan');
    $this->get(route('officer.history', ['type' => 'grocery', 'recordId' => $other->id]))->assertNotFound();
    $this->get(route('officer.grocery.tasks', ['taskId' => $other->id]))->assertNotFound();
    $this->get(route('officer.history', ['type' => 'grocery']))->assertOk()->assertDontSee($other->request_number);
});

it('keeps past own work visible after an area assignment ends without widening history access', function (): void {
    $own = GroceryRedemption::factory()->create($this->groceryAttributes);
    $this->officer->staffProfile->serviceAreas()->update(['active_to' => today()->subDay()->toDateString()]);
    $this->actingAs($this->officer)->get(route('officer.history', ['type' => 'grocery']))->assertOk()->assertSee($own->request_number);
    $this->actingAs($this->otherOfficer)->get(route('officer.history', ['type' => 'grocery']))->assertOk()->assertDontSee($own->request_number);
});

it('shows terminal assigned pickups but not active or other officers pickups', function (): void {
    $done = PickupRequest::factory()->create($this->pickupAttributes);
    $active = PickupRequest::factory()->create([...$this->pickupAttributes, 'status' => PickupStatus::Scheduled, 'completed_at' => null]);
    $other = PickupRequest::factory()->create([...$this->pickupAttributes, 'assigned_staff_id' => $this->otherOfficer->id]);
    $this->actingAs($this->officer)->get(route('officer.history', ['type' => 'pickup']))
        ->assertOk()->assertSee($done->request_number)->assertDontSee($active->request_number)->assertDontSee($other->request_number);
});

it('paginates own history and resets the page when searching', function (): void {
    $records = GroceryRedemption::factory()->count(11)->create($this->groceryAttributes);
    $this->actingAs($this->officer);
    Livewire::withQueryParams(['type' => 'grocery'])->test(WorkHistory::class)
        ->call('setPage', 2, 'workHistory')->assertSet('paginators.workHistory', 2)
        ->set('search', $records->last()->request_number)->assertSet('paginators.workHistory', 1)
        ->assertSee($records->last()->request_number)->assertDontSee($records->first()->request_number);
});

it('rejects invalid history selectors and locked-state tampering', function (): void {
    $this->actingAs($this->officer)->get(route('officer.history', ['type' => 'withdrawal']))->assertNotFound();
    $this->get(route('officer.history', ['recordId' => 'invalid']))->assertNotFound();
    expect(fn () => Livewire::test(WorkHistory::class)->set('type', 'grocery'))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => Livewire::test(WorkHistory::class)->set('recordId', 1))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('denies non-officer roles access to officer work history', function (string $role): void {
    $this->actingAs(User::where('username', $role)->firstOrFail())->get(route('officer.history'))->assertForbidden();
})->with(['warga', 'bendahara', 'admin']);

it('does not present an omitted pickup estimate as zero', function (): void {
    $service = app(PickupService::class);
    $pickup = $service->submit($this->citizen, ['service_area_id' => $this->officer->staffProfile->service_area_id, 'selected_date' => today()->toDateString(), 'address' => 'Alamat uji'], [['waste_type_id' => $this->items[0]['waste_type_id']]], [], (string) str()->uuid());
    $this->actingAs($this->citizen)->get(route('citizen.pickup.show', $pickup))->assertOk()->assertSeeText('Perkiraan belum diisi')->assertDontSeeText('0 item');
});

it('uses the actual pickup status for the mascot and badge', function (PickupStatus $status, string $bubble, string $badge): void {
    $pickup = PickupRequest::factory()->create([...$this->pickupAttributes, 'status' => $status]);
    $this->actingAs($this->officer);
    Livewire::test(PickupTask::class, ['pickup' => $pickup])->assertSee($bubble)->assertSee('data-status="'.$badge.'"', false);
})->with([
    [PickupStatus::Scheduled, 'Siap menjemput!', 'pending'],
    [PickupStatus::EnRoute, 'Menuju lokasi!', 'in_progress'],
    [PickupStatus::PickedUp, 'Saatnya timbang sampah!', 'in_progress'],
    [PickupStatus::Completed, 'Penjemputan selesai!', 'success'],
    [PickupStatus::Cancelled, 'Penjemputan tidak dilanjutkan', 'cancelled'],
]);

it('restores the pickup receipt after a new page request without writing another ledger entry', function (): void {
    $service = app(PickupService::class);
    $pickup = $service->submit($this->citizen, ['service_area_id' => $this->officer->staffProfile->service_area_id, 'selected_date' => today()->toDateString(), 'address' => 'Alamat uji'], [['waste_type_id' => $this->items[0]['waste_type_id']]], [], (string) str()->uuid());
    $pickup = $service->markPickedUp($this->officer, $service->begin($this->officer, $service->claim($this->officer, $pickup)));
    $pickup = $service->complete($this->officer, $pickup, $this->items, (string) str()->uuid());
    $before = LedgerEntry::count();
    $this->actingAs($this->officer)->get(route('officer.pickup.task', $pickup))
        ->assertOk()->assertSee($pickup->deposit->deposit_number)->assertSee('75.000')->assertSee('Lihat riwayat setoran')->assertDontSee('Menuju lokasi!');
    expect(LedgerEntry::count())->toBe($before);
    // Display a later reversal without exercising a financial action from the history screen.
    $pickup->deposit->forceFill(['status' => Deposit::STATUS_REVERSED])->save();
    $this->get(route('officer.pickup.task', $pickup))->assertOk()->assertSee('Setoran sudah dibalik')->assertDontSee('Setoran pickup berhasil');
    expect(LedgerEntry::count())->toBe($before);
});

it('presents a named amount confirmation before the cash completion button', function (): void {
    $deposits = app(DepositService::class);
    $deposits->finalize($this->officer, $deposits->createDraft($this->officer, $this->citizen), (string) str()->uuid(), $this->items);
    $withdrawal = app(WithdrawalService::class)->request($this->citizen, ['amount' => 10000, 'pickup_location' => 'Loket', 'pickup_date' => today()->toDateString()], (string) str()->uuid());
    app(ClaimedServiceWorkflow::class)->claim($this->treasurer, $withdrawal);
    $this->actingAs($this->treasurer);
    $before = LedgerEntry::count();
    $screen = Livewire::test(WithdrawalPayments::class)->call('select', $withdrawal->id)
        ->assertSee('Catat pembayaran tunai?')->assertSee($this->citizen->name)->assertSee('10.000')
        ->assertSee('Periksa lagi')->assertSee('Ya, catat pembayaran');
    expect($withdrawal->fresh()->paid_at)->toBeNull()->and(LedgerEntry::count())->toBe($before);
    $screen->call('complete')->assertHasNoErrors()->assertSee('Penyerahan tercatat');
    expect($withdrawal->fresh()->paid_at)->not->toBeNull()->and(LedgerEntry::count())->toBe($before + 1);
});

it('presents the same lightweight confirmation for grocery handover', function (): void {
    $grocery = GroceryRedemption::factory()->create([...$this->groceryAttributes, 'status' => GroceryStatus::Processing, 'handed_over_at' => null, 'handover_actor_id' => null]);
    $this->actingAs($this->officer);
    Livewire::test(GroceryTasks::class)->call('select', $grocery->id)
        ->assertSee('Catat penyerahan sembako?')->assertSee('Paket Sembako Demo')->assertSee('Ya, catat penyerahan')->assertSee('Periksa lagi');
});
