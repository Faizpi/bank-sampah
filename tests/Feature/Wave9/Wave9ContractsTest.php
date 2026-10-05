<?php

declare(strict_types=1);

namespace Tests\Feature\Wave9;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\AuditReconciliation\Services\AuditQueryService;
use App\Domain\AuditReconciliation\Services\AuditRetentionService;
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
use App\Domain\Platform\Models\Media;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Reports\Services\ReportQueryService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;
use ZipArchive;

final class Wave9ContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_contract_rejects_unknown_filter_and_sort_is_server_allowlisted(): void
    {
        $actor = $this->userWith('report.view');
        $reports = app(ReportQueryService::class);
        $this->expectException(ValidationException::class);
        $reports->paginate($actor, ['start' => '2026-08-01', 'end' => '2026-08-02', 'unknown' => 'x'], 'id');
    }

    public function test_report_scope_is_applied_before_aggregate_and_idor_is_hidden(): void
    {
        $owner = $this->userWith('report.view');
        $other = $this->userWith('report.view');
        $this->seedDeposit($owner, 20_000, '2026-08-01 10:00:00');
        $this->seedDeposit($other, 99_000, '2026-08-01 10:00:00');
        $metrics = app(ReportQueryService::class)->aggregate($owner, ['start' => '2026-08-01', 'end' => '2026-08-02']);
        self::assertSame(1, $metrics['deposit_count']);
        self::assertSame(20_000, $metrics['total_value']);
        self::assertSame([$owner->id], app(ReportQueryService::class)->query($owner, ['start' => '2026-08-01', 'end' => '2026-08-02'])->pluck('customer_id')->all());
    }

    public function test_export_is_private_server_named_and_formula_cells_are_neutralized(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');
        $deposit = $this->seedDeposit($actor, 20_000, '2026-08-01 10:00:00', '=FORMULA');
        $export = app(ReportExportService::class)->export($actor, 'deposits', ['start' => '2026-08-01', 'end' => '2026-08-02'], 'csv');
        self::assertSame(ReportExportStatus::Succeeded, $export->status);
        self::assertSame('private', Media::query()->findOrFail($export->media_id)->getRawOriginal('visibility'));
        self::assertSame('laporan-deposits-'.$export->uuid.'.csv', $export->filename);
        self::assertStringContainsString("'=FORMULA", Storage::disk('media_private')->get((string) $export->path));
        self::assertSame(1, AuditLog::query()->where('action', 'report.export.completed')->count());
        self::assertSame($actor->id, $deposit->customer_id);
    }

    public function test_export_idor_expiry_and_download_audit_are_enforced(): void
    {
        Storage::fake('media_private');
        $owner = $this->userWith('report.view', 'report.export');
        $other = $this->userWith('report.view', 'report.export');
        $export = app(ReportExportService::class)->export($owner, 'deposits', ['start' => '2026-08-01', 'end' => '2026-08-02'], 'csv');
        self::assertThrows(fn (): mixed => app(ReportExportService::class)->download($other, $export), ModelNotFoundException::class);
        $media = app(ReportExportService::class)->download($owner, $export);
        self::assertSame($export->media_id, $media->id);
        self::assertSame(1, AuditLog::query()->where('action', 'report.export.downloaded')->count());
        $expired = $export->forceFill(['expires_at' => now()->subMinute()]);
        $expired->saveQuietly();
        self::assertThrows(fn (): mixed => app(ReportExportService::class)->download($owner, $expired), ModelNotFoundException::class);
    }

    public function test_all_documented_operational_report_types_can_be_exported_with_private_scope(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');

        foreach (['deposits', 'withdrawals', 'groceries', 'pickups', 'participation'] as $reportType) {
            $export = app(ReportExportService::class)->export($actor, $reportType, ['start' => '2026-08-01', 'end' => '2026-08-02'], 'csv');

            self::assertSame(ReportExportStatus::Succeeded, $export->status);
            self::assertTrue(Storage::disk('media_private')->exists((string) $export->path));
            self::assertSame($actor->id, $export->requester_id);
        }
    }

    public function test_report_center_contract_exposes_scope_filters_and_operational_types(): void
    {
        $actor = $this->userWith('report.view');
        $contract = app(ReportQueryService::class)->contract();

        self::assertContains('service_area_id', $contract['filters']);
        self::assertContains('status', $contract['filters']);
        self::assertSame(['deposits', 'withdrawals', 'groceries', 'pickups', 'participation'], $contract['report_types']);
        self::assertSame('paid_at', $contract['report_columns']['withdrawals'][1]);
        self::assertSame('handed_over_at', $contract['report_columns']['groceries'][1]);
        self::assertContains('completed_at', $contract['report_columns']['pickups']);
        self::assertCount(0, app(ReportQueryService::class)->paginate($actor, ['start' => '2026-08-01', 'end' => '2026-08-02'], perPage: 25));
    }

    public function test_operational_report_dates_are_used_for_display_and_export(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');
        $reports = app(ReportQueryService::class);
        $withdrawalAt = '2026-08-01 09:10:11';
        $groceryAt = '2026-08-01 10:20:21';
        $pickupAt = '2026-08-01 11:30:31';
        WithdrawalRequest::query()->create([
            'request_number' => 'WDR-W9-DATE', 'customer_id' => $actor->id, 'requested_by_id' => $actor->id,
            'amount' => 12_000, 'status' => WithdrawalStatus::Paid, 'paid_at' => $withdrawalAt,
        ]);
        $package = GroceryPackage::query()->create(['code' => 'W9-PACKAGE', 'name' => 'Paket W9', 'contents' => 'Isi', 'value' => 20_000, 'status' => 'aktif']);
        GroceryRedemption::query()->create([
            'request_number' => 'GRC-W9-DATE', 'customer_id' => $actor->id, 'requested_by_id' => $actor->id,
            'grocery_package_id' => $package->id, 'value_snapshot' => 20_000, 'package_snapshot' => ['code' => 'W9-PACKAGE'],
            'status' => GroceryStatus::Completed, 'handed_over_at' => $groceryAt,
        ]);
        $dusun = Dusun::query()->create(['code' => 'W9-DS', 'name' => 'Dusun W9', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'W9-RW', 'name' => 'RW W9', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'W9-RT', 'name' => 'RT W9', 'is_active' => true]);
        $area = ServiceArea::query()->create(['name' => 'Area W9', 'is_active' => true]);
        PickupRequest::query()->create([
            'request_number' => 'PUP-W9-DATE', 'customer_id' => $actor->id, 'rt_id' => $rt->id, 'service_area_id' => $area->id,
            'address' => 'Alamat W9', 'selected_date' => '2026-08-01', 'status' => PickupStatus::Completed, 'completed_at' => $pickupAt,
        ]);

        self::assertSame($withdrawalAt, $reports->displayRows($actor, 'withdrawals', ['start' => '2026-08-01', 'end' => '2026-08-02'])[0]['date']);
        self::assertSame($groceryAt, $reports->displayRows($actor, 'groceries', ['start' => '2026-08-01', 'end' => '2026-08-02'])[0]['date']);
        self::assertSame($pickupAt, $reports->displayRows($actor, 'pickups', ['start' => '2026-08-01', 'end' => '2026-08-02'])[0]['date']);
        foreach ([['withdrawals', 'Tanggal Dibayar', $withdrawalAt], ['groceries', 'Tanggal Diserahkan', $groceryAt], ['pickups', 'Tanggal Selesai', $pickupAt]] as [$type, $column, $date]) {
            $export = app(ReportExportService::class)->export($actor, $type, ['start' => '2026-08-01', 'end' => '2026-08-02'], 'csv');
            $content = Storage::disk('media_private')->get((string) $export->path);
            self::assertStringContainsString($column, $content);
            self::assertStringContainsString($date, $content);
        }
    }

    public function test_records_are_bounded_and_export_streams_all_rows_under_explicit_limit(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');
        foreach (range(1, 101) as $number) {
            $this->seedDeposit($actor, $number, '2026-08-01 10:00:00', 'DEP-W9-LIMIT-'.$number);
        }
        $reports = app(ReportQueryService::class);
        self::assertCount(100, $reports->records($actor, 'deposits', ['start' => '2026-08-01', 'end' => '2026-08-02']));
        $export = app(ReportExportService::class)->export($actor, 'deposits', ['start' => '2026-08-01', 'end' => '2026-08-02'], 'csv');
        $content = Storage::disk('media_private')->get((string) $export->path);
        self::assertSame(102, substr_count(trim($content), "\n") + 1);
        self::assertStringContainsString('DEP-W9-LIMIT-101-'.$actor->id, $content);
    }

    public function test_display_rows_preserve_report_scope_and_format_empty_results(): void
    {
        $actor = $this->userWith('report.view');
        $reports = app(ReportQueryService::class);

        self::assertSame([], $reports->displayRows($actor, 'deposits', ['start' => '2026-08-01', 'end' => '2026-08-02']));

        $this->seedDeposit($actor, 20_000, '2026-08-01 10:00:00', 'DEP-W9-ROWS');
        self::assertSame([
            [
                'reference' => 'DEP-W9-ROWS-'.$actor->id,
                'date' => '2026-08-01 10:00:00',
                'subject' => $actor->name,
                'detail' => '1,00 kg · loket',
                'status' => 'final',
                'value' => 20_000,
                'value_format' => 'currency',
            ],
        ], $reports->displayRows($actor, 'deposits', ['start' => '2026-08-01', 'end' => '2026-08-02']));
    }

    public function test_xlsx_formula_injection_is_neutralized_and_failure_has_no_partial_file(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');
        $this->seedDeposit($actor, 20_000, '2026-08-01 10:00:00', '=XLSX');
        $xlsx = app(ReportExportService::class)->export($actor, 'deposits', ['start' => '2026-08-01', 'end' => '2026-08-02'], 'xlsx');
        self::assertTrue(Storage::disk('media_private')->exists((string) $xlsx->path));
        self::assertStringContainsString('&apos;=XLSX', $this->unzipSheet(Storage::disk('media_private')->get((string) $xlsx->path)));
        $filesBeforeInvalidRequest = Storage::disk('media_private')->allFiles('exports');
        try {
            app(ReportExportService::class)->export($actor, 'deposits', ['start' => '2026-08-01', 'end' => '2026-08-02'], 'csv', ['not-allowed']);
            self::fail('Expected invalid export columns to be rejected.');
        } catch (ValidationException) {
            self::assertSame($filesBeforeInvalidRequest, Storage::disk('media_private')->allFiles('exports'));
        }
    }

    public function test_audit_view_is_sanitized_and_append_only(): void
    {
        $admin = $this->userWith('audit.view');
        $audit = app(AuditLogger::class)->record($admin, 'test.audit', $admin, ['password' => 'secret'], ['token' => 'secret'], (string) Str::uuid());
        $safe = app(AuditQueryService::class)->sanitized($admin, $audit->id);
        self::assertSame('[REDACTED]', $safe['old_values']['password']);
        $this->expectException(LogicException::class);
        $audit->delete();
    }

    public function test_audit_idor_is_denied_and_retention_requires_permission_and_protects_w9_audit(): void
    {
        $admin = $this->userWith('audit.view');
        $other = User::factory()->create();
        $audit = app(AuditLogger::class)->record($admin, 'test.audit', $admin, [], [], (string) Str::uuid());
        $this->assertThrowsException(fn (): mixed => app(AuditQueryService::class)->find($other, $audit->id), AuthorizationException::class);
        $this->assertThrowsException(fn (): mixed => app(AuditRetentionService::class)->execute($admin, '2026-08-02'), AuthorizationException::class);
    }

    private function seedDeposit(User $owner, int $value, string $occurredAt, string $number = 'DEP-W9-OK'): Deposit
    {
        $deposit = Deposit::query()->create(['deposit_number' => $number.'-'.$owner->id, 'customer_id' => $owner->id, 'staff_id' => $owner->id, 'method' => 'loket', 'occurred_at' => $occurredAt, 'status' => 'final', 'total_weight_kg' => '1.000', 'total_value' => $value, 'finalized_at' => $occurredAt]);
        DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => WasteType::factory()->create()->id, 'waste_condition_id' => WasteCondition::factory()->create()->id, 'weight_kg' => '1.000', 'price_per_unit' => $value, 'subtotal' => $value]);

        return $deposit;
    }

    /** @param \Closure(): mixed $callback */
    private function assertThrowsException(\Closure $callback, string $expected): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            self::assertInstanceOf($expected, $exception);

            return;
        }
        self::fail('Expected exception was not thrown.');
    }

    private function unzipSheet(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'w9-test-');
        self::assertNotFalse($path);
        file_put_contents($path, $content);
        $zip = new ZipArchive;
        self::assertTrue($zip->open($path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);
        self::assertIsString($sheet);

        return $sheet;
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'w9-'.uniqid(), 'description' => 'W9']);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
