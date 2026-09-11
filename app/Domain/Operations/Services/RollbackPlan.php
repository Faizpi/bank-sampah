<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Models\BackupLog;

final readonly class RollbackPlan
{
    public function __construct(
        public bool $schemaCompatible,
        public bool $requiresDatabaseMediaRestore,
        public bool $automaticMigrationRollbackRequested,
        public ?BackupLog $verifiedBackup,
    ) {}
}
