<?php

declare(strict_types=1);

namespace Tests\Feature\Officer;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Livewire\Officer\GroceryTasks;
use App\Livewire\Officer\MobileServiceTasks;
use App\Livewire\Officer\PickupTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class FieldOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pickup_task_is_not_mountable_by_an_unassigned_officer(): void
    {
        $assigned = $this->userWith('pickup.execute', 'pickup.complete');
        $other = $this->userWith('pickup.execute', 'pickup.complete');
        $pickup = $this->scheduledPickup($assigned);

        $this->actingAs($other);

        Livewire::test(PickupTask::class, ['pickup' => $pickup])->assertNotFound();
    }

    public function test_pickup_task_transitions_only_the_assigned_pickup_and_requires_complete_evidence(): void
    {
        $officer = $this->userWith('pickup.view', 'pickup.execute', 'pickup.complete', 'pickup.cancel');
        [$type, $condition] = $this->wasteContext();
        $pickup = $this->scheduledPickup($officer);
        $this->actingAs($officer);

        Livewire::test(PickupTask::class, ['pickup' => $pickup])
            ->call('begin')
            ->call('markPickedUp')
            ->set('actualItems', [['waste_type_id' => (string) $type->id, 'condition_id' => (string) $condition->id, 'weight_kg' => '1.000']])
            ->call('complete')
            ->assertHasErrors('actualItems')
            ->assertSee('Tinjau lalu konfirmasi finalisasi sebelum menyelesaikan tugas.')
            ->call('reviewCompletion')
            ->assertHasErrors('evidence');

        self::assertSame(PickupStatus::PickedUp, $pickup->fresh()->status);
        self::assertDatabaseCount('deposits', 0);
    }

    public function test_pickup_success_projection_treats_deposit_status_as_a_string(): void
    {
        $source = file_get_contents(app_path('Livewire/Officer/PickupTask.php'));

        self::assertIsString($source);
        self::assertStringContainsString("'status' => (string) \$deposit->status", $source);
        self::assertStringNotContainsString('$deposit->status->value', $source);
    }

    public function test_officer_success_receipts_render_all_transaction_facts(): void
    {
        $officer = $this->userWith('pickup.view', 'pickup.execute', 'pickup.complete', 'grocery.handover');
        $pickup = $this->scheduledPickup($officer);
        $occurredAt = '15 Agustus 2026, 10:30';

        Livewire::actingAs($officer)
            ->test(PickupTask::class, ['pickup' => $pickup])
            ->set('receipt', ['number' => 'STR-PICKUP-001', 'value' => 125_000, 'occurredAt' => $occurredAt, 'status' => 'final'])
            ->assertSee('Berhasil')
            ->assertSee('STR-PICKUP-001')
            ->assertSee('Rp 125.000')
            ->assertSee($occurredAt);

        Livewire::actingAs($officer)
            ->test(GroceryTasks::class)
            ->set('receipt', ['number' => 'GRC-HANDOVER-001', 'value' => 50_000, 'occurredAt' => $occurredAt, 'status' => 'selesai'])
            ->assertSee('Berhasil')
            ->assertSee('GRC-HANDOVER-001')
            ->assertSee('Rp 50.000')
            ->assertSee($occurredAt);
    }

    public function test_pickup_task_exposes_placeholders_and_photo_capture_controls(): void
    {
        $officer = $this->userWith('pickup.view', 'pickup.execute', 'pickup.complete');
        $this->wasteContext();
        $pickup = $this->scheduledPickup($officer);
        $this->actingAs($officer);

        Livewire::test(PickupTask::class, ['pickup' => $pickup])
            ->call('begin')
            ->call('markPickedUp')
            ->assertSee('Pilih jenis sampah')
            ->assertSee('Pilih kondisi')
            ->assertSee('Ambil dari kamera')
            ->assertSee('Pilih dari galeri')
            ->assertSee('data-photo-picker-property="evidence"', escape: false)
            ->assertDontSee('wire:model="evidence"', escape: false);
    }

    public function test_officer_final_actions_require_a_review_dialog_with_consequences(): void
    {
        $pickupOfficer = $this->userWith('pickup.view', 'pickup.execute', 'pickup.complete');
        [$type, $condition] = $this->wasteContext();
        $pickup = $this->scheduledPickup($pickupOfficer);
        $this->actingAs($pickupOfficer);

        Livewire::test(PickupTask::class, ['pickup' => $pickup])
            ->call('begin')
            ->call('markPickedUp')
            ->assertSee('Review Finalisasi')
            ->assertSee('Harga aktif dan subtotal akan dihitung ulang server saat finalisasi.');

        $handoverOfficer = $this->userWith('grocery.handover');
        $package = GroceryPackage::query()->create([
            'code' => 'PAKET-KONFIRMASI-001',
            'name' => 'Paket Konfirmasi',
            'contents' => 'Bahan pokok',
            'value' => 50_000,
            'status' => 'aktif',
        ]);
        $redemption = GroceryRedemption::query()->create([
            'request_number' => 'GRC-KONFIRMASI-001',
            'customer_id' => User::factory()->create()->id,
            'requested_by_id' => $handoverOfficer->id,
            'grocery_package_id' => $package->id,
            'value_snapshot' => $package->value,
            'package_snapshot' => ['name' => $package->name, 'value' => $package->value],
            'status' => GroceryStatus::ReadyForPickup,
        ]);
        $this->actingAs($handoverOfficer);

        Livewire::test(GroceryTasks::class)
            ->call('select', $redemption->id)
            ->assertSee('Tinjau serah-terima');
    }

    public function test_grocery_handover_card_scan_uses_camera_with_manual_fallback_and_cleanup(): void
    {
        $view = file_get_contents(resource_path('views/livewire/officer/grocery-tasks.blade.php'));

        self::assertIsString($view);
        self::assertStringContainsString("'BarcodeDetector' in window", $view);
        self::assertStringContainsString('navigator.mediaDevices?.getUserMedia', $view);
        self::assertStringContainsString('$wire.scanCustomerCard(rawValue)', $view);
        self::assertStringContainsString('stream?.getTracks().forEach((track) => track.stop())', $view);
        self::assertStringContainsString('wire:model="scanToken"', $view);
        self::assertStringContainsString('$wire.closeScanner()', $view);
    }

    public function test_pickup_task_rejects_invalid_actual_items_before_financial_service(): void
    {
        $officer = $this->userWith('pickup.view', 'pickup.execute', 'pickup.complete');
        $this->wasteContext();
        $pickup = $this->scheduledPickup($officer);
        $this->actingAs($officer);

        Livewire::test(PickupTask::class, ['pickup' => $pickup])
            ->call('begin')
            ->call('markPickedUp')
            ->set('actualItems', [['waste_type_id' => '', 'condition_id' => '', 'weight_kg' => 'not-a-weight']])
            ->call('reviewCompletion')
            ->assertHasErrors(['actualItems.0.waste_type_id', 'actualItems.0.condition_id', 'actualItems.0.weight_kg'])
            ->assertSee('Jenis sampah wajib dipilih.')
            ->assertSee('Kondisi sampah wajib dipilih.');

        self::assertSame(PickupStatus::PickedUp, $pickup->fresh()->status);
        self::assertDatabaseCount('deposits', 0);
    }

    public function test_pickup_task_records_a_reasoned_failure_as_a_terminal_cancelled_state(): void
    {
        $officer = $this->userWith('pickup.view', 'pickup.execute', 'pickup.cancel');
        $pickup = $this->scheduledPickup($officer);
        $this->actingAs($officer);

        Livewire::test(PickupTask::class, ['pickup' => $pickup])
            ->set('failureReason', 'Warga tidak berada di lokasi pickup')
            ->call('reportFailure')
            ->assertHasNoErrors();

        self::assertSame(PickupStatus::Cancelled, $pickup->fresh()->status);
        self::assertSame('Warga tidak berada di lokasi pickup', $pickup->fresh()->cancellation_reason);
    }

    public function test_mobile_service_tasks_hide_unassigned_services_and_enforce_assignment_on_open(): void
    {
        $assigned = $this->userWith('mobile-service.operate');
        $other = $this->userWith('mobile-service.operate');
        $service = $this->mobileService($assigned);
        $this->actingAs($other);

        Livewire::test(MobileServiceTasks::class)
            ->assertDontSee($service->service_number)
            ->call('open', $service->id);

        self::assertSame(MobileServiceStatus::Published, $service->fresh()->status);
    }

    public function test_mobile_service_tasks_open_and_close_an_assigned_service_using_domain_transitions(): void
    {
        $officer = $this->userWith('mobile-service.operate');
        $service = $this->mobileService($officer);
        $this->actingAs($officer);

        Livewire::test(MobileServiceTasks::class)
            ->call('open', $service->id)
            ->call('close', $service->id)
            ->assertHasNoErrors();

        self::assertSame(MobileServiceStatus::Closed, $service->fresh()->status);
    }

    public function test_mobile_service_tasks_hide_expired_actionable_services_without_hiding_history(): void
    {
        $officer = $this->userWith('mobile-service.operate');
        $now = CarbonImmutable::parse('2026-08-10 10:00:00', 'Asia/Jakarta');
        CarbonImmutable::setTestNow($now);

        try {
            $expired = $this->mobileService($officer, MobileServiceStatus::Published);
            $expired->forceFill(['point' => 'Titik layanan kedaluwarsa', 'ends_at' => $now->subSecond()])->save();
            $historical = $this->mobileService($officer, MobileServiceStatus::Open);
            $historical->forceFill(['point' => 'Rekap layanan selesai', 'ends_at' => $now->subSecond()])->save();
            $current = $this->mobileService($officer, MobileServiceStatus::Published);
            $current->forceFill(['point' => 'Titik layanan sekarang', 'ends_at' => $now->addHour()])->save();
            $this->actingAs($officer);

            Livewire::test(MobileServiceTasks::class)
                ->assertDontSee($expired->point)
                ->assertDontSee($historical->point)
                ->assertSee($current->point);

            Livewire::test(MobileServiceTasks::class)->call('open', $expired->id);
            Livewire::test(MobileServiceTasks::class)
                ->call('recap', $historical->id)
                ->assertHasNoErrors();

            self::assertSame(MobileServiceStatus::Published, $expired->fresh()->status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /** @return array{WasteType, WasteCondition} */
    private function wasteContext(): array
    {
        $category = WasteCategory::factory()->create(['is_active' => true]);
        $unit = WasteUnit::factory()->weight()->create();
        $condition = WasteCondition::factory()->create(['is_active' => true]);
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create(['is_active' => true]);
        WasteMasterMutationGuard::run(static fn (): array => $type->conditions()->sync([$condition->id]));

        return [$type, $condition];
    }

    public function test_officer_dashboard_exposes_scoped_operational_work(): void
    {
        $officer = $this->userWith(
            'user.view',
            'pickup.view',
            'deposit.view',
            'grocery.view',
            'mobile-service.view',
            'mobile-service.operate',
        );
        $customer = User::factory()->create();
        $today = today()->toDateString();
        $late = today()->subDay()->toDateString();
        $this->pickupForDashboard($officer, $today, PickupStatus::Scheduled, 'PUP-TODAY-001');
        $this->pickupForDashboard($officer, $late, PickupStatus::EnRoute, 'PUP-LATE-001');
        $this->pickupForDashboard($officer, $today, PickupStatus::Completed, 'PUP-DONE-001');
        Deposit::query()->create([
            'deposit_number' => 'DEP-DRAFT-001',
            'customer_id' => $customer->id,
            'staff_id' => $officer->id,
            'method' => 'langsung',
            'occurred_at' => now(),
            'status' => Deposit::STATUS_DRAFT,
        ]);
        $mobileService = $this->mobileService($officer, MobileServiceStatus::Open);
        $package = GroceryPackage::query()->create([
            'code' => 'PAKET-OFFICER-001',
            'name' => 'Paket Officer',
            'contents' => 'Bahan pokok',
            'value' => 50_000,
            'status' => 'aktif',
        ]);
        GroceryRedemption::query()->create([
            'request_number' => 'GRC-OFFICER-001',
            'customer_id' => $customer->id,
            'requested_by_id' => $officer->id,
            'grocery_package_id' => $package->id,
            'value_snapshot' => $package->value,
            'package_snapshot' => ['name' => $package->name, 'value' => $package->value],
            'status' => GroceryStatus::Approved,
        ]);

        $this->actingAs($officer);

        $response = $this->get(route('officer.dashboard'));
        $response->assertOk();
        $response->assertSeeText('PUP-TODAY-001');
        $response->assertSeeText('PUP-LATE-001');
        $response->assertSeeText('DEP-DRAFT-001');
        $response->assertSeeText('GRC-OFFICER-001');
        $response->assertSeeText($mobileService->service_number);
    }

    private function scheduledPickup(User $officer): PickupRequest
    {
        $customer = User::factory()->create();

        return PickupRequest::query()->create([
            'request_number' => 'PUP-TASK-'.str()->upper(str()->random(8)),
            'customer_id' => $customer->id,
            'rt_id' => $this->rtId(),
            'service_area_id' => $this->serviceAreaId(),
            'address' => 'Alamat tugas lapangan yang lengkap',
            'selected_date' => today(),
            'scheduled_date' => today(),
            'estimated_weight_kg' => '2.000',
            'status' => PickupStatus::Scheduled,
            'assigned_staff_id' => $officer->id,
        ]);
    }

    private function pickupForDashboard(User $officer, string $date, PickupStatus $status, string $number): PickupRequest
    {
        $customer = User::factory()->create();

        return PickupRequest::query()->create([
            'request_number' => $number,
            'customer_id' => $customer->id,
            'rt_id' => $this->rtId(),
            'service_area_id' => $this->serviceAreaId(),
            'address' => 'Alamat dashboard yang lengkap',
            'selected_date' => $date,
            'scheduled_date' => $date,
            'estimated_weight_kg' => '2.000',
            'status' => $status,
            'assigned_staff_id' => $officer->id,
        ]);
    }

    private function mobileService(User $officer, MobileServiceStatus $status = MobileServiceStatus::Published): MobileService
    {
        [$type] = $this->wasteContext();
        $service = MobileService::query()->create([
            'service_number' => 'MOB-OFFICER-'.str()->upper(str()->random(8)),
            'point' => 'Balai layanan officer',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => $status,
            'capacity' => 20,
            'served_count' => 0,
            'created_by' => $officer->id,
        ]);
        $service->staff()->attach($officer->id);
        $service->wasteTypes()->attach($type->id);

        return $service;
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'officer-test-'.str()->random(12), 'description' => 'Officer test role']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function rtId(): int
    {
        $dusun = Dusun::query()->create(['code' => 'OFF-DS-'.str()->random(8), 'name' => 'Officer Dusun', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'OFF-RW-'.str()->random(8), 'name' => 'Officer RW', 'is_active' => true]);

        return Rt::query()->create(['rw_id' => $rw->id, 'code' => 'OFF-RT-'.str()->random(8), 'name' => 'Officer RT', 'is_active' => true])->id;
    }

    private function serviceAreaId(): int
    {
        return ServiceArea::query()->create(['name' => 'Officer Area '.str()->random(8), 'is_active' => true])->id;
    }
}
