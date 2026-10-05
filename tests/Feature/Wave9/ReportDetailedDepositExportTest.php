<?php

declare(strict_types=1);

namespace Tests\Feature\Wave9;

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
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

final class ReportDetailedDepositExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_export_includes_an_item_level_row_for_every_waste_item_with_parent_identity_and_totals(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');
        $firstType = WasteType::factory()->create(['name' => 'Plastik Botol Ekspor']);
        $secondType = WasteType::factory()->create(['name' => 'Kertas Koran Ekspor']);
        $condition = WasteCondition::factory()->create(['name' => 'Bersih Ekspor']);
        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-DETAIL-EXPORT',
            'customer_id' => $actor->id,
            'staff_id' => $actor->id,
            'method' => 'loket',
            'occurred_at' => '2026-08-01 10:00:00',
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '4.000',
            'total_value' => 42_000,
            'finalized_at' => '2026-08-01 10:00:00',
        ]);
        foreach ([
            [$firstType, '1.250', 12_500],
            [$secondType, '2.750', 29_500],
        ] as [$type, $weight, $subtotal]) {
            DepositItem::query()->create([
                'deposit_id' => $deposit->id,
                'waste_type_id' => $type->id,
                'waste_condition_id' => $condition->id,
                'weight_kg' => $weight,
                'price_per_unit' => 10_000,
                'subtotal' => $subtotal,
            ]);
        }

        $export = app(ReportExportService::class)->export($actor, 'deposits', [
            'start' => '2026-08-01',
            'end' => '2026-08-02',
        ], 'xlsx');

        self::assertSame(ReportExportStatus::Succeeded, $export->status);
        $parts = $this->xlsxParts((string) Storage::disk('media_private')->get((string) $export->path));
        $sheet = $parts['xl/worksheets/sheet1.xml'];
        foreach (['DEP-DETAIL-EXPORT', (string) $actor->id, 'Plastik Botol Ekspor', 'Kertas Koran Ekspor', 'Bersih Ekspor', '1.250', '2.750', '12500', '29500'] as $expectedValue) {
            self::assertStringContainsString($expectedValue, $sheet);
        }
        self::assertSame(3, substr_count($sheet, '<row '));
        self::assertStringContainsString('<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>', $sheet);
        self::assertStringContainsString('<autoFilter ref="A1:Q3"/>', $sheet);
        self::assertStringContainsString('<tableParts count="1">', $sheet);
        self::assertStringContainsString('<c r="K2" s="4"><v>4.000</v></c>', $sheet);
        self::assertStringContainsString('<c r="O2" s="4"><v>1.250</v></c>', $sheet);
        self::assertStringContainsString('<numFmt numFmtId="165" formatCode="0.00&quot; kg&quot;"/>', $parts['xl/styles.xml']);
        self::assertStringContainsString('<c r="L2" s="5"><v>42000</v></c>', $sheet);
        self::assertStringContainsString('formatCode="0.00&quot; kg&quot;"', $parts['xl/styles.xml']);
        self::assertStringNotContainsString('formatCode="0.000&quot; kg&quot;"', $parts['xl/styles.xml']);
        self::assertStringContainsString('<c r="B2" s="3"><v>', $sheet);
        self::assertStringNotContainsString('<mergeCells', $sheet);
        self::assertStringContainsString('<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="1" name="LaporanTable" displayName="LaporanTable" ref="A1:Q3"', $parts['xl/tables/table1.xml']);
        self::assertStringContainsString('<sheet name="Laporan" sheetId="1" r:id="rId1"/>', $parts['xl/workbook.xml']);
        self::assertSame(1, substr_count($parts['xl/workbook.xml'], '<sheet '));
    }

    public function test_deposit_export_honors_selected_parent_columns_and_keeps_required_item_schema(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');
        $type = WasteType::factory()->create();
        $condition = WasteCondition::factory()->create();
        $deposit = Deposit::query()->create(['deposit_number' => 'DEP-COLUMN-EXPORT', 'customer_id' => $actor->id, 'staff_id' => $actor->id, 'method' => 'loket', 'occurred_at' => '2026-08-01 10:00:00', 'status' => Deposit::STATUS_FINAL, 'total_weight_kg' => '1.000', 'total_value' => 10_000, 'finalized_at' => '2026-08-01 10:00:00']);
        DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.000', 'price_per_unit' => 10_000, 'subtotal' => 10_000]);

        $export = app(ReportExportService::class)->export($actor, 'deposits', ['start' => '2026-08-01', 'end' => '2026-08-02'], 'xlsx', ['deposit_number', 'total_value']);

        self::assertSame(ReportExportStatus::Succeeded, $export->status);
        $table = $this->xlsxParts((string) Storage::disk('media_private')->get((string) $export->path))['xl/tables/table1.xml'];
        self::assertStringContainsString('name="Nomor Setoran"', $table);
        self::assertStringContainsString('name="Total Nilai (Rp)"', $table);
        self::assertStringContainsString('name="Jenis Sampah"', $table);
        self::assertStringNotContainsString('name="Status Setoran"', $table);
    }

    public function test_pickup_export_uses_a_blank_staff_field_when_completed_request_has_no_assigned_staff(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export', 'user.view.all');
        $customer = User::factory()->create(['name' => 'Nasabah Tanpa Petugas']);
        $dusun = Dusun::query()->create(['code' => 'DS-NO-STAFF', 'name' => 'Dusun Tanpa Petugas', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-NO-STAFF', 'name' => 'RW Tanpa Petugas', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-NO-STAFF', 'name' => 'RT Tanpa Petugas', 'is_active' => true]);
        $serviceArea = ServiceArea::query()->create(['name' => 'Area Tanpa Petugas', 'is_active' => true]);
        PickupRequest::factory()->create([
            'customer_id' => $customer->id,
            'rt_id' => $rt->id,
            'service_area_id' => $serviceArea->id,
            'assigned_staff_id' => null,
            'request_number' => 'PUP-NO-STAFF-EXPORT',
            'status' => PickupStatus::Completed,
            'completed_at' => '2026-08-01 08:30:00',
        ]);

        $export = app(ReportExportService::class)->export($actor, 'pickups', [
            'start' => '2026-08-01',
            'end' => '2026-08-02',
        ], 'csv');

        self::assertSame(ReportExportStatus::Succeeded, $export->status);
        $rows = array_map(static fn (string $row): array => str_getcsv($row), array_filter(explode("\n", trim((string) Storage::disk('media_private')->get((string) $export->path)))));
        $staffColumn = array_search('Petugas Penjemputan', $rows[0], true);
        self::assertNotFalse($staffColumn);
        self::assertSame('', $rows[1][$staffColumn]);
    }

    public function test_non_deposit_exports_use_readable_business_schemas_with_related_values(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export', 'user.view.all');
        $customer = User::factory()->create(['name' => 'Nasabah Ekspor Lengkap']);

        $dusun = Dusun::query()->create(['code' => 'DS-RICH-EXPORT', 'name' => 'Dusun Ekspor', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-RICH-EXPORT', 'name' => 'RW Ekspor', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-RICH-EXPORT', 'name' => 'RT Ekspor', 'is_active' => true]);
        $serviceArea = ServiceArea::query()->create(['name' => 'Area Layanan Ekspor', 'is_active' => true]);
        PickupRequest::factory()->create([
            'customer_id' => $customer->id,
            'rt_id' => $rt->id,
            'service_area_id' => $serviceArea->id,
            'request_number' => 'PUP-RICH-EXPORT',
            'status' => PickupStatus::Completed,
            'completed_at' => '2026-08-01 08:30:00',
            'estimated_weight_kg' => '7.500',
        ]);

        WithdrawalRequest::factory()->create([
            'customer_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'request_number' => 'WDR-RICH-EXPORT',
            'amount' => 85_000,
            'status' => WithdrawalStatus::Paid,
            'paid_at' => '2026-08-01 09:00:00',
        ]);

        $package = GroceryPackage::query()->create([
            'code' => 'PAKET-RICH-EXPORT',
            'name' => 'Paket Ekspor',
            'contents' => 'Beras',
            'value' => 60_000,
            'status' => 'aktif',
        ]);
        GroceryRedemption::factory()->create([
            'customer_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'grocery_package_id' => $package->id,
            'request_number' => 'GRC-RICH-EXPORT',
            'value_snapshot' => 60_000,
            'status' => GroceryStatus::Completed,
            'handed_over_at' => '2026-08-01 10:00:00',
        ]);

        $wasteType = WasteType::factory()->create(['name' => 'Kardus Partisipasi']);
        $condition = WasteCondition::factory()->create(['name' => 'Kering Partisipasi']);
        $participation = Deposit::query()->create([
            'deposit_number' => 'DEP-PARTICIPATION-RICH',
            'customer_id' => $customer->id,
            'staff_id' => $actor->id,
            'method' => 'loket',
            'occurred_at' => '2026-08-01 11:00:00',
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '3.000',
            'total_value' => 30_000,
            'finalized_at' => '2026-08-01 11:00:00',
        ]);
        DepositItem::query()->create([
            'deposit_id' => $participation->id,
            'waste_type_id' => $wasteType->id,
            'waste_condition_id' => $condition->id,
            'weight_kg' => '3.000',
            'price_per_unit' => 10_000,
            'subtotal' => 30_000,
        ]);

        foreach ([
            'pickups' => [['Nomor Penjemputan', 'Nama Nasabah', 'RT', 'Area Layanan', 'Alamat Penjemputan', 'Tanggal Pilihan', 'Tanggal Terjadwal', 'Petugas Penjemputan', 'Estimasi Berat (kg)', 'Catatan', 'Status Penjemputan'], ['PUP-RICH-EXPORT', 'Nasabah Ekspor Lengkap', 'RT Ekspor', 'Area Layanan Ekspor', '7.500']],
            'withdrawals' => [['Nomor Pencairan', 'Nomor Nasabah', 'Nama Nasabah', 'RT', 'Area Layanan', 'Petugas Pembayar', 'Lokasi Pengambilan', 'Tanggal Pengambilan', 'Verifikasi Penerima', 'Referensi Penerima', 'Status Pencairan', 'Jumlah Pencairan (Rp)'], ['WDR-RICH-EXPORT', 'Nasabah Ekspor Lengkap', '85000']],
            'groceries' => [['Nomor Penukaran', 'Nomor Nasabah', 'Nama Nasabah', 'RT', 'Area Layanan', 'Nama Paket', 'Isi Paket', 'Petugas Persiapan', 'Petugas Penyerahan', 'Catatan Ketersediaan', 'Verifikasi Penerima', 'Referensi Penerima', 'Status Penukaran', 'Nilai Sembako (Rp)'], ['GRC-RICH-EXPORT', 'Nasabah Ekspor Lengkap', '60000']],
            'participation' => [['Nomor Setoran', 'Tanggal Setoran', 'Nomor Nasabah', 'Nama Nasabah', 'RT', 'Area Layanan', 'Petugas Setoran', 'Metode Setoran', 'Lokasi Setoran', 'Status Setoran', 'Total Berat (kg)', 'Total Nilai (Rp)', 'Jenis Sampah', 'Kondisi Sampah'], ['Nasabah Ekspor Lengkap', 'Kardus Partisipasi', 'Kering Partisipasi', '3.000', '30000']],
        ] as $reportType => [$headers, $values]) {
            $export = app(ReportExportService::class)->export($actor, $reportType, ['start' => '2026-08-01', 'end' => '2026-08-02'], 'xlsx');

            self::assertSame(ReportExportStatus::Succeeded, $export->status);
            $parts = $this->xlsxParts((string) Storage::disk('media_private')->get((string) $export->path));
            foreach ($headers as $header) {
                self::assertStringContainsString('name="'.$header.'"', $parts['xl/tables/table1.xml']);
            }
            foreach ($values as $value) {
                self::assertStringContainsString($value, $parts['xl/worksheets/sheet1.xml']);
            }
            self::assertStringNotContainsString('name="ID Nasabah"', $parts['xl/tables/table1.xml']);
            self::assertStringNotContainsString('name="ID Area Layanan"', $parts['xl/tables/table1.xml']);
        }
    }

    /** @return array<string, string> */
    private function xlsxParts(string $xlsx): array
    {
        $path = tempnam(sys_get_temp_dir(), 'report-export-');
        self::assertNotFalse($path);
        file_put_contents($path, $xlsx);
        $zip = new ZipArchive;
        self::assertTrue($zip->open($path) === true);
        $parts = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            self::assertIsString($name);
            $content = $zip->getFromIndex($index);
            self::assertIsString($content);
            $parts[$name] = $content;
        }
        $zip->close();
        unlink($path);

        return $parts;
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'detail-export-'.uniqid(), 'description' => 'Detailed deposit export test']);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
