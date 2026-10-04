<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\ResolveCitizenVerification;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

final class CitizenVerificationResolutionTest extends TestCase
{
    use RefreshDatabase;

    private const CORRELATION_ID = '018f4ca4-2e67-7c16-a455-8f610f6f5642';

    public function test_authorized_verifier_activates_pending_citizen_with_attribution_and_sanitized_audit(): void
    {
        $verifier = User::factory()->create();
        $subject = User::factory()->pendingVerification()->create();
        $this->grant($verifier, 'user.verify');

        $resolved = app(ResolveCitizenVerification::class)->verify($verifier->fresh(), $subject, self::CORRELATION_ID);

        self::assertSame(UserStatus::Active, $resolved->status);
        self::assertSame($verifier->id, $resolved->verified_by);
        self::assertNotNull($resolved->verified_at);
        self::assertNull($resolved->rejection_reason);
        $this->assertDatabaseHas('users', ['id' => $subject->id, 'status' => UserStatus::Active->value, 'verified_by' => $verifier->id]);
        $audit = AuditLog::query()->sole();
        self::assertSame('identity.user.verified', $audit->action);
        self::assertSame($verifier->id, $audit->actor_id);
        self::assertSame($subject->id, $audit->auditable_id);
        self::assertSame(['status' => UserStatus::PendingVerification->value], $audit->old_values);
        self::assertSame(UserStatus::Active->value, $audit->new_values['status']);
        self::assertSame($verifier->id, $audit->new_values['verified_by']);
        self::assertSame(self::CORRELATION_ID, $audit->correlation_id);
    }

    public function test_self_action_and_permission_escalation_are_denied_without_state_or_audit(): void
    {
        $self = User::factory()->pendingVerification()->create();
        $unprivileged = User::factory()->create();
        $subject = User::factory()->pendingVerification()->create();
        $this->grant($self, 'user.verify');

        try {
            app(ResolveCitizenVerification::class)->verify($self->fresh(), $self, self::CORRELATION_ID);
            self::fail('Self-verification was not denied.');
        } catch (AuthorizationException) {
            $this->assertUnchangedWithoutAudit($self);
        }

        try {
            app(ResolveCitizenVerification::class)->verify($unprivileged, $subject, self::CORRELATION_ID);
            self::fail('Unprivileged verification was not denied.');
        } catch (AuthorizationException) {
            $this->assertUnchangedWithoutAudit($subject);
        }
    }

    public function test_only_pending_users_can_be_resolved_without_audit(): void
    {
        $verifier = User::factory()->create();
        $active = User::factory()->create();
        $this->grant($verifier, 'user.verify');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Hanya pengguna yang menunggu verifikasi yang dapat diproses.');

        try {
            app(ResolveCitizenVerification::class)->verify($verifier->fresh(), $active, self::CORRELATION_ID);
        } finally {
            self::assertSame(UserStatus::Active, $active->fresh()->status);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_rejection_requires_reason_before_any_state_or_audit_change(): void
    {
        $rejector = User::factory()->create();
        $subject = User::factory()->pendingVerification()->create();
        $this->grant($rejector, 'user.reject');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Alasan penolakan wajib diisi.');

        try {
            app(ResolveCitizenVerification::class)->reject($rejector->fresh(), $subject, " \t ", self::CORRELATION_ID);
        } finally {
            $this->assertUnchangedWithoutAudit($subject);
        }
    }

    public function test_rejection_is_audited_atomically_and_sensitive_reason_keys_are_sanitized(): void
    {
        $rejector = User::factory()->create();
        $subject = User::factory()->pendingVerification()->create();
        $this->grant($rejector, 'user.reject');

        $resolved = app(ResolveCitizenVerification::class)->reject($rejector->fresh(), $subject, 'Dokumen token rahasia tidak sesuai.', self::CORRELATION_ID);

        self::assertSame(UserStatus::Rejected, $resolved->status);
        self::assertSame('Dokumen token rahasia tidak sesuai.', $resolved->rejection_reason);
        self::assertNull($resolved->verified_at);
        self::assertNull($resolved->verified_by);
        $audit = AuditLog::query()->sole();
        self::assertSame('identity.user.rejected', $audit->action);
        self::assertSame('[REDACTED]', $audit->new_values['rejection_reason']);
        self::assertSame(UserStatus::Rejected->value, $audit->new_values['status']);
    }

    public function test_audit_failure_rolls_back_verified_user_state(): void
    {
        $verifier = User::factory()->create();
        $subject = User::factory()->pendingVerification()->create();
        $this->grant($verifier, 'user.verify');
        AuditLog::creating(static function (): void {
            throw new RuntimeException('Forced audit write failure.');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forced audit write failure.');

        try {
            app(ResolveCitizenVerification::class)->verify($verifier->fresh(), $subject, self::CORRELATION_ID);
        } finally {
            $this->assertUnchangedWithoutAudit($subject);
        }
    }

    private function assertUnchangedWithoutAudit(User $subject): void
    {
        $fresh = $subject->fresh();
        self::assertNotNull($fresh);
        self::assertSame(UserStatus::PendingVerification, $fresh->status);
        self::assertNull($fresh->verified_at);
        self::assertNull($fresh->verified_by);
        self::assertNull($fresh->rejection_reason);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function grant(User $user, string $permissionName): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'verifier'],
            ['description' => 'Test verifier role'],
        );
        $permission = Permission::query()->firstOrCreate(
            ['name' => $permissionName],
            ['description' => "Test permission {$permissionName}"],
        );
        $role->permissions()->syncWithoutDetaching($permission);
        $user->roles()->attach($role);
    }
}
