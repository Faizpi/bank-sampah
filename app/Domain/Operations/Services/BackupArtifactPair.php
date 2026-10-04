<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use InvalidArgumentException;

final readonly class BackupArtifactPair
{
    public function __construct(
        public BackupArtifact $database,
        public BackupArtifact $media,
    ) {
        if ($database->locationAlias === $media->locationAlias) {
            throw new InvalidArgumentException('Database and media backup aliases must differ.');
        }
    }
}
