<?php

declare(strict_types=1);

namespace Tests\Feature\AuditReconciliation;

use App\Domain\AuditReconciliation\Enums\ReconciliationItemStatus;
use App\Domain\AuditReconciliation\Enums\ReconciliationStatus;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\AuditReconciliation\Services\ReconciliationService;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

final class ReconciliationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_create_reconciliation_with_computed_totals(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $this->seedDeposit($creator, 1_000, '2026-08-01 10:00:00', 'DEP-REC-CREATE');

        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null, 'Penutupan layanan harian');

        self::assertSame(ReconciliationStatus::Draft, $record->status);
        self::assertSame(1, $record->version);
        self::assertSame('all', $record->scope_key);
        self::assertSame(1_000, $record->deposit_total);
        self::assertSame(1_000, $record->difference);
        self::assertSame($creator->id, $record->created_by);
        self::assertSame(1, $record->items()->count());
        self::assertSame(ReconciliationItemStatus::Open, $record->items()->first()->status);
        self::assertSame(1, AuditLog::query()->where('action', 'reconciliation.created')->count());
    }

    public function test_reconciliation_without_discrepancy_can_be_approved(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');

        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);
        self::assertSame(0, $record->difference);

        $submitted = app(ReconciliationService::class)->submit($creator, $record);
        self::assertSame(ReconciliationStatus::Submitted, $submitted->status);
        self::assertNotNull($submitted->submitted_at);

        $approved = app(ReconciliationService::class)->approve($approver, $submitted);
        self::assertSame(ReconciliationStatus::Approved, $approved->status);
        self::assertSame($approver->id, $approved->approver_id);
        self::assertNotNull($approved->approved_at);
        self::assertSame(1, AuditLog::query()->where('action', 'reconciliation.approved')->count());
    }

    public function test_creator_cannot_approve_own_reconciliation(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'reconciliation.approve', 'report.view');

        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);
        app(ReconciliationService::class)->submit($creator, $record);

        $this->assertThrows(fn (): mixed => app(ReconciliationService::class)->approve($creator, $record), AuthorizationException::class);
    }

    public function test_open_discrepancy_blocks_approval(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');
        $this->seedDeposit($creator, 5_000, '2026-08-01 10:00:00', 'DEP-REC-DIFF');

        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);
        app(ReconciliationService::class)->submit($creator, $record);

        $this->assertThrows(fn (): mixed => app(ReconciliationService::class)->approve($approver, $record), ValidationException::class);
    }

    public function test_discrepancy_resolution_allows_approval(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');
        $this->seedDeposit($creator, 5_000, '2026-08-01 10:00:00', 'DEP-REC-RESOLVE');

        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);
        $resolved = app(ReconciliationService::class)->resolveDiscrepancy($creator, $record, ['note' => 'Selisih diverifikasi melalui bukti kas dan koreksi resmi.']);
        self::assertSame(ReconciliationItemStatus::Resolved, $resolved->items()->latest('id')->first()->status);
        self::assertSame(1, AuditLog::query()->where('action', 'reconciliation.discrepancy.resolved')->count());

        $submitted = app(ReconciliationService::class)->submit($creator, $resolved);
        $approved = app(ReconciliationService::class)->approve($approver, $submitted);
        self::assertSame(ReconciliationStatus::Approved, $approved->status);
    }

    public function test_rejection_requires_reason_of_minimum_length(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');

        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);
        app(ReconciliationService::class)->submit($creator, $record);

        $this->assertThrows(fn (): mixed => app(ReconciliationService::class)->reject($approver, $record, 'pendek'), ValidationException::class);
    }

    public function test_actual_cash_is_compared_with_ledger_closing_total_and_resolution_records_actual_total(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $this->seedDeposit($creator, 5_000, '2026-08-01 10:00:00', 'DEP-REC-CASH');

        $service = app(ReconciliationService::class);
        $record = $service->create($creator, '2026-08-01', null, 'Hitung kas fisik', 4_000);

        self::assertSame(4_000, $record->cash_total);
        self::assertSame(-1_000, $record->difference);
        self::assertSame(5_000, $record->items()->latest('id')->first()->expected_total);
        self::assertSame(4_000, $record->items()->latest('id')->first()->actual_total);
        self::assertSame(ReconciliationItemStatus::Open, $record->items()->latest('id')->first()->status);

        $resolved = $service->resolveDiscrepancy($creator, $record, ['actual_total' => 5_000, 'note' => 'Kas dihitung ulang dan cocok dengan saldo ledger penutupan.']);
        $item = $resolved->items()->latest('id')->first();
        self::assertSame(5_000, $item->actual_total);
        self::assertSame(0, $item->difference);
        self::assertSame(ReconciliationItemStatus::Resolved, $item->status);
    }

    public function test_rejected_reconciliation_cannot_be_approved(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');

        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);
        app(ReconciliationService::class)->submit($creator, $record);
        $rejected = app(ReconciliationService::class)->reject($approver, $record, 'Selisih perlu ditelusuri ulang sebelum disahkan.');
        self::assertSame(ReconciliationStatus::Rejected, $rejected->status);
        self::assertSame($approver->id, $rejected->rejector_id);
        self::assertNotNull($rejected->rejected_at);
        self::assertSame(1, AuditLog::query()->where('action', 'reconciliation.rejected')->count());

        $this->assertThrows(fn (): mixed => app(ReconciliationService::class)->approve($approver, $rejected), ValidationException::class);
    }

    public function test_revision_is_parented_and_increments_version(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');

        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);
        app(ReconciliationService::class)->submit($creator, $record);
        $approved = app(ReconciliationService::class)->approve($approver, $record);

        $revision = app(ReconciliationService::class)->revise($creator, $approved, 'Revisi setelah bukti tambahan ditemukan.');
        self::assertSame($approved->id, $revision->parent_id);
        self::assertSame(2, $revision->version);
        self::assertSame('all', $revision->scope_key);
        self::assertDatabaseHas('reconciliations', ['id' => $revision->id, 'parent_id' => $approved->id, 'version' => 2]);
    }

    public function test_revision_preserves_cash_snapshot_and_difference_item_actual_total(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');
        $this->seedDeposit($creator, 5_000, '2026-08-01 10:00:00', 'DEP-REC-REVISION-CASH');

        $service = app(ReconciliationService::class);
        $record = $service->create($creator, '2026-08-01', null, 'Hitung kas fisik', 4_000);
        $resolved = $service->resolveDiscrepancy($creator, $record, ['note' => 'Kas dihitung ulang dan cocok dengan saldo kas fisik.']);
        $submitted = $service->submit($creator, $resolved);
        $approved = $service->approve($approver, $submitted);
        $revision = $service->revise($creator, $approved, 'Revisi setelah bukti tambahan ditemukan.');
        $item = $revision->items()->latest('id')->first();

        self::assertSame(4_000, $revision->cash_total);
        self::assertSame(-1_000, $revision->difference);
        self::assertSame(4_000, $item->actual_total);
        self::assertSame(-1_000, $item->difference);
    }

    public function test_rejected_revision_preserves_cash_snapshot_and_difference_item_actual_total(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');
        $this->seedDeposit($creator, 5_000, '2026-08-01 10:00:00', 'DEP-REC-REJECTED-CASH');

        $service = app(ReconciliationService::class);
        $record = $service->create($creator, '2026-08-01', null, 'Hitung kas fisik', 4_000);
        $submitted = $service->submit($creator, $record);
        $rejected = $service->reject($approver, $submitted, 'Bukti kas perlu dilengkapi sebelum disahkan.');
        $revision = $service->revise($creator, $rejected, 'Revisi setelah bukti kas dilengkapi.');
        $item = $revision->items()->latest('id')->first();

        self::assertSame(4_000, $revision->cash_total);
        self::assertSame(-1_000, $revision->difference);
        self::assertSame(4_000, $item->actual_total);
        self::assertSame(-1_000, $item->difference);
    }

    public function test_discrepancy_resolution_rejects_stale_draft_after_submission_without_mutation(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $this->seedDeposit($creator, 5_000, '2026-08-01 10:00:00', 'DEP-REC-LOCKED-RESOLVE');

        $service = app(ReconciliationService::class);
        $record = $service->create($creator, '2026-08-01', null, 'Hitung kas fisik', 4_000);
        $itemCount = $record->items()->count();
        $service->submit($creator, $record);

        $this->assertThrows(fn (): mixed => $service->resolveDiscrepancy($creator, $record, [
            'actual_total' => 5_000,
            'note' => 'Kas dihitung ulang setelah record diajukan untuk pengesahan.',
        ]), ValidationException::class);

        $fresh = $record->fresh();
        self::assertSame(ReconciliationStatus::Submitted, $fresh->status);
        self::assertSame($itemCount, $fresh->items()->count());
        self::assertSame(0, AuditLog::query()->where('action', 'reconciliation.discrepancy.resolved')->count());
    }

    public function test_append_only_enforcement(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');

        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);
        $record->setAttribute('deposit_total', 999);

        $this->expectException(LogicException::class);
        $record->save();
    }

    public function test_reconciliation_cannot_be_deleted(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');

        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);

        $this->expectException(LogicException::class);
        $record->delete();
    }

    public function test_actor_cannot_submit_an_out_of_scope_reconciliation(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $otherActor = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);

        $this->expectException(AuthorizationException::class);
        app(ReconciliationService::class)->submit($otherActor, $record);
    }

    public function test_revision_is_audited_without_mutating_the_approved_snapshot(): void
    {
        $creator = $this->userWith('reconciliation.create', 'reconciliation.view', 'report.view');
        $approver = $this->userWith('reconciliation.approve', 'reconciliation.view', 'user.view.all', 'report.view');
        $record = app(ReconciliationService::class)->create($creator, '2026-08-01', null);
        $approved = app(ReconciliationService::class)->approve($approver, app(ReconciliationService::class)->submit($creator, $record));

        $revision = app(ReconciliationService::class)->revise($creator, $approved, 'Bukti tambahan dicatat pada revisi ini.');

        self::assertSame($approved->id, $revision->parent_id);
        self::assertSame(2, $revision->version);
        self::assertNull($approved->fresh()->parent_id);
        self::assertDatabaseHas('audit_logs', [
            'actor_id' => $creator->id,
            'action' => 'reconciliation.revised',
            'auditable_id' => $revision->id,
        ]);
    }

    private function seedDeposit(User $owner, int $value, string $occurredAt, string $number = 'DEP-REC-OK'): object
    {
        $deposit = Deposit::query()->create([
            'deposit_number' => $number.'-'.$owner->id,
            'customer_id' => $owner->id,
            'staff_id' => $owner->id,
            'method' => 'loket',
            'occurred_at' => $occurredAt,
            'status' => 'final',
            'total_weight_kg' => '1.000',
            'total_value' => $value,
            'finalized_at' => $occurredAt,
        ]);
        $account = LedgerAccount::query()->create(['user_id' => $owner->id, 'status' => 'aktif', 'currency' => 'IDR']);
        LedgerEntry::query()->create(['entry_number' => 'LED-REC-'.$owner->id.'-'.str()->random(6), 'ledger_account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_IN, 'kind' => LedgerEntry::KIND_DEPOSIT, 'amount' => $value, 'source_type' => Deposit::class, 'source_id' => $deposit->id, 'source_key' => 'deposit:rec:'.$deposit->id, 'effective_at' => $occurredAt, 'balance_after' => $value]);

        return $deposit;
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'rec-'.uniqid(), 'description' => 'Reconciliation workflow']);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
