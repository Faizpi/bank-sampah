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
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Reports\Services\ReportQueryService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Livewire\Treasurer\Reports as TreasurerReports;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

final class ReportSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_report_type_exposes_an_explicit_summary_contract(): void
    {
        $actor = $this->userWith('report.view');
        $reports = app(ReportQueryService::class);
        $contracts = $this->summaryContracts();

        foreach ($contracts as $reportType => $contract) {
            self::assertSame($contract, $reports->summaryContract($reportType));
        }

        self::assertSame($contracts, $reports->contract()['summary']);
        self::assertSame([
            'subject_count', 'deposit_count', 'total_weight_kg', 'total_value', 'plastic_weight_kg',
        ], array_column($reports->summaryContract('deposits'), 'key'));
    }

    public function test_all_report_types_return_meaningful_filtered_summary_values(): void
    {
        $actor = $this->userWith('report.view', 'user.view.all');
        $otherCustomer = User::factory()->create();
        $period = ['start' => '2026-08-01', 'end' => '2026-08-02'];
        $condition = WasteCondition::factory()->create();
        $plastic = WasteType::factory()->create(['is_plastic' => true]);
        $nonPlastic = WasteType::factory()->create(['is_plastic' => false]);
        $this->seedDeposit($actor, $actor, 10_000, 'DEP-SEMANTICS-1', '1.250', $plastic, $condition);
        $this->seedDeposit($otherCustomer, $actor, 20_000, 'DEP-SEMANTICS-2', '2.750', $nonPlastic, $condition);

        $this->seedWithdrawal($actor, $actor, 15_000, 'WDR-SEMANTICS-1');
        $this->seedWithdrawal($otherCustomer, $otherCustomer, 20_000, 'WDR-SEMANTICS-2');

        $package = GroceryPackage::query()->create([
            'code' => 'PKG-SEMANTICS',
            'name' => 'Paket Semantics',
            'contents' => 'Beras dan minyak',
            'value' => 30_000,
            'status' => 'aktif',
        ]);
        $this->seedGrocery($actor, $actor, $package, 25_000, 'GRC-SEMANTICS-1');
        $this->seedGrocery($otherCustomer, $otherCustomer, $package, 30_000, 'GRC-SEMANTICS-2');

        [$rt, $area] = $this->pickupRegion();
        $this->seedPickup($actor, $rt, $area, 'PUP-SEMANTICS-1', '2.500');
        $this->seedPickup($otherCustomer, $rt, $area, 'PUP-SEMANTICS-2', '5.000');

        $reports = app(ReportQueryService::class);
        self::assertSame([
            'subject_count' => 2,
            'deposit_count' => 2,
            'total_weight_kg' => '4.000',
            'total_value' => 30_000,
            'plastic_weight_kg' => '1.250',
        ], $reports->aggregate($actor, $period, 'deposits'));
        self::assertSame([
            'customer_count' => 2,
            'withdrawal_count' => 2,
            'total_amount' => 35_000,
        ], $reports->aggregate($actor, $period, 'withdrawals'));
        self::assertSame([
            'customer_count' => 2,
            'redemption_count' => 2,
            'total_redeemed_value' => 55_000,
        ], $reports->aggregate($actor, $period, 'groceries'));
        self::assertSame([
            'customer_count' => 2,
            'pickup_count' => 2,
            'estimated_weight_kg' => '7.500',
        ], $reports->aggregate($actor, $period, 'pickups'));
        self::assertSame([
            'participant_count' => 2,
            'participation_count' => 2,
            'collected_weight_kg' => '4.000',
            'collected_value' => 30_000,
        ], $reports->aggregate($actor, $period, 'participation'));
    }

    public function test_summary_covers_the_full_filtered_dataset_beyond_the_interactive_record_limit(): void
    {
        $actor = $this->userWith('report.view', 'user.view.all');
        foreach (range(1, 101) as $number) {
            $this->seedWithdrawal($actor, $actor, 1_000, 'WDR-SEMANTICS-'.$number);
        }

        $reports = app(ReportQueryService::class);
        $period = ['start' => '2026-08-01', 'end' => '2026-08-02'];

        self::assertSame([
            'customer_count' => 1,
            'withdrawal_count' => 101,
            'total_amount' => 101_000,
        ], $reports->summary($actor, 'withdrawals', $period));
        self::assertCount(100, $reports->records($actor, 'withdrawals', $period));
    }

    public function test_deposit_reports_use_the_effective_value_after_an_official_correction(): void
    {
        $actor = $this->userWith('report.view', 'user.view.all');
        $condition = WasteCondition::factory()->create();
        $type = WasteType::factory()->create();
        $deposit = $this->seedDeposit($actor, $actor, 10_000, 'DEP-CORRECTED-REPORT', '1.000', $type, $condition);
        TransactionCorrection::query()->create([
            'correction_number' => 'COR-CORRECTED-REPORT', 'deposit_id' => $deposit->id, 'reason' => 'Nilai timbang sudah diverifikasi ulang.',
            'before_values' => ['total_value' => 10_000], 'after_values' => ['total_value' => 7_000], 'delta_value' => -3_000,
            'status' => 'final', 'created_by' => $actor->id, 'finalized_at' => '2026-08-01 12:00:00',
        ]);
        $deposit->forceFill(['status' => Deposit::STATUS_CORRECTED])->save();

        $reports = app(ReportQueryService::class);
        $period = ['start' => '2026-08-01', 'end' => '2026-08-02'];
        self::assertSame(7_000, $reports->aggregate($actor, $period, 'deposits')['total_value']);
        self::assertSame(7_000, $reports->displayRows($actor, 'deposits', $period)[0]['value']);
    }

    public function test_display_rows_expose_readable_shared_preview_fields_for_every_report_type(): void
    {
        $actor = $this->userWith('report.view', 'user.view.all');
        $customer = User::factory()->create(['name' => 'Nasabah Preview']);
        $period = ['start' => '2026-08-01', 'end' => '2026-08-02'];
        $condition = WasteCondition::factory()->create();
        $wasteType = WasteType::factory()->create();
        $this->seedDeposit($customer, $actor, 12_500, 'DEP-PREVIEW', '1.250', $wasteType, $condition);
        $this->seedWithdrawal($customer, $actor, 15_000, 'WDR-PREVIEW');
        $package = GroceryPackage::query()->create([
            'code' => 'PKG-PREVIEW',
            'name' => 'Paket Preview',
            'contents' => 'Beras dan minyak',
            'value' => 20_000,
            'status' => 'aktif',
        ]);
        $this->seedGrocery($customer, $actor, $package, 20_000, 'GRC-PREVIEW');
        [$rt, $area] = $this->pickupRegion();
        $this->seedPickup($customer, $rt, $area, 'PUP-PREVIEW', '2.500');

        $reports = app(ReportQueryService::class);
        foreach (['deposits', 'withdrawals', 'groceries', 'pickups', 'participation'] as $reportType) {
            $row = $reports->displayRows($actor, $reportType, $period)[0];

            self::assertSame(['reference', 'date', 'subject', 'detail', 'status', 'value', 'value_format'], array_keys($row));
            self::assertSame('Nasabah Preview', $row['subject']);
            self::assertNotSame((string) $customer->id, $row['subject']);
            self::assertNotSame('', $row['detail']);
        }

        self::assertSame('DEP-PREVIEW', $reports->displayRows($actor, 'deposits', $period)[0]['reference']);
        self::assertSame(12_500, $reports->displayRows($actor, 'deposits', $period)[0]['value']);
        self::assertSame('currency', $reports->displayRows($actor, 'deposits', $period)[0]['value_format']);
        self::assertSame(15_000, $reports->displayRows($actor, 'withdrawals', $period)[0]['value']);
        self::assertSame('currency', $reports->displayRows($actor, 'withdrawals', $period)[0]['value_format']);
        self::assertSame(20_000, $reports->displayRows($actor, 'groceries', $period)[0]['value']);
        self::assertSame('currency', $reports->displayRows($actor, 'groceries', $period)[0]['value_format']);
        self::assertSame('PUP-PREVIEW', $reports->displayRows($actor, 'pickups', $period)[0]['reference']);
        self::assertSame('Area Semantics · Alamat Semantics', $reports->displayRows($actor, 'pickups', $period)[0]['detail']);
        self::assertSame('2.500', $reports->displayRows($actor, 'pickups', $period)[0]['value']);
        self::assertSame('weight', $reports->displayRows($actor, 'pickups', $period)[0]['value_format']);
        Livewire::actingAs($actor);
        Livewire::test(TreasurerReports::class, ['surface' => 'backoffice'])
            ->set('reportType', 'pickups')
            ->set('period', 'custom')
            ->set('start', $period['start'])
            ->set('end', $period['end'])
            ->call('refreshReport')
            ->assertSee('2,50 kg');
        self::assertSame(12_500, $reports->displayRows($actor, 'participation', $period)[0]['value']);
        self::assertSame('currency', $reports->displayRows($actor, 'participation', $period)[0]['value_format']);
    }

    public function test_deposit_and_participation_detail_weights_always_use_two_indonesian_decimals(): void
    {
        $actor = $this->userWith('report.view', 'user.view.all');
        $condition = WasteCondition::factory()->create();
        $wasteType = WasteType::factory()->create();
        $this->seedDeposit($actor, $actor, 20_000, 'DEP-DETAIL-WEIGHT', '20.000', $wasteType, $condition, 'penjemputan');

        $reports = app(ReportQueryService::class);
        $period = ['start' => '2026-08-01', 'end' => '2026-08-02'];

        foreach (['deposits', 'participation'] as $reportType) {
            self::assertSame('20,00 kg · penjemputan', $reports->displayRows($actor, $reportType, $period)[0]['detail']);
        }
    }

    public function test_treasurer_report_uses_the_summary_contract_labels_for_each_type(): void
    {
        $actor = $this->userWith('report.view');
        $contracts = $this->summaryContracts();

        foreach ($contracts as $reportType => $contract) {
            Livewire::actingAs($actor);
            $component = Livewire::test(TreasurerReports::class, ['surface' => 'backoffice'])
                ->set('reportType', $reportType)
                ->set('start', '2026-08-01')
                ->set('end', '2026-08-02')
                ->call('refreshReport');

            $component->assertSet('metricDefinitions', $contract);
            foreach ($contract as $metric) {
                $component->assertSee($metric['label']);
            }

            if ($reportType !== 'deposits') {
                $component->assertDontSee('>Total Berat</p>', escape: false)
                    ->assertDontSee('>Total Nilai</p>', escape: false)
                    ->assertDontSee('>Plastik</p>', escape: false);
            }
        }
    }

    public function test_treasurer_report_uses_simple_period_presets_and_excel_only_export(): void
    {
        $actor = $this->userWith('report.view', 'report.export');
        Livewire::actingAs($actor);

        $component = Livewire::test(TreasurerReports::class)
            ->set('month', '01')
            ->set('year', '2026')
            ->call('setPeriod', 'month')
            ->assertSet('start', '2026-01-01')
            ->assertSet('end', '2026-01-31')
            ->set('period', 'custom')
            ->set('start', '2026-02-03')
            ->set('end', '2026-02-05')
            ->call('refreshReport')
            ->assertSet('start', '2026-02-03')
            ->assertSet('end', '2026-02-05');

        $component->assertSee('Unduh Excel')
            ->assertDontSee('CSV')
            ->assertDontSee('PDF');
    }

    public function test_report_type_choices_are_limited_by_officer_role_without_an_all_transactions_choice(): void
    {
        $treasurer = $this->userWith('report.view');
        $backoffice = $this->userWith('report.view');

        Livewire::actingAs($treasurer);
        Livewire::test(TreasurerReports::class)
            ->assertSet('reportType', 'withdrawals')
            ->assertSet('reportTypes', [
                'withdrawals' => 'Pencairan',
                'groceries' => 'Sembako',
            ]);

        Livewire::actingAs($backoffice);
        Livewire::test(TreasurerReports::class, ['surface' => 'backoffice'])
            ->assertSet('reportType', 'deposits')
            ->assertSet('reportTypes', [
                'deposits' => 'Setoran',
                'withdrawals' => 'Pencairan',
                'groceries' => 'Sembako',
                'pickups' => 'Penjemputan',
                'participation' => 'Partisipasi',
            ]);
    }

    public function test_treasurer_rejects_forged_disallowed_report_types_for_refresh_and_export(): void
    {
        $actor = $this->userWith('report.view', 'report.export');
        Livewire::actingAs($actor);

        Livewire::test(TreasurerReports::class)
            ->set('reportType', 'deposits')
            ->call('refreshReport')
            ->assertHasErrors(['reportType']);

        Livewire::test(TreasurerReports::class)
            ->set('reportType', 'pickups')
            ->call('export')
            ->assertHasErrors(['reportType']);
    }

    public function test_treasurer_cannot_forge_the_backoffice_surface_to_access_restricted_report_types(): void
    {
        $actor = $this->userWith('report.view', 'report.export');
        Livewire::actingAs($actor);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(TreasurerReports::class)
            ->set('surface', 'backoffice')
            ->set('reportType', 'deposits')
            ->call('refreshReport');
    }

    /** @return array<string, list<array{key: string, label: string, format: string}>> */
    private function summaryContracts(): array
    {
        return [
            'deposits' => [
                ['key' => 'subject_count', 'label' => 'Nasabah', 'format' => 'count'],
                ['key' => 'deposit_count', 'label' => 'Setoran', 'format' => 'count'],
                ['key' => 'total_weight_kg', 'label' => 'Total Berat', 'format' => 'weight'],
                ['key' => 'total_value', 'label' => 'Total Nilai', 'format' => 'currency'],
                ['key' => 'plastic_weight_kg', 'label' => 'Plastik', 'format' => 'weight'],
            ],
            'withdrawals' => [
                ['key' => 'customer_count', 'label' => 'Nasabah', 'format' => 'count'],
                ['key' => 'withdrawal_count', 'label' => 'Pencairan', 'format' => 'count'],
                ['key' => 'total_amount', 'label' => 'Total Pencairan', 'format' => 'currency'],
            ],
            'groceries' => [
                ['key' => 'customer_count', 'label' => 'Nasabah', 'format' => 'count'],
                ['key' => 'redemption_count', 'label' => 'Penukaran Sembako', 'format' => 'count'],
                ['key' => 'total_redeemed_value', 'label' => 'Total Nilai Sembako', 'format' => 'currency'],
            ],
            'pickups' => [
                ['key' => 'customer_count', 'label' => 'Nasabah', 'format' => 'count'],
                ['key' => 'pickup_count', 'label' => 'Penjemputan', 'format' => 'count'],
                ['key' => 'estimated_weight_kg', 'label' => 'Estimasi Berat', 'format' => 'weight'],
            ],
            'participation' => [
                ['key' => 'participant_count', 'label' => 'Peserta', 'format' => 'count'],
                ['key' => 'participation_count', 'label' => 'Partisipasi', 'format' => 'count'],
                ['key' => 'collected_weight_kg', 'label' => 'Berat Terkumpul', 'format' => 'weight'],
                ['key' => 'collected_value', 'label' => 'Nilai Terkumpul', 'format' => 'currency'],
            ],
        ];
    }

    private function seedDeposit(User $customer, User $staff, int $value, string $number, string $weight, WasteType $wasteType, WasteCondition $condition, string $method = 'loket'): Deposit
    {
        $deposit = Deposit::query()->create([
            'deposit_number' => $number,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'method' => $method,
            'occurred_at' => '2026-08-01 10:00:00',
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => $weight,
            'total_value' => $value,
            'finalized_at' => '2026-08-01 10:00:00',
        ]);
        DepositItem::query()->create([
            'deposit_id' => $deposit->id,
            'waste_type_id' => $wasteType->id,
            'waste_condition_id' => $condition->id,
            'weight_kg' => $weight,
            'price_per_unit' => 1_000,
            'subtotal' => $value,
        ]);

        return $deposit;
    }

    private function seedWithdrawal(User $customer, User $requestedBy, int $amount, string $number): WithdrawalRequest
    {
        return WithdrawalRequest::query()->create([
            'request_number' => $number,
            'customer_id' => $customer->id,
            'requested_by_id' => $requestedBy->id,
            'amount' => $amount,
            'status' => WithdrawalStatus::Paid,
            'paid_at' => '2026-08-01 11:00:00',
        ]);
    }

    private function seedGrocery(User $customer, User $requestedBy, GroceryPackage $package, int $value, string $number): GroceryRedemption
    {
        return GroceryRedemption::query()->create([
            'request_number' => $number,
            'customer_id' => $customer->id,
            'requested_by_id' => $requestedBy->id,
            'grocery_package_id' => $package->id,
            'value_snapshot' => $value,
            'package_snapshot' => ['code' => $package->code, 'value' => $value],
            'status' => GroceryStatus::Completed,
            'handed_over_at' => '2026-08-01 12:00:00',
        ]);
    }

    /** @return array{0: Rt, 1: ServiceArea} */
    private function pickupRegion(): array
    {
        $dusun = Dusun::query()->create(['code' => 'DS-SEMANTICS', 'name' => 'Dusun Semantics', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-SEMANTICS', 'name' => 'RW Semantics', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-SEMANTICS', 'name' => 'RT Semantics', 'is_active' => true]);
        $area = ServiceArea::query()->create(['name' => 'Area Semantics', 'is_active' => true]);

        return [$rt, $area];
    }

    private function seedPickup(User $customer, Rt $rt, ServiceArea $area, string $number, string $weight): PickupRequest
    {
        return PickupRequest::query()->create([
            'request_number' => $number,
            'customer_id' => $customer->id,
            'rt_id' => $rt->id,
            'service_area_id' => $area->id,
            'address' => 'Alamat Semantics',
            'selected_date' => '2026-08-01',
            'estimated_weight_kg' => $weight,
            'status' => PickupStatus::Completed,
            'completed_at' => '2026-08-01 13:00:00',
        ]);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'report-semantics-'.Str::uuid(), 'description' => 'Report semantics tests']);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
