<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Models\BackupLog;
use App\Domain\Operations\Services\RollbackPlan;
use App\Domain\Operations\Services\RollbackPlanValidator;
use Illuminate\Console\Command;
use Throwable;

final class ValidateRollbackPlan extends Command
{
    protected $signature = 'operations:validate-rollback
        {--schema-compatible : Confirm that the prior release supports the current schema}
        {--requires-restore : Declare that a consistent database and media restore is required}
        {--backup-id= : Verified backup-pair record required for a restore}
        {--automatic-migration-rollback : Reject an automatic destructive migration rollback request}';

    protected $description = 'Validate a rollback plan without switching releases, restoring data, or rolling back migrations.';

    public function handle(RollbackPlanValidator $validator): int
    {
        try {
            $backupId = $this->option('backup-id');
            $backup = is_string($backupId) && ctype_digit($backupId)
                ? BackupLog::query()->find((int) $backupId)
                : null;
            $plan = new RollbackPlan(
                schemaCompatible: (bool) $this->option('schema-compatible'),
                requiresDatabaseMediaRestore: (bool) $this->option('requires-restore'),
                automaticMigrationRollbackRequested: (bool) $this->option('automatic-migration-rollback'),
                verifiedBackup: $backup,
            );
            $result = $validator->validate($plan);

            $this->line($plan->schemaCompatible ? 'rollback-mode: code-compatible' : 'rollback-mode: database-media-restore');
            if ($result->passes()) {
                $this->components->info('rollback-plan: valid');

                return self::SUCCESS;
            }

            $this->components->error('rollback-plan: invalid');
            foreach ($result->issueCodes() as $issue) {
                $this->line("issue: {$issue}");
            }

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->error('rollback-plan: infrastructure-unavailable');

            return self::FAILURE;
        }
    }
}
