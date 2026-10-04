<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Communication\Enums\AnnouncementAudience;
use App\Domain\Communication\Enums\AnnouncementStatus;
use App\Domain\Communication\Models\Announcement;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Services\TargetProgressService;
use App\Domain\Programs\Services\TargetService;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Filament\Resources\Communication\Models\Announcements\AnnouncementResource;
use App\Filament\Resources\Communication\Models\Announcements\Pages\ManageAnnouncements;
use App\Filament\Resources\MobileServices\Models\MobileServices\MobileServiceResource;
use App\Filament\Resources\MobileServices\Models\MobileServices\Pages\ManageMobileServices;
use App\Filament\Resources\Pickups\Models\PickupCapacities\Pages\ManagePickupCapacities;
use App\Filament\Resources\Programs\Models\CollectionTargets\CollectionTargetResource;
use App\Filament\Resources\Programs\Models\CollectionTargets\Pages\ManageCollectionTargets;
use App\Filament\Resources\Statistics\Models\StatisticPublications\Pages\ManageStatisticPublications;
use App\Filament\Resources\Statistics\Models\StatisticPublications\StatisticPublicationResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminOperationsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_resources_are_permission_gated_and_have_management_pages(): void
    {
        $admin = $this->userWith('backoffice.access', 'announcement.manage', 'announcement.publish', 'target.manage', 'target.publish', 'mobile-service.manage', 'mobile-service.operate', 'statistics.public.manage', 'waste.manage');
        $viewer = $this->userWith('backoffice.access');

        $this->actingAs($admin);
        self::assertTrue(AnnouncementResource::canViewAny());
        self::assertTrue(CollectionTargetResource::canViewAny());
        self::assertTrue(MobileServiceResource::canViewAny());
        self::assertTrue(StatisticPublicationResource::canViewAny());
        self::assertSame(['index'], array_keys(AnnouncementResource::getPages()));
        self::assertSame(['index'], array_keys(CollectionTargetResource::getPages()));
        self::assertSame(['index'], array_keys(MobileServiceResource::getPages()));
        self::assertSame(['index'], array_keys(StatisticPublicationResource::getPages()));

        $this->actingAs($viewer);
        self::assertFalse(AnnouncementResource::canViewAny());
        self::assertFalse(CollectionTargetResource::canViewAny());
        self::assertFalse(MobileServiceResource::canViewAny());
        self::assertFalse(StatisticPublicationResource::canViewAny());
        self::assertSame(ManageAnnouncements::class, AnnouncementResource::getPages()['index']->getPage());
        self::assertSame(ManageCollectionTargets::class, CollectionTargetResource::getPages()['index']->getPage());
        self::assertSame(ManageMobileServices::class, MobileServiceResource::getPages()['index']->getPage());
        self::assertSame(ManageStatisticPublications::class, StatisticPublicationResource::getPages()['index']->getPage());
    }

    public function test_announcement_resource_publishes_and_unpublishes_through_audited_service_actions(): void
    {
        $admin = $this->userWith('backoffice.access', 'announcement.view', 'announcement.manage', 'announcement.publish');
        $announcement = Announcement::query()->create([
            'announcement_number' => 'ANN-RESOURCE-'.uniqid(),
            'title' => 'Jadwal layanan',
            'body' => '<p>Jadwal layanan minggu ini.</p>',
            'audience' => AnnouncementAudience::Public,
            'publish_start' => now()->subMinute(),
            'publish_end' => null,
            'status' => AnnouncementStatus::Draft,
            'priority' => 1,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);
        Livewire::test(ManageAnnouncements::class)
            ->assertCanSeeTableRecords([$announcement])
            ->assertTableActionVisible('publish', $announcement)
            ->callTableAction('publish', $announcement);

        self::assertDatabaseHas('announcements', ['id' => $announcement->id, 'status' => AnnouncementStatus::Published->value, 'published_by' => $admin->id]);
        self::assertDatabaseHas('audit_logs', ['action' => 'announcement.published', 'auditable_id' => $announcement->id, 'actor_id' => $admin->id]);

        Livewire::test(ManageAnnouncements::class)
            ->assertTableActionVisible('unpublish', $announcement->fresh())
            ->callTableAction('unpublish', $announcement->fresh());

        self::assertDatabaseHas('announcements', ['id' => $announcement->id, 'status' => AnnouncementStatus::Inactive->value]);
        self::assertDatabaseHas('audit_logs', ['action' => 'announcement.unpublished', 'auditable_id' => $announcement->id, 'actor_id' => $admin->id]);
    }

    public function test_announcement_resource_scopes_non_admin_records_by_audience_and_policy(): void
    {
        $viewer = $this->userWith('backoffice.access', 'announcement.view');
        $customerRt = $this->createRt('SCOPE-RT');
        $viewer->customerProfile()->create(['customer_number' => 'CST-SCOPE-'.uniqid(), 'rt_id' => $customerRt->id, 'address' => 'Alamat scope', 'joined_at' => today()]);
        $visible = Announcement::factory()->create(['audience' => AnnouncementAudience::Citizen]);
        $hidden = Announcement::factory()->create(['audience' => AnnouncementAudience::Internal]);
        $region = Announcement::factory()->create(['audience' => AnnouncementAudience::Region]);
        $region->rts()->attach($customerRt);
        $otherRegion = Announcement::factory()->create(['audience' => AnnouncementAudience::Region]);
        $otherRt = $this->createRt('OTHER-RT');
        $otherRegion->rts()->attach($otherRt);

        $this->actingAs($viewer);
        self::assertSame([$visible->id, $region->id], AnnouncementResource::getEloquentQuery()->pluck('id')->sort()->values()->all());
        self::assertTrue($viewer->fresh()->can('view', $visible));
        self::assertFalse($viewer->fresh()->can('view', $hidden));
        self::assertFalse($viewer->fresh()->can('view', $otherRegion));
    }

    public function test_target_progress_batch_preserves_formula_and_avoids_row_aggregation_queries(): void
    {
        $admin = $this->userWith('target.view', 'target.manage', 'target.publish', 'waste.manage');
        $category = WasteCategory::factory()->create();
        $unit = WasteUnit::factory()->weight('1.000000')->create();
        $condition = WasteCondition::factory()->create();
        $type = WasteType::factory()->create(['waste_category_id' => $category->id, 'waste_unit_id' => $unit->id, 'is_plastic' => true]);
        $targetA = CollectionTarget::query()->create(['target_number' => 'TGT-'.uniqid(), 'name' => 'Target A', 'purpose' => 'Uji target A', 'period_start' => today()->subDay(), 'period_end' => today()->addDay(), 'target_weight_kg' => '10.000', 'status' => TargetStatus::Active, 'is_public' => false, 'created_by' => $admin->id]);
        $targetB = CollectionTarget::query()->create(['target_number' => 'TGT-'.uniqid(), 'name' => 'Target B', 'purpose' => 'Uji target B', 'period_start' => today()->subDay(), 'period_end' => today()->addDay(), 'target_weight_kg' => '10.000', 'status' => TargetStatus::Active, 'is_public' => false, 'created_by' => $admin->id]);
        $targetC = CollectionTarget::query()->create(['target_number' => 'TGT-'.uniqid(), 'name' => 'Target C', 'purpose' => 'Uji target C', 'period_start' => today()->subDay(), 'period_end' => today()->addDay(), 'target_weight_kg' => '10.000', 'status' => TargetStatus::Active, 'is_public' => false, 'created_by' => $admin->id]);
        $targetA->scopes()->create(['waste_type_id' => $type->id]);
        $targetB->scopes()->create(['waste_type_id' => $type->id]);
        $targetC->scopes()->create(['waste_type_id' => $type->id]);
        $customer = User::factory()->create();
        $deposit = Deposit::query()->create(['deposit_number' => 'DEP-PROGRESS-'.uniqid(), 'customer_id' => $customer->id, 'staff_id' => $admin->id, 'method' => 'loket', 'occurred_at' => now(), 'status' => Deposit::STATUS_FINAL]);
        $deposit->items()->create(['waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.250']);

        DB::enableQueryLog();
        $aggregates = app(TargetProgressService::class)->aggregateMany([$targetA->load('scopes'), $targetB->load('scopes'), $targetC->load('scopes')]);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertSame('1.250', $aggregates[$targetA->id]['weight_kg']);
        self::assertSame(1, $aggregates[$targetA->id]['subject_count']);
        self::assertSame(1, $aggregates[$targetA->id]['deposit_count']);
        self::assertSame('1.250', $aggregates[$targetA->id]['plastic_weight_kg']);
        self::assertSame($aggregates[$targetA->id], $aggregates[$targetB->id]);
        self::assertLessThanOrEqual(8, $queries);
    }

    public function test_target_resource_activates_and_closes_with_progress_snapshot(): void
    {
        $admin = $this->userWith('backoffice.access', 'target.view', 'target.manage', 'target.publish');
        $target = app(TargetService::class)->create($admin, 'Target plastik', 'Pengumpulan plastik desa', today()->toDateString(), today()->addDays(30)->toDateString(), '10.000', true, []);

        $this->actingAs($admin);
        Livewire::test(ManageCollectionTargets::class)
            ->assertCanSeeTableRecords([$target])
            ->assertTableActionVisible('activate', $target)
            ->callTableAction('activate', $target);

        self::assertDatabaseHas('collection_targets', ['id' => $target->id, 'status' => TargetStatus::Active->value, 'published_by' => $admin->id]);

        Livewire::test(ManageCollectionTargets::class)
            ->assertTableActionVisible('close', $target->fresh())
            ->callTableAction('close', $target->fresh());

        self::assertDatabaseHas('collection_targets', ['id' => $target->id, 'status' => TargetStatus::Closed->value, 'closed_progress_kg' => '0.000']);
        self::assertDatabaseHas('audit_logs', ['action' => 'target.closed', 'auditable_id' => $target->id, 'actor_id' => $admin->id]);
    }

    public function test_mobile_service_resource_shows_safe_error_and_keeps_draft_when_publish_schedule_collides(): void
    {
        [$admin, $staff, $rt, $type] = $this->mobileContext();
        $this->actingAs($admin);
        $service = app(MobileServiceService::class);
        $published = $service->create($admin, null, $rt->id, 'Balai RT 01', '2026-08-10 09:00:00', '2026-08-10 11:00:00', 20, '', [$staff->id], [$type->id]);
        $draft = $service->create($admin, null, $rt->id, 'Lapangan RT 02', '2026-08-10 10:00:00', '2026-08-10 12:00:00', 20, '', [$staff->id], [$type->id]);
        $service->transition($admin, $published, MobileServiceStatus::Published);

        Livewire::test(ManageMobileServices::class)
            ->assertTableActionVisible('publish', $draft)
            ->callTableAction('publish', $draft)
            ->assertNotified('Layanan belum dapat dipublikasikan');

        self::assertSame(MobileServiceStatus::Draft, $draft->fresh()->status);
    }

    public function test_mobile_service_resource_creates_publishes_opens_and_closes_a_schedule(): void
    {
        [$admin, $staff, $rt, $type] = $this->mobileContext();
        $this->actingAs($admin);

        Livewire::test(ManageMobileServices::class)
            ->callAction('create', data: [
                'rw_id' => null,
                'rt_id' => $rt->id,
                'point' => 'Balai RT 01',
                'starts_at' => '2026-08-10 09:00:00',
                'ends_at' => '2026-08-10 11:00:00',
                'capacity' => 20,
                'notes' => 'Bawa timbangan.',
                'staff_ids' => [$staff->id],
                'waste_type_ids' => [$type->id],
            ]);

        $service = MobileService::query()->latest('id')->firstOrFail();
        self::assertSame(MobileServiceStatus::Draft, $service->status);

        Livewire::test(ManageMobileServices::class)
            ->assertTableActionVisible('publish', $service)
            ->callTableAction('publish', $service);
        Livewire::test(ManageMobileServices::class)
            ->assertTableActionVisible('open', $service->fresh())
            ->callTableAction('open', $service->fresh());
        Livewire::test(ManageMobileServices::class)
            ->assertTableActionVisible('close', $service->fresh())
            ->callTableAction('close', $service->fresh());

        self::assertDatabaseHas('mobile_services', ['id' => $service->id, 'status' => MobileServiceStatus::Closed->value]);
        self::assertDatabaseHas('audit_logs', ['action' => 'mobile-service.status.changed', 'auditable_id' => $service->id, 'actor_id' => $admin->id]);
    }

    public function test_capacity_key_change_deactivates_the_old_record_without_direct_deletion(): void
    {
        $admin = $this->userWith('backoffice.access', 'pickup.capacity.manage');
        $area = ServiceArea::query()->create(['name' => 'Capacity resource area', 'is_active' => true]);
        $old = PickupCapacity::query()->create([
            'service_area_id' => $area->id,
            'service_date' => today()->addDay(),
            'max_addresses' => 5,
            'max_weight_kg' => '50.000',
            'vehicle_label' => 'Lama',
            'is_active' => true,
        ]);
        $this->actingAs($admin);

        Livewire::test(ManagePickupCapacities::class)
            ->assertCanSeeTableRecords([$old])
            ->assertTableActionVisible('edit', $old)
            ->callTableAction('edit', $old, data: [
                'service_area_id' => $area->id,
                'service_date' => today()->addDays(2)->toDateString(),
                'max_addresses' => 7,
                'max_weight_kg' => '70.000',
                'vehicle_label' => 'Baru',
            ]);

        $replacement = PickupCapacity::query()->whereDate('service_date', today()->addDays(2))->firstOrFail();
        self::assertNotSame($old->id, $replacement->id);
        self::assertDatabaseHas('pickup_capacities', ['id' => $old->id, 'is_active' => 0]);
        self::assertDatabaseHas('pickup_capacities', ['id' => $replacement->id, 'is_active' => 1]);
        self::assertDatabaseHas('audit_logs', ['action' => 'pickup.capacity.replaced', 'auditable_id' => $old->id, 'actor_id' => $admin->id]);
        self::assertDatabaseHas('audit_logs', ['action' => 'pickup.capacity.updated', 'auditable_id' => $replacement->id, 'actor_id' => $admin->id]);
    }

    public function test_public_statistics_resource_configures_allowlisted_publication(): void
    {
        $admin = $this->userWith('backoffice.access', 'statistics.public.manage');
        $publication = StatisticPublication::query()->create([
            'publication_key' => 'public-dashboard',
            'metrics' => ['deposit_count'],
            'dimensions' => ['period'],
            'privacy_threshold' => 5,
            'is_active' => false,
        ]);

        $this->actingAs($admin);
        Livewire::test(ManageStatisticPublications::class)
            ->assertCanSeeTableRecords([$publication])
            ->assertTableActionVisible('edit', $publication)
            ->callTableAction('edit', $publication, data: [
                'metrics' => ['deposit_count', 'total_weight_kg'],
                'dimensions' => ['period'],
                'privacy_threshold' => 8,
                'is_active' => true,
            ]);

        self::assertDatabaseHas('statistic_publications', [
            'publication_key' => 'public-dashboard',
            'privacy_threshold' => 8,
            'is_active' => 1,
            'approved_by' => $admin->id,
        ]);
        self::assertDatabaseHas('audit_logs', [
            'action' => 'statistics.publication.configured',
            'auditable_id' => $publication->id,
            'actor_id' => $admin->id,
        ]);
    }

    /** @return array{0: User, 1: User, 2: Rt, 3: WasteType} */
    private function mobileContext(): array
    {
        $admin = $this->userWith('backoffice.access', 'mobile-service.view', 'mobile-service.manage', 'mobile-service.operate', 'waste.manage');
        $staff = $this->userWith('mobile-service.operate');
        $dusun = Dusun::query()->create(['code' => 'RESOURCE-DS-'.uniqid(), 'name' => 'Dusun Resource', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RESOURCE-RW-'.uniqid(), 'name' => 'RW Resource', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RESOURCE-RT-'.uniqid(), 'name' => 'RT Resource', 'is_active' => true]);
        $staff->staffProfile()->create(['staff_number' => 'RESOURCE-STF-'.uniqid(), 'service_area_id' => null, 'active_from' => today(), 'active_to' => null]);
        $category = app(ManageWasteMaster::class)->createCategory($admin, 'RESOURCE-CAT-'.uniqid(), 'Kategori resource');
        $unit = app(ManageWasteMaster::class)->createUnit($admin, 'RESOURCE-UNIT-'.uniqid(), 'Kilogram', 'kg', WasteUnit::CLASSIFICATION_WEIGHT, '1.000000');
        $condition = app(ManageWasteMaster::class)->createCondition($admin, 'RESOURCE-COND-'.uniqid(), 'Bersih', null);
        $type = app(ManageWasteMaster::class)->createType($admin, $category, $unit, 'RESOURCE-TYPE-'.uniqid(), 'Plastik resource', null, 0, true, true, [$condition->id]);

        return [$admin, $staff, $rt, $type];
    }

    private function createRt(string $prefix): Rt
    {
        $dusun = Dusun::query()->create(['code' => $prefix.'-DS-'.uniqid(), 'name' => $prefix.' Dusun', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => $prefix.'-RW-'.uniqid(), 'name' => $prefix.' RW', 'is_active' => true]);

        return Rt::query()->create(['rw_id' => $rw->id, 'code' => $prefix.'-RT-'.uniqid(), 'name' => $prefix.' RT', 'is_active' => true]);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'resource-'.uniqid(), 'description' => 'Resource test']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
