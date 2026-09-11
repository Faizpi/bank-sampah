<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use InvalidArgumentException;

final readonly class BackupArtifact
{
    public function __construct(
        public string $locationAlias,
        public string $sha256,
        public int $sizeBytes,
    ) {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,79}$/', $locationAlias) !== 1) {
            throw new InvalidArgumentException('Backup location alias is invalid.');
        }

        if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
            throw new InvalidArgumentException('Backup SHA-256 checksum is invalid.');
        }

        if ($sizeBytes < 1) {
            throw new InvalidArgumentException('Backup size must be positive.');
        }
    }
}
