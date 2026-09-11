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
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\WasteMaster\Actions\ManageWastePricing;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Filament\Resources\Communication\Models\Announcements\Pages\ManageAnnouncements;
use App\Filament\Resources\CustomersRegions\Models\Rts\Pages\ManageRts;
use App\Filament\Resources\CustomersRegions\Models\Rws\Pages\ManageRws;
use App\Filament\Resources\CustomersRegions\Models\ServiceAreas\Pages\ManageServiceAreas;
use App\Filament\Resources\WasteMaster\Models\WastePrices\Pages\ManageWastePrices;
use App\Filament\Resources\WasteMaster\Models\WasteTypes\Pages\ManageWasteTypes;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class BackofficeValidationFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_announcement_confirmation_actions_show_domain_feedback_without_state_or_audit_changes(): void
    {
        $actor = $this->userWith('backoffice.access', 'announcement.view', 'announcement.manage', 'announcement.publish');
        $expired = Announcement::factory()->create(['audience' => AnnouncementAudience::Public, 'status' => AnnouncementStatus::Draft, 'publish_start' => now()->subHour(), 'publish_end' => now()->subMinute(), 'created_by' => $actor->id]);
        $this->actingAs($actor);

        Livewire::test(ManageAnnouncements::class)->callTableAction('publish', $expired)->assertNotified('Pengumuman belum dapat diterbitkan');

        self::assertSame(AnnouncementStatus::Draft, $expired->fresh()->status);
        self::assertDatabaseMissing('audit_logs', ['auditable_type' => Announcement::class, 'auditable_id' => $expired->id, 'action' => 'announcement.published']);
    }

    public function test_failed_region_activation_actions_show_contextual_safe_feedback_and_preserve_state(): void
    {
        $actor = $this->userWith('backoffice.access', 'region.manage');
        $dusun = Dusun::query()->create(['code' => 'D-1', 'name' => 'Dusun nonaktif', 'is_active' => false]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-1', 'name' => 'RW 1', 'is_active' => false]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-1', 'name' => 'RT 1', 'is_active' => false]);
        $area = ServiceArea::query()->create(['name' => 'Area 1', 'is_active' => false]);
        RegionMutationGuard::run(fn () => $area->rts()->attach($rt));
        $this->actingAs($actor);

        Livewire::test(ManageRws::class)->callTableAction('activate', $rw)->assertNotified('RW belum dapat diaktifkan');
        Livewire::test(ManageRts::class)->callTableAction('activate', $rt)->assertNotified('RT belum dapat diaktifkan');
        Livewire::test(ManageServiceAreas::class)->callTableAction('activate', $area)->assertNotified('Area pelayanan belum dapat diaktifkan');

        self::assertFalse($rw->fresh()->is_active);
        self::assertFalse($rt->fresh()->is_active);
        self::assertFalse($area->fresh()->is_active);
    }

    public function test_failed_waste_type_activation_shows_actionable_indonesian_feedback_and_preserves_state(): void
    {
        $actor = $this->userWith('backoffice.access', 'waste.manage');
        $category = WasteCategory::factory()->create(['is_active' => false]);
        $unit = WasteUnit::factory()->weight('1.000000')->create(['is_active' => true]);
        $condition = WasteCondition::factory()->create(['is_active' => true]);
        $type = WasteType::factory()->create(['waste_category_id' => $category->id, 'waste_unit_id' => $unit->id, 'is_active' => false]);
        WasteMasterMutationGuard::run(fn () => $type->conditions()->attach($condition));
        $this->actingAs($actor);

        Livewire::test(ManageWasteTypes::class)->callTableAction('activate', $type)->assertNotified('Jenis sampah belum dapat diaktifkan');

        self::assertFalse($type->fresh()->is_active);
    }

    public function test_overlapping_waste_price_create_keeps_form_error_and_does_not_create_or_audit(): void
    {
        $actor = $this->userWith('backoffice.access', 'price.manage');
        $category = WasteCategory::factory()->create(['is_active' => true]);
        $unit = WasteUnit::factory()->weight('1.000000')->create(['is_active' => true]);
        $condition = WasteCondition::factory()->create(['is_active' => true]);
        $type = WasteType::factory()->create(['waste_category_id' => $category->id, 'waste_unit_id' => $unit->id, 'is_active' => true]);
        WasteMasterMutationGuard::run(fn () => $type->conditions()->attach($condition));
        app(ManageWastePricing::class)->createPeriod($actor, $type, $condition, 5000, CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-08-31'), '11111111-1111-4111-8111-111111111111');
        $this->actingAs($actor);
        $pricesBefore = \DB::table('waste_prices')->where('waste_type_id', $type->id)->count();
        $auditsBefore = (int) \DB::table('audit_logs')->where('action', 'waste.price.created')->count();

        $component = Livewire::test(ManageWastePrices::class);
        $component->callAction('create', data: [
            'waste_type_id' => $type->id,
            'waste_condition_id' => $condition->id,
            'price' => 6000,
            'effective_from' => '2026-08-15 00:00:00',
            'effective_to' => '2026-09-15 00:00:00',
            'zero_price_confirmed' => false,
        ]);
        $component->assertHasErrors(['effective_from']);
        $notifications = array_merge(session('filament.notifications', []), session('filament.claimed_notifications', []));
        self::assertCount(1, $notifications);
        $component->assertNotified('Harga sampah belum dapat disimpan');
        self::assertSame($pricesBefore, \DB::table('waste_prices')->where('waste_type_id', $type->id)->count());
        self::assertSame($auditsBefore, (int) \DB::table('audit_logs')->where('action', 'waste.price.created')->count());
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'feedback-'.uniqid(), 'description' => 'Feedback test']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
