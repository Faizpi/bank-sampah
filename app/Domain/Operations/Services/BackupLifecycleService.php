<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Operations\Enums\BackupRestoreVerificationResult;
use App\Domain\Operations\Enums\BackupStatus;
use App\Domain\Operations\Exceptions\BackupRequestConflict;
use App\Domain\Operations\Models\BackupLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class BackupLifecycleService
{
    public function __construct(
        private PermissionChecker $permissions,
        private AuditLogger $audit,
    ) {}

    public function request(BackupRequest $request): BackupLog
    {
        $this->assertValidCorrelationId($request->correlationId);
        $this->authorize($request->actor, 'backup.run');
        $payloadHash = $this->requestPayloadHash($request);

        try {
            return DB::transaction(function () use ($request, $payloadHash): BackupLog {
                $existing = $this->lockedByOperatorKey($request);
                if ($existing !== null) {
                    return $this->resolveExistingRequest($existing, $payloadHash);
                }

                $backup = new BackupLog;
                $backup->forceFill([
                    'backup_pair_uuid' => (string) Str::uuid(),
                    'initiated_by' => $request->actor->id,
                    'operator_key' => $request->operatorKey,
                    'request_payload_hash' => $payloadHash,
                    'status' => BackupStatus::Pending,
                    'database_location_alias' => $request->artifacts->database->locationAlias,
                    'media_location_alias' => $request->artifacts->media->locationAlias,
                    'database_sha256' => $request->artifacts->database->sha256,
                    'media_sha256' => $request->artifacts->media->sha256,
                    'database_size_bytes' => $request->artifacts->database->sizeBytes,
                    'media_size_bytes' => $request->artifacts->media->sizeBytes,
                    'retention_until' => $request->retentionUntil,
                    'started_at' => now(),
                ]);
                $backup->save();

                $lockedBackup = BackupLog::query()->whereKey($backup->getKey())->lockForUpdate()->firstOrFail();
                $this->audit->record($request->actor, 'operations.backup.requested', $lockedBackup, [], $this->backupAuditValues($lockedBackup), $request->correlationId);

                return $lockedBackup;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->isOperatorKeyUniqueViolation($exception)) {
                throw $exception;
            }

            return DB::transaction(function () use ($request, $payloadHash): BackupLog {
                $existing = $this->lockedByOperatorKey($request);
                if ($existing === null) {
                    throw new LogicException('Backup operator-key race could not be resolved.');
                }

                return $this->resolveExistingRequest($existing, $payloadHash);
            }, attempts: 3);
        }
    }

    public function markProcessing(User $actor, BackupLog $backup, string $correlationId): void
    {
        $this->assertValidCorrelationId($correlationId);
        $this->authorize($actor, 'backup.run');
        $this->transition($actor, $backup, BackupStatus::Processing, null, $correlationId);
    }

    public function markSucceeded(User $actor, BackupLog $backup, string $correlationId): void
    {
        $this->assertValidCorrelationId($correlationId);
        $this->authorize($actor, 'backup.run');
        $this->transition($actor, $backup, BackupStatus::Succeeded, null, $correlationId);
    }

    public function markFailed(User $actor, BackupLog $backup, string $errorReference, string $correlationId): void
    {
        $this->assertValidCorrelationId($correlationId);
        $this->authorize($actor, 'backup.run');
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,79}$/', $errorReference) !== 1) {
            throw new InvalidArgumentException('Backup error reference is invalid.');
        }

        $this->transition($actor, $backup, BackupStatus::Failed, $errorReference, $correlationId);
    }

    public function recordRestoreVerification(User $actor, BackupLog $backup, string $verificationTargetAlias, string $evidenceReference, bool $passed, string $correlationId): void
    {
        $this->assertValidCorrelationId($correlationId);
        $this->authorize($actor, 'backup.restore');
        if (! $this->isIsolatedVerificationAlias($verificationTargetAlias)) {
            throw new InvalidArgumentException('Restore verification target must be an isolated verification artifact.');
        }
        if (! $this->isValidEvidenceReference($evidenceReference)) {
            throw new InvalidArgumentException('Restore verification evidence reference is invalid.');
        }

        DB::transaction(function () use ($actor, $backup, $verificationTargetAlias, $evidenceReference, $passed, $correlationId): void {
            $lockedBackup = BackupLog::query()->whereKey($backup->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedBackup->status !== BackupStatus::Succeeded) {
                throw new LogicException('Only completed backups may be restore-verified.');
            }

            $oldValues = $this->backupAuditValues($lockedBackup);
            $lockedBackup->forceFill([
                'restore_tested_at' => now(),
                'restore_verification_result' => $passed ? BackupRestoreVerificationResult::Passed : BackupRestoreVerificationResult::Failed,
                'restore_verification_target_alias' => $verificationTargetAlias,
                'restore_verification_evidence_reference' => $evidenceReference,
            ]);
            $lockedBackup->save();

            $this->audit->record($actor, 'operations.backup.restore_verified', $lockedBackup, $oldValues, $this->backupAuditValues($lockedBackup), $correlationId);
        }, attempts: 3);
    }

    private function transition(User $actor, BackupLog $backup, BackupStatus $target, ?string $errorReference, string $correlationId): void
    {
        DB::transaction(function () use ($actor, $backup, $target, $errorReference, $correlationId): void {
            $lockedBackup = BackupLog::query()->whereKey($backup->getKey())->lockForUpdate()->firstOrFail();
            $current = $lockedBackup->status;
            // Repeating the same target is an explicit safe idempotent no-op: no mutation and no audit.
            if ($current === $target) {
                return;
            }
            if (! $current->canTransitionTo($target)) {
                throw new LogicException("Backup status cannot transition from {$current->value} to {$target->value}.");
            }

            $oldValues = $this->backupAuditValues($lockedBackup);
            $lockedBackup->forceFill([
                'status' => $target,
                'error_reference' => $errorReference,
                'finished_at' => in_array($target, [BackupStatus::Succeeded, BackupStatus::Failed], true) ? now() : null,
            ]);
            $lockedBackup->save();

            $this->audit->record($actor, 'operations.backup.'.strtolower($target->name), $lockedBackup, $oldValues, $this->backupAuditValues($lockedBackup), $correlationId);
        }, attempts: 3);
    }

    private function lockedByOperatorKey(BackupRequest $request): ?BackupLog
    {
        return BackupLog::query()
            ->where('initiated_by', $request->actor->id)
            ->where('operator_key', $request->operatorKey)
            ->lockForUpdate()
            ->first();
    }

    private function resolveExistingRequest(BackupLog $backup, string $payloadHash): BackupLog
    {
        if ($backup->request_payload_hash !== $payloadHash) {
            throw new BackupRequestConflict;
        }

        return $backup;
    }

    private function requestPayloadHash(BackupRequest $request): string
    {
        return hash('sha256', json_encode([
            'database_alias' => $request->artifacts->database->locationAlias,
            'database_sha256' => $request->artifacts->database->sha256,
            'database_size_bytes' => $request->artifacts->database->sizeBytes,
            'media_alias' => $request->artifacts->media->locationAlias,
            'media_sha256' => $request->artifacts->media->sha256,
            'media_size_bytes' => $request->artifacts->media->sizeBytes,
            'retention_until' => $request->retentionUntil->utc()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }

    private function isOperatorKeyUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'backup_logs_initiated_by_operator_key_unique')
            || str_contains($message, 'unique constraint failed: backup_logs.initiated_by, backup_logs.operator_key');
    }

    /** @return array<string, int|string|null> */
    private function backupAuditValues(BackupLog $backup): array
    {
        return [
            'backup_pair_uuid' => $backup->backup_pair_uuid,
            'status' => $backup->status->value,
            'database_location_alias' => $backup->database_location_alias,
            'media_location_alias' => $backup->media_location_alias,
            'database_sha256' => $backup->database_sha256,
            'media_sha256' => $backup->media_sha256,
            'database_size_bytes' => $backup->database_size_bytes,
            'media_size_bytes' => $backup->media_size_bytes,
            'retention_until' => $this->auditDateValue($backup->getAttribute('retention_until')),
            'started_at' => $this->auditDateValue($backup->getAttribute('started_at')),
            'finished_at' => $this->auditDateValue($backup->getAttribute('finished_at')),
            'error_reference' => $backup->error_reference,
            'restore_tested_at' => $this->auditDateValue($backup->getAttribute('restore_tested_at')),
            'restore_verification_result' => $backup->restore_verification_result?->value,
            'restore_verification_target_alias' => $backup->restore_verification_target_alias,
            'restore_verification_evidence_reference' => $backup->restore_verification_evidence_reference,
        ];
    }

    private function auditDateValue(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toIso8601String() : null;
    }

    private function assertValidCorrelationId(string $correlationId): void
    {
        if (! Str::isUuid($correlationId)) {
            throw new InvalidArgumentException('Backup correlation ID is invalid.');
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki izin operasi backup.');
        }
    }

    private function isIsolatedVerificationAlias(string $alias): bool
    {
        if (preg_match('/^verify-[A-Za-z0-9][A-Za-z0-9._-]{2,75}$/', $alias) !== 1) {
            return false;
        }

        return ! str_contains(strtolower($alias), 'prod')
            && ! str_contains(strtolower($alias), 'live')
            && ! str_contains(strtolower($alias), 'primary')
            && ! str_contains(strtolower($alias), 'current');
    }

    private function isValidEvidenceReference(string $reference): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $reference) === 1;
    }
}
