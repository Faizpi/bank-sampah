<?php

declare(strict_types=1);

namespace Tests\Feature\Statistics;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Models\DepositItem;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Models\TargetScope;
use App\Domain\Statistics\Services\StatisticsService;
use App\Livewire\PublicSite\PublicPrograms;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class InternalStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_and_public_statistics_calculate_every_configurable_metric(): void
    {
        config(['app.statistics_privacy_threshold' => 1]);
        $actor = $this->userWith('statistics.internal.view', 'statistics.public.manage', 'user.view.all', 'waste.manage');
        $customer = User::factory()->create();
        CustomerProfile::factory()->for($customer)->create();
        [$type, $condition] = $this->wasteType($actor);
        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-STATS-001',
            'customer_id' => $customer->id,
            'staff_id' => $actor->id,
            'method' => 'langsung',
            'occurred_at' => '2026-08-01 10:00:00',
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.250',
            'total_value' => 12_000,
            'finalized_at' => '2026-08-01 10:00:00',
        ]);
        DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.250']);
        $customerTwo = User::factory()->create();
        CustomerProfile::factory()->for($customerTwo)->create();
        $depositTwo = Deposit::query()->create([
            'deposit_number' => 'DEP-STATS-002',
            'customer_id' => $customerTwo->id,
            'staff_id' => $actor->id,
            'method' => 'langsung',
            'occurred_at' => '2026-08-01 11:00:00',
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.250',
            'total_value' => 12_000,
            'finalized_at' => '2026-08-01 11:00:00',
        ]);
        DepositItem::query()->create(['deposit_id' => $depositTwo->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.250']);
        CollectionTarget::query()->create([
            'target_number' => 'TGT-STATS-001',
            'name' => 'Target statistik',
            'purpose' => 'Uji metrik target',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'target_weight_kg' => '10.000',
            'status' => TargetStatus::Active,
            'is_public' => true,
            'public_min_subjects' => 1,
            'created_by' => $actor->id,
        ]);
        MobileService::query()->create([
            'service_number' => 'MOB-STATS-001',
            'point' => 'Titik statistik',
            'starts_at' => '2026-08-01 08:00:00',
            'ends_at' => '2026-08-01 12:00:00',
            'status' => MobileServiceStatus::Closed,
            'capacity' => 20,
            'served_count' => 1,
            'created_by' => $actor->id,
        ]);

        $statistics = app(StatisticsService::class);
        $internal = $statistics->internal($actor, '2026-08-01', '2026-08-02');

        self::assertFalse($internal['suppressed']);
        self::assertSame(2, $internal['active_customers']);
        self::assertSame(2, $internal['deposit_count']);
        self::assertSame('2.500', $internal['total_weight_kg']);
        self::assertSame('2.500', $internal['plastic_weight_kg']);
        self::assertSame('Plastik', $internal['dominant_waste_type']);
        self::assertSame('2.500', $internal['target_progress_kg']);
        self::assertSame(1, $internal['mobile_service_count']);

        $statistics->configurePublic($actor, ['active_customers', 'target_progress_kg', 'mobile_service_count'], ['period'], 2, true);
        $public = $statistics->public('2026-08-01', '2026-08-02');

        self::assertFalse($public['suppressed']);
        self::assertSame(['active_customers' => 2, 'target_progress_kg' => '2.500', 'mobile_service_count' => 1], $public['metrics']);
    }

    public function test_public_statistics_uses_configured_rt_aggregation_and_includes_period_metadata(): void
    {
        $actor = $this->userWith('statistics.public.manage', 'waste.manage');
        $dusun = Dusun::query()->create(['code' => 'STATS-DS-1', 'name' => 'Dusun Statistik', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'STATS-RW-1', 'name' => 'RW Statistik', 'is_active' => true]);
        $selectedRt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'STATS-RT-1', 'name' => 'RT Terpilih', 'is_active' => true]);
        $otherRt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'STATS-RT-2', 'name' => 'RT Lain', 'is_active' => true]);
        [$type, $condition] = $this->wasteType($actor);

        foreach ([[$selectedRt, 'DEP-RT-SELECTED-1'], [$selectedRt, 'DEP-RT-SELECTED-2'], [$otherRt, 'DEP-RT-OTHER']] as [$rt, $number]) {
            $customer = User::factory()->create();
            CustomerProfile::factory()->for($customer)->create(['rt_id' => $rt->id]);
            $deposit = Deposit::query()->create(['deposit_number' => $number, 'customer_id' => $customer->id, 'staff_id' => $actor->id, 'method' => 'langsung', 'occurred_at' => '2026-08-01 10:00:00', 'status' => Deposit::STATUS_FINAL, 'total_weight_kg' => '1.000', 'total_value' => 1_000, 'finalized_at' => '2026-08-01 10:00:00']);
            DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.000']);
        }

        app(StatisticsService::class)->configurePublic($actor, ['deposit_count', 'total_weight_kg'], ['period', 'rt_id'], 2, true);
        $result = app(StatisticsService::class)->public('2026-08-01', '2026-08-02', $selectedRt->id);

        self::assertFalse($result['suppressed']);
        self::assertSame(['deposit_count' => 2, 'total_weight_kg' => '2.000'], $result['metrics']);
        self::assertSame(['start' => '2026-08-01', 'end' => '2026-08-02'], $result['period']);
        self::assertSame($selectedRt->id, $result['rt_id']);
    }

    public function test_public_programs_render_human_readable_scope_date_range_metric_labels_and_weight_units(): void
    {
        $actor = $this->userWith('statistics.public.manage', 'waste.manage');
        $dusun = Dusun::query()->create(['code' => 'PROGRAM-DS-1', 'name' => 'Dusun Program', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'PROGRAM-RW-1', 'name' => 'RW Program', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'PROGRAM-RT-1', 'name' => 'RT Program', 'is_active' => true]);
        [$type] = $this->wasteType($actor);
        $target = CollectionTarget::query()->create(['target_number' => 'TGT-PUBLIC-1', 'name' => 'Target publik', 'purpose' => 'Pengumpulan terarah', 'period_start' => today()->subDay(), 'period_end' => today()->addDay(), 'target_weight_kg' => '5.000', 'status' => TargetStatus::Active, 'is_public' => true, 'public_min_subjects' => 0, 'created_by' => $actor->id]);
        TargetScope::query()->create(['collection_target_id' => $target->id, 'rt_id' => $rt->id, 'waste_type_id' => $type->id]);
        foreach (['DEP-PUBLIC-PROGRAM-1', 'DEP-PUBLIC-PROGRAM-2'] as $number) {
            $customer = User::factory()->create();
            Deposit::query()->create(['deposit_number' => $number, 'customer_id' => $customer->id, 'staff_id' => $actor->id, 'method' => 'langsung', 'occurred_at' => now(), 'status' => Deposit::STATUS_FINAL, 'total_weight_kg' => '0.000', 'total_value' => 0, 'finalized_at' => now()]);
        }
        app(StatisticsService::class)->configurePublic($actor, ['total_weight_kg'], ['period'], 2, true);

        Livewire::test(PublicPrograms::class)
            ->assertSee('Cakupan:')
            ->assertSee('RT Program')
            ->assertSee($type->name)
            ->assertSee('Periode 12 bulan:')
            ->assertSee('Total berat')
            ->assertDontSee('Total weight kg');
    }

    public function test_public_programs_applies_selected_rt_to_public_statistics_only_when_configured(): void
    {
        $actor = $this->userWith('statistics.public.manage', 'waste.manage');
        $dusun = Dusun::query()->create(['code' => 'FILTER-DS-1', 'name' => 'Dusun Filter', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'FILTER-RW-1', 'name' => 'RW Filter', 'is_active' => true]);
        $firstRt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'FILTER-RT-1', 'name' => 'RT Satu', 'is_active' => true]);
        $secondRt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'FILTER-RT-2', 'name' => 'RT Dua', 'is_active' => true]);
        [$type, $condition] = $this->wasteType($actor);

        foreach ([[$firstRt, '1.000', 'SATU-1'], [$firstRt, '1.000', 'SATU-2'], [$secondRt, '2.000', 'DUA-1'], [$secondRt, '2.000', 'DUA-2']] as [$rt, $weight, $suffix]) {
            $customer = User::factory()->create();
            CustomerProfile::factory()->for($customer)->create(['rt_id' => $rt->id]);
            $deposit = Deposit::query()->create(['deposit_number' => 'DEP-FILTER-'.$suffix, 'customer_id' => $customer->id, 'staff_id' => $actor->id, 'method' => 'langsung', 'occurred_at' => now(), 'status' => Deposit::STATUS_FINAL, 'total_weight_kg' => $weight, 'total_value' => 1_000, 'finalized_at' => now()]);
            DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => $weight]);
        }

        app(StatisticsService::class)->configurePublic($actor, ['total_weight_kg'], ['period', 'rt_id'], 2, true);

        Livewire::test(PublicPrograms::class)
            ->assertSee('Cakupan statistik')
            ->assertSee('Seluruh desa')
            ->assertSee('RT Satu')
            ->assertSee('RT Dua')
            ->set('rtId', (string) $firstRt->id)
            ->assertSee('Menampilkan agregat untuk RT terpilih.')
            ->assertSee('2 kg')
            ->set('rtId', (string) $secondRt->id)
            ->assertSee('4 kg');

        app(StatisticsService::class)->configurePublic($actor, ['total_weight_kg'], ['period'], 2, true);

        Livewire::test(PublicPrograms::class)
            ->assertDontSee('Cakupan statistik')
            ->assertSee('6 kg');
    }

    public function test_internal_statistics_suppression_clears_new_metrics_too(): void
    {
        $actor = $this->userWith('statistics.internal.view', 'user.view.all');
        $customer = User::factory()->create();
        CustomerProfile::factory()->for($customer)->create();
        config(['app.statistics_privacy_threshold' => 5]);

        $result = app(StatisticsService::class)->internal($actor, '2026-08-01', '2026-08-02');

        self::assertTrue($result['suppressed']);
        self::assertNull($result['active_customers']);
        self::assertNull($result['target_progress_kg']);
        self::assertNull($result['mobile_service_count']);
    }

    /** @return array{WasteType, WasteCondition} */
    private function wasteType(User $actor): array
    {
        $master = app(ManageWasteMaster::class);
        $category = $master->createCategory($actor, 'STATS-CAT-'.$actor->id, 'Statistik');
        $unit = $master->createUnit($actor, 'STATS-UNIT-'.$actor->id, 'Kilogram', 'kg', WasteUnit::CLASSIFICATION_WEIGHT, '1.000000');
        $condition = $master->createCondition($actor, 'STATS-COND-'.$actor->id, 'Bersih', null);
        $type = $master->createType($actor, $category, $unit, 'STATS-TYPE-'.$actor->id, 'Plastik', null, 0, true, true, [$condition->id]);

        return [$type, $condition];
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'stats-test-'.$user->id, 'description' => 'Statistics test']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
