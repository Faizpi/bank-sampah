<?php

declare(strict_types=1);

namespace Tests\Feature\Wave9;

use App\Domain\Corrections\Models\TransactionCorrection;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Models\DepositItem;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Models\TargetScope;
use App\Domain\Programs\Services\TargetProgressService;
use App\Domain\Reports\Services\ReportQueryService;
use App\Domain\Statistics\Services\StatisticsService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F-43 regression: report/statistics aggregation must stay bounded in memory while
 * preserving exact metrics, filters, corrected deposit values and integer money/weights.
 */
final class ReportStatisticsBoundedAggregationTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD = ['start' => '2026-08-01', 'end' => '2026-09-01'];

    public function test_deposit_aggregate_matches_naive_metric_on_weight_integer_value_and_subject_count(): void
    {
        $actor = $this->userWith('report.view', 'user.view.all');
        $condition = WasteCondition::factory()->create();
        $plastic = WasteType::factory()->create(['is_plastic' => true]);
        $nonPlastic = WasteType::factory()->create(['is_plastic' => false]);
        // Two deposits for one customer to prove distinct subject counting is preserved.
        $this->seedDeposit($actor, $actor, $plastic, $condition, '12.345', 12_345, 'DEP-BOUND-1');
        $this->seedDeposit($actor, $actor, $nonPlastic, $condition, '0.005', 5, 'DEP-BOUND-2');
        $other = User::factory()->create();
        $this->seedDeposit($other, $actor, $plastic, $condition, '1.000', 1_000, 'DEP-BOUND-3');

        $metrics = app(ReportQueryService::class)->aggregate($actor, self::PERIOD, 'deposits');

        self::assertSame([
            'subject_count' => 2,
            'deposit_count' => 3,
            'total_weight_kg' => '13.350',
            'total_value' => 13_350,
            'plastic_weight_kg' => '13.345',
        ], $metrics);
        // Money stays an int, never a float.
        self::assertIsInt($metrics['total_value']);
    }

    public function test_deposit_aggregate_keeps_effective_value_after_correction(): void
    {
        $actor = $this->userWith('report.view', 'user.view.all');
        $condition = WasteCondition::factory()->create();
        $type = WasteType::factory()->create();
        $deposit = $this->seedDeposit($actor, $actor, $type, $condition, '1.000', 10_000, 'DEP-BOUND-CORR');
        TransactionCorrection::query()->create([
            'correction_number' => 'COR-BOUND-1', 'deposit_id' => $deposit->id, 'reason' => 'Koreksi nilai resmi untuk uji batas.',
            'before_values' => ['total_value' => 10_000], 'after_values' => ['total_value' => 7_000], 'delta_value' => -3_000,
            'status' => 'final', 'created_by' => $actor->id, 'finalized_at' => '2026-08-01 12:00:00',
        ]);
        $deposit->forceFill(['status' => Deposit::STATUS_CORRECTED])->save();

        self::assertSame(7_000, app(ReportQueryService::class)->aggregate($actor, self::PERIOD, 'deposits')['total_value']);
        self::assertSame(7_000, app(ReportQueryService::class)->summary($actor, 'participation', self::PERIOD)['collected_value']);
    }

    public function test_summary_uses_exact_sql_aggregates_for_every_non_deposit_type(): void
    {
        $actor = $this->userWith('report.view', 'user.view.all');
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();

        $this->seedWithdrawal($customer, $actor, 15_000, 'WDR-BOUND-1');
        $this->seedWithdrawal($otherCustomer, $actor, 20_000, 'WDR-BOUND-2');
        $this->seedWithdrawal($otherCustomer, $actor, 5_000, 'WDR-BOUND-3');

        $package = GroceryPackage::query()->create(['code' => 'PKG-BOUND', 'name' => 'Paket Bound', 'contents' => 'Isi', 'value' => 30_000, 'status' => 'aktif']);
        $this->seedGrocery($customer, $actor, $package, 25_000, 'GRC-BOUND-1');
        $this->seedGrocery($otherCustomer, $actor, $package, 31_000, 'GRC-BOUND-2');

        [$rt, $area] = $this->pickupRegion();
        $this->seedPickup($customer, $rt, $area, 'PUP-BOUND-1', '2.500');
        $this->seedPickup($otherCustomer, $rt, $area, 'PUP-BOUND-2', '5.125');
        $this->seedPickup($otherCustomer, $rt, $area, 'PUP-BOUND-3', '0.375');

        $reports = app(ReportQueryService::class);
        self::assertSame(['customer_count' => 2, 'withdrawal_count' => 3, 'total_amount' => 40_000], $reports->summary($actor, 'withdrawals', self::PERIOD));
        self::assertSame(['customer_count' => 2, 'redemption_count' => 2, 'total_redeemed_value' => 56_000], $reports->summary($actor, 'groceries', self::PERIOD));
        self::assertSame(['customer_count' => 2, 'pickup_count' => 3, 'estimated_weight_kg' => '8.000'], $reports->summary($actor, 'pickups', self::PERIOD));
    }

    public function test_statistics_aggregate_counts_and_grams_are_exact_for_bulk_deposits(): void
    {
        config(['app.statistics_privacy_threshold' => 1]);
        $actor = $this->userWith('statistics.internal.view', 'user.view.all');
        $condition = WasteCondition::factory()->create();
        $plastic = WasteType::factory()->create(['name' => 'Plastik', 'is_plastic' => true]);
        // 250 deposits * 1.234 kg = 308.500 kg, forcing the chunked stream to iterate more than once.
        foreach (range(1, 250) as $number) {
            $customer = User::factory()->create();
            CustomerProfile::factory()->for($customer)->create();
            $this->seedStatisticsDeposit($customer, $actor, $plastic, $condition, '1.234', 'DEP-STATS-BULK-'.$number);
        }

        $internal = app(StatisticsService::class)->internal($actor, '2026-08-01', '2026-08-02');

        self::assertFalse($internal['suppressed']);
        self::assertSame(250, $internal['deposit_count']);
        self::assertSame(250, $internal['active_customers']);
        self::assertSame('308.500', $internal['total_weight_kg']);
        self::assertSame('308.500', $internal['plastic_weight_kg']);
        self::assertSame('Plastik', $internal['dominant_waste_type']);
    }

    public function test_statistics_dominant_type_uses_gram_scaled_ranking_not_float_drift(): void
    {
        config(['app.statistics_privacy_threshold' => 1]);
        $actor = $this->userWith('statistics.internal.view', 'user.view.all');
        $condition = WasteCondition::factory()->create();
        $heavy = WasteType::factory()->create(['name' => 'Berat', 'is_plastic' => false]);
        $light = WasteType::factory()->create(['name' => 'Ringan', 'is_plastic' => false]);
        $customer = User::factory()->create();
        CustomerProfile::factory()->for($customer)->create();
        $this->seedStatisticsDeposit($customer, $actor, $heavy, $condition, '2.000', 'DEP-STATS-DOM-1');
        $this->seedStatisticsDeposit($customer, $actor, $light, $condition, '1.000', 'DEP-STATS-DOM-2');

        self::assertSame('Berat', app(StatisticsService::class)->internal($actor, '2026-08-01', '2026-08-02')['dominant_waste_type']);
    }

    public function test_export_row_limit_counts_flattened_items_matching_the_export_definition(): void
    {
        $actor = $this->userWith('report.view', 'report.export', 'user.view.all');
        $condition = WasteCondition::factory()->create();
        $type = WasteType::factory()->create();
        // Three deposits, one item each = three flattened rows.
        foreach (range(1, 3) as $number) {
            $this->seedMultiItemDeposit($actor, $type, $condition, 'DEP-EXPORT-BOUND-'.$number, 1);
        }

        $expected = DB::table('deposit_items')->count();
        self::assertSame(3, $expected);

        $collection = app(ReportQueryService::class)->streamRecords($actor, 'deposits', self::PERIOD);
        $exportRows = 0;
        foreach ($collection as $record) {
            $exportRows += $record->items->count();
        }
        self::assertSame($expected, $exportRows);
    }

    public function test_target_progress_aggregate_many_stays_exact_across_streamed_chunks(): void
    {
        $actor = $this->userWith('user.view.all');
        $condition = WasteCondition::factory()->create();
        $type = WasteType::factory()->create(['is_plastic' => true]);
        [$rt] = $this->pickupRegion();

        $target = CollectionTarget::query()->create([
            'target_number' => 'TGT-BOUND-1', 'name' => 'Target Bound', 'purpose' => 'Uji batas',
            'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'target_weight_kg' => '100.000',
            'status' => TargetStatus::Active, 'is_public' => true, 'public_min_subjects' => 1, 'created_by' => $actor->id,
        ]);
        TargetScope::query()->create(['collection_target_id' => $target->id, 'rt_id' => $rt->id, 'waste_type_id' => $type->id]);

        // 220 deposits * 0.5 kg = 110.000 kg across more than one streamed chunk.
        foreach (range(1, 220) as $number) {
            $customer = User::factory()->create();
            CustomerProfile::factory()->for($customer)->create(['rt_id' => $rt->id]);
            $deposit = Deposit::query()->create([
                'deposit_number' => 'DEP-TGT-BOUND-'.$number, 'customer_id' => $customer->id, 'staff_id' => $actor->id,
                'method' => 'langsung', 'occurred_at' => '2026-08-10 10:00:00', 'status' => Deposit::STATUS_FINAL,
                'total_weight_kg' => '0.500', 'total_value' => 500, 'finalized_at' => '2026-08-10 10:00:00',
            ]);
            DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '0.500']);
        }

        $aggregate = app(TargetProgressService::class)->aggregateMany([$target->load('scopes')]);
        $result = $aggregate[$target->id];

        self::assertSame('110.000', $result['weight_kg']);
        self::assertSame(220, $result['deposit_count']);
        self::assertSame(220, $result['subject_count']);
        self::assertSame('110.000', $result['plastic_weight_kg']);
    }

    private function seedDeposit(User $customer, User $staff, WasteType $type, WasteCondition $condition, string $weight, int $value, string $number, string $occurredAt = '2026-08-01 10:00:00'): Deposit
    {
        $deposit = Deposit::query()->create([
            'deposit_number' => $number, 'customer_id' => $customer->id, 'staff_id' => $staff->id,
            'method' => 'loket', 'occurred_at' => $occurredAt, 'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => $weight, 'total_value' => $value, 'finalized_at' => $occurredAt,
        ]);
        DepositItem::query()->create([
            'deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id,
            'weight_kg' => $weight, 'price_per_unit' => 1_000, 'subtotal' => $value,
        ]);

        return $deposit;
    }

    private function seedMultiItemDeposit(User $customer, WasteType $type, WasteCondition $condition, string $number, int $itemCount): Deposit
    {
        $deposit = Deposit::query()->create([
            'deposit_number' => $number, 'customer_id' => $customer->id, 'staff_id' => $customer->id,
            'method' => 'loket', 'occurred_at' => '2026-08-01 10:00:00', 'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.000', 'total_value' => 1_000, 'finalized_at' => '2026-08-01 10:00:00',
        ]);
        foreach (range(1, $itemCount) as $_) {
            DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.000']);
        }

        return $deposit;
    }

    private function seedStatisticsDeposit(User $customer, User $actor, WasteType $type, WasteCondition $condition, string $weight, string $number): Deposit
    {
        $deposit = Deposit::query()->create([
            'deposit_number' => $number, 'customer_id' => $customer->id, 'staff_id' => $actor->id,
            'method' => 'langsung', 'occurred_at' => '2026-08-01 10:00:00', 'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => $weight, 'total_value' => 1_000, 'finalized_at' => '2026-08-01 10:00:00',
        ]);
        DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => $weight]);

        return $deposit;
    }

    private function seedWithdrawal(User $customer, User $requestedBy, int $amount, string $number): WithdrawalRequest
    {
        return WithdrawalRequest::query()->create([
            'request_number' => $number, 'customer_id' => $customer->id, 'requested_by_id' => $requestedBy->id,
            'amount' => $amount, 'status' => WithdrawalStatus::Paid, 'paid_at' => '2026-08-01 11:00:00',
        ]);
    }

    private function seedGrocery(User $customer, User $requestedBy, GroceryPackage $package, int $value, string $number): GroceryRedemption
    {
        return GroceryRedemption::query()->create([
            'request_number' => $number, 'customer_id' => $customer->id, 'requested_by_id' => $requestedBy->id,
            'grocery_package_id' => $package->id, 'value_snapshot' => $value,
            'package_snapshot' => ['code' => $package->code, 'value' => $value], 'status' => GroceryStatus::Completed,
            'handed_over_at' => '2026-08-01 12:00:00',
        ]);
    }

    /** @return array{0: Rt, 1: ServiceArea} */
    private function pickupRegion(): array
    {
        $dusun = Dusun::query()->create(['code' => 'DS-BOUND', 'name' => 'Dusun Bound', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-BOUND', 'name' => 'RW Bound', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-BOUND', 'name' => 'RT Bound', 'is_active' => true]);
        $area = ServiceArea::query()->create(['name' => 'Area Bound', 'is_active' => true]);

        return [$rt, $area];
    }

    private function seedPickup(User $customer, Rt $rt, ServiceArea $area, string $number, string $weight): PickupRequest
    {
        return PickupRequest::query()->create([
            'request_number' => $number, 'customer_id' => $customer->id, 'rt_id' => $rt->id, 'service_area_id' => $area->id,
            'address' => 'Alamat Bound', 'selected_date' => '2026-08-01', 'estimated_weight_kg' => $weight,
            'status' => PickupStatus::Completed, 'completed_at' => '2026-08-01 13:00:00',
        ]);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'report-bound-'.$user->id, 'description' => 'Bounded aggregation tests']);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
