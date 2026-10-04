<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class BackupRequest
{
    public function __construct(
        public User $actor,
        public BackupArtifactPair $artifacts,
        public CarbonImmutable $retentionUntil,
        public string $operatorKey,
        public string $correlationId,
    ) {
        if ($retentionUntil->isPast()) {
            throw new InvalidArgumentException('Backup retention target must be in the future.');
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,190}$/', $operatorKey) !== 1) {
            throw new InvalidArgumentException('Backup operator key is invalid.');
        }

        if (! Str::isUuid($correlationId)) {
            throw new InvalidArgumentException('Backup correlation ID is invalid.');
        }
    }
}
