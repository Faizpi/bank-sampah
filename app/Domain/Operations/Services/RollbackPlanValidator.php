<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Models\BackupLog;

final readonly class RollbackPlanValidator
{
    public function __construct(
        private BackupEligibilityValidator $backupEligibility,
    ) {}

    public function validate(RollbackPlan $plan): RollbackValidationResult
    {
        $issues = [];

        if ($plan->automaticMigrationRollbackRequested) {
            $issues[] = 'automatic_destructive_migration_rollback_rejected';
        }
        if (! $plan->schemaCompatible && ! $plan->requiresDatabaseMediaRestore) {
            $issues[] = 'database_media_restore_required';
        }
        if ($plan->requiresDatabaseMediaRestore && ! $this->hasVerifiedBackupPair($plan)) {
            $issues[] = 'verified_database_media_backup_required';
        }

        return new RollbackValidationResult($issues);
    }

    private function hasVerifiedBackupPair(RollbackPlan $plan): bool
    {
        $backup = $this->persistedBackup($plan->verifiedBackup);

        return $this->backupEligibility->isEligible($backup, now());
    }

    private function persistedBackup(?BackupLog $backup): ?BackupLog
    {
        if ($backup === null) {
            return null;
        }

        $key = $backup->getKey();
        if ($key === null) {
            return null;
        }
        if (! is_int($key) && ! is_string($key)) {
            return null;
        }

        return BackupLog::query()->find($key);
    }
}
