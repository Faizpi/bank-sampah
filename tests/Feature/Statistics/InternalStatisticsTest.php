<?php

declare(strict_types=1);

namespace Tests\Feature\Statistics;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Models\DepositItem;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Statistics\Services\StatisticsService;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
