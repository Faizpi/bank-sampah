<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Communication\Enums\AnnouncementAudience;
use App\Domain\Communication\Enums\AnnouncementStatus;
use App\Domain\Communication\Models\Announcement;
use App\Domain\Communication\Services\AnnouncementService;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Services\TargetProgressService;
use App\Domain\Programs\Services\TargetService;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Filament\Resources\Communication\Models\Announcements\AnnouncementResource;
use App\Filament\Resources\Communication\Models\Announcements\Pages\ManageAnnouncements;
use App\Filament\Resources\Programs\Models\CollectionTargets\CollectionTargetResource;
use App\Filament\Resources\Programs\Models\CollectionTargets\Pages\ManageCollectionTargets;
use App\Filament\Resources\Statistics\Models\StatisticPublications\Pages\ManageStatisticPublications;
use App\Filament\Resources\Statistics\Models\StatisticPublications\StatisticPublicationResource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminOperationsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_resources_are_permission_gated_and_have_management_pages(): void
    {
        $admin = $this->userWith('backoffice.access', 'announcement.manage', 'announcement.publish', 'target.manage', 'target.publish', 'statistics.public.manage', 'waste.manage');
        $viewer = $this->userWith('backoffice.access');

        $this->actingAs($admin);
        self::assertTrue(AnnouncementResource::canViewAny());
        self::assertTrue(CollectionTargetResource::canViewAny());
        self::assertTrue(StatisticPublicationResource::canViewAny());
        self::assertSame(['index'], array_keys(AnnouncementResource::getPages()));
        self::assertSame(['index'], array_keys(CollectionTargetResource::getPages()));
        self::assertSame(['index'], array_keys(StatisticPublicationResource::getPages()));

        $this->actingAs($viewer);
        self::assertFalse(AnnouncementResource::canViewAny());
        self::assertFalse(CollectionTargetResource::canViewAny());
        self::assertFalse(StatisticPublicationResource::canViewAny());
        self::assertSame(ManageAnnouncements::class, AnnouncementResource::getPages()['index']->getPage());
        self::assertSame(ManageCollectionTargets::class, CollectionTargetResource::getPages()['index']->getPage());
        self::assertSame(ManageStatisticPublications::class, StatisticPublicationResource::getPages()['index']->getPage());
    }

    public function test_announcement_resource_creates_public_only_and_soft_deletes_with_permission(): void
    {
        $admin = $this->userWith('backoffice.access', 'announcement.view', 'announcement.manage');
        $this->actingAs($admin);

        Livewire::test(ManageAnnouncements::class)
            ->callAction('create', data: [
                'title' => 'Layanan untuk seluruh warga',
                'body' => 'Informasi layanan terbuka untuk publik.',
                'publish_start' => now()->toDateTimeString(),
                'publish_end' => null,
                'priority' => 1,
            ]);

        $announcement = Announcement::query()->where('title', 'Layanan untuk seluruh warga')->firstOrFail();
        self::assertSame(AnnouncementAudience::Public, $announcement->audience);

        Livewire::test(ManageAnnouncements::class)
            ->assertTableActionVisible('delete', $announcement)
            ->callTableAction('delete', $announcement);

        self::assertSoftDeleted('announcements', ['id' => $announcement->id]);
        self::assertFalse(app(AnnouncementService::class)->publicQuery()->whereKey($announcement->id)->exists());
        self::assertDatabaseHas('audit_logs', ['action' => 'announcement.deleted', 'auditable_id' => $announcement->id, 'actor_id' => $admin->id]);
    }

    public function test_announcement_soft_delete_preserves_historical_rt_pivot(): void
    {
        $admin = $this->userWith('announcement.manage');
        $rt = $this->createRt('ANNOUNCEMENT-HISTORY');
        $announcement = Announcement::factory()->create(['audience' => AnnouncementAudience::Public, 'created_by' => $admin->id]);
        $announcement->rts()->attach($rt);

        app(AnnouncementService::class)->delete($admin, $announcement);

        self::assertDatabaseHas('announcement_rt', ['announcement_id' => $announcement->id, 'rt_id' => $rt->id]);
    }

    public function test_announcement_service_rejects_delete_without_manage_permission(): void
    {
        $viewer = $this->userWith('announcement.view');
        $announcement = Announcement::factory()->create(['audience' => AnnouncementAudience::Public]);

        $this->expectException(AuthorizationException::class);
        app(AnnouncementService::class)->delete($viewer, $announcement);
    }

    public function test_announcement_delete_is_hidden_without_manage_permission(): void
    {
        $viewer = $this->userWith('backoffice.access', 'announcement.view');
        $announcement = Announcement::factory()->create(['audience' => AnnouncementAudience::Public]);
        $this->actingAs($viewer);

        Livewire::test(ManageAnnouncements::class)
            ->assertTableActionHidden('delete', $announcement);
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

    public function test_announcement_edit_action_does_not_expose_audience_or_rt_targets(): void
    {
        $admin = $this->userWith('backoffice.access', 'announcement.view', 'announcement.manage');
        $announcement = Announcement::factory()->create([
            'audience' => AnnouncementAudience::Public,
            'status' => AnnouncementStatus::Draft,
            'created_by' => $admin->id,
        ]);
        $this->actingAs($admin);

        Livewire::test(ManageAnnouncements::class)
            ->mountTableAction('edit', $announcement)
            ->assertTableActionDataSet([
                'title' => $announcement->title,
                'body' => $announcement->body,
            ]);
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

    public function test_target_progress_scoped_to_waste_type_counts_only_matching_items_in_mixed_deposit(): void
    {
        $admin = $this->userWith('target.view', 'target.manage', 'target.publish', 'waste.manage');
        $category = WasteCategory::factory()->create();
        $unit = WasteUnit::factory()->weight('1.000000')->create();
        $condition = WasteCondition::factory()->create();
        $matchingType = WasteType::factory()->create(['waste_category_id' => $category->id, 'waste_unit_id' => $unit->id, 'is_plastic' => true]);
        $nonMatchingType = WasteType::factory()->create(['waste_category_id' => $category->id, 'waste_unit_id' => $unit->id, 'is_plastic' => false]);
        $target = CollectionTarget::query()->create(['target_number' => 'TGT-SCOPED-'.uniqid(), 'name' => 'Target plastik terpilih', 'purpose' => 'Uji lingkup jenis sampah', 'period_start' => today()->subDay(), 'period_end' => today()->addDay(), 'target_weight_kg' => '10.000', 'status' => TargetStatus::Active, 'is_public' => false, 'created_by' => $admin->id]);
        $target->scopes()->create(['waste_type_id' => $matchingType->id]);
        $customer = User::factory()->create();
        $deposit = Deposit::query()->create(['deposit_number' => 'DEP-SCOPED-'.uniqid(), 'customer_id' => $customer->id, 'staff_id' => $admin->id, 'method' => 'loket', 'occurred_at' => now(), 'status' => Deposit::STATUS_FINAL]);
        $deposit->items()->create(['waste_type_id' => $matchingType->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.250']);
        $deposit->items()->create(['waste_type_id' => $nonMatchingType->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '3.750']);

        $aggregate = app(TargetProgressService::class)->aggregate($target->load('scopes'));

        self::assertSame('1.250', $aggregate['weight_kg']);
        self::assertSame(1, $aggregate['deposit_count']);
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

    public function test_target_resource_shows_validation_error_when_create_is_rejected(): void
    {
        $admin = $this->userWith('backoffice.access', 'target.view', 'target.manage');
        $this->actingAs($admin);

        Livewire::test(ManageCollectionTargets::class)
            ->callAction('create', data: [
                'name' => 'Target internal',
                'purpose' => 'Pengumpulan khusus wilayah',
                'period_start' => today()->toDateString(),
                'period_end' => today()->addDays(30)->toDateString(),
                'target_weight_kg' => '10.000',
                'is_public' => false,
                'scopes' => [],
            ])
            ->assertNotified('Target tidak dapat dibuat');

        self::assertDatabaseMissing('collection_targets', ['name' => 'Target internal']);
    }

    public function test_target_resource_shows_overlap_error_and_keeps_target_as_draft(): void
    {
        $admin = $this->userWith('backoffice.access', 'target.view', 'target.manage', 'target.publish');
        $service = app(TargetService::class);
        $active = $service->create($admin, 'Target aktif', 'Pengumpulan plastik aktif', today()->toDateString(), today()->addDays(30)->toDateString(), '10.000', true, []);
        $draft = $service->create($admin, 'Target bertabrakan', 'Pengumpulan plastik bertabrakan', today()->addDay()->toDateString(), today()->addDays(20)->toDateString(), '12.000', true, []);
        $service->activate($admin, $active);

        $this->actingAs($admin);
        Livewire::test(ManageCollectionTargets::class)
            ->assertTableActionVisible('activate', $draft)
            ->callTableAction('activate', $draft)
            ->assertNotified('Target belum dapat diterbitkan');

        self::assertSame(TargetStatus::Draft, $draft->fresh()->status);
        self::assertNull($draft->fresh()->published_by);
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
