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

    private function seedDeposit(User $owner, int $value, string $occurredAt, string $number = 'DEP-REC-OK'): object
    {
        return Deposit::query()->create([
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
