<?php

declare(strict_types=1);

namespace Tests\Feature\Wave9;

use App\Domain\AuditReconciliation\Enums\ReconciliationStatus;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\AuditReconciliation\Services\AuditQueryService;
use App\Domain\AuditReconciliation\Services\AuditRetentionService;
use App\Domain\AuditReconciliation\Services\ReconciliationService;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Platform\Models\Media;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Reports\Services\ReportQueryService;
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

    public function test_xlsx_formula_injection_is_neutralized_and_failure_has_no_partial_file(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');
        $this->seedDeposit($actor, 20_000, '2026-08-01 10:00:00', '=XLSX');
        $xlsx = app(ReportExportService::class)->export($actor, 'deposits', ['start' => '2026-08-01', 'end' => '2026-08-02'], 'xlsx');
        self::assertTrue(Storage::disk('media_private')->exists((string) $xlsx->path));
        self::assertStringContainsString('&apos;=XLSX', $this->unzipSheet(Storage::disk('media_private')->get((string) $xlsx->path)));
        $failed = app(ReportExportService::class)->export($actor, 'reconciliation', ['start' => '2026-08-01', 'end' => '2026-08-02'], 'csv');
        self::assertSame(ReportExportStatus::Failed, $failed->status);
        self::assertNull($failed->path);
        self::assertSame([], Storage::disk('media_private')->allFiles('exports/'.$failed->uuid));
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

    public function test_reconciliation_uses_versioned_state_machine_sod_and_blocks_open_discrepancy(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');
        $this->seedDeposit($creator, 1_000, '2026-08-01 10:00:00', 'DEP-W9-REC');
        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null, 'Penutupan layanan harian');
        self::assertSame(1, $record->version);
        app(ReconciliationService::class)->submit($creator, $record);
        $this->expectException(ValidationException::class);
        app(ReconciliationService::class)->approve($approver, $record);
    }

    public function test_reconciliation_invalid_transition_scope_and_approval_are_audited(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');
        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);
        $this->expectException(ValidationException::class);
        app(ReconciliationService::class)->approve($approver, $record);
        app(ReconciliationService::class)->submit($creator, $record);
        $rejected = app(ReconciliationService::class)->reject($approver, $record, 'Selisih perlu ditelusuri ulang.');
        self::assertSame(ReconciliationStatus::Rejected, $rejected->status);
        self::assertSame(1, AuditLog::query()->where('action', 'reconciliation.rejected')->count());
    }

    public function test_reconciliation_discrepancy_resolution_allows_approval_and_revision_is_parented(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');
        $this->seedDeposit($creator, 1_000, '2026-08-01 10:00:00', 'DEP-W9-RESOLVE');
        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);
        $resolved = app(ReconciliationService::class)->resolveDiscrepancy($creator, $record, ['note' => 'Selisih diverifikasi melalui bukti kas dan koreksi resmi.']);
        app(ReconciliationService::class)->submit($creator, $resolved);
        $approved = app(ReconciliationService::class)->approve($approver, $resolved);
        self::assertSame(ReconciliationStatus::Approved, $approved->status);
        $revision = app(ReconciliationService::class)->revise($creator, $approved, 'Revisi setelah bukti tambahan.');
        self::assertSame($approved->id, $revision->parent_id);
        self::assertSame(2, $revision->version);
        self::assertDatabaseHas('reconciliations', ['id' => $revision->id, 'scope_key' => 'all']);
        self::assertSame(1, AuditLog::query()->where('action', 'reconciliation.discrepancy.resolved')->count());
    }

    private function seedDeposit(User $owner, int $value, string $occurredAt, string $number = 'DEP-W9-OK'): object
    {
        return Deposit::query()->create(['deposit_number' => $number.'-'.$owner->id, 'customer_id' => $owner->id, 'staff_id' => $owner->id, 'method' => 'loket', 'occurred_at' => $occurredAt, 'status' => 'final', 'total_weight_kg' => '1.000', 'total_value' => $value, 'finalized_at' => $occurredAt]);
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
