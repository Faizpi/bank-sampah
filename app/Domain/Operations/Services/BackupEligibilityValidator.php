<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Enums\BackupRestoreVerificationResult;
use App\Domain\Operations\Enums\BackupStatus;
use App\Domain\Operations\Models\BackupLog;
use Carbon\CarbonInterface;

final readonly class BackupEligibilityValidator
{
    public function isEligible(?BackupLog $backup, CarbonInterface $now): bool
    {
        return $backup !== null
            && $backup->getRawOriginal('status') === BackupStatus::Succeeded->value
            && $this->hasCurrentRetention($backup, $now)
            && $this->hasValidTemporalOrder($backup, $now)
            && $backup->getRawOriginal('restore_verification_result') === BackupRestoreVerificationResult::Passed->value
            && $this->isValidEvidenceReference($backup->getAttribute('restore_verification_evidence_reference'))
            && $this->isIsolatedVerificationAlias($backup->getAttribute('restore_verification_target_alias'))
            && $this->hasValidArtifactMetadata($backup);
    }

    private function hasCurrentRetention(BackupLog $backup, CarbonInterface $now): bool
    {
        $retentionUntil = $backup->getAttribute('retention_until');

        return $retentionUntil instanceof CarbonInterface && $retentionUntil->isAfter($now);
    }

    private function hasValidTemporalOrder(BackupLog $backup, CarbonInterface $now): bool
    {
        $startedAt = $backup->getAttribute('started_at');
        $finishedAt = $backup->getAttribute('finished_at');
        $restoreTestedAt = $backup->getAttribute('restore_tested_at');

        return $startedAt instanceof CarbonInterface
            && $finishedAt instanceof CarbonInterface
            && $restoreTestedAt instanceof CarbonInterface
            && $startedAt->lessThanOrEqualTo($finishedAt)
            && $finishedAt->lessThanOrEqualTo($restoreTestedAt)
            && $restoreTestedAt->lessThanOrEqualTo($now);
    }

    private function hasValidArtifactMetadata(BackupLog $backup): bool
    {
        $databaseAlias = $backup->getAttribute('database_location_alias');
        $mediaAlias = $backup->getAttribute('media_location_alias');
        $databaseChecksum = $backup->getAttribute('database_sha256');
        $mediaChecksum = $backup->getAttribute('media_sha256');
        $databaseSize = $backup->getAttribute('database_size_bytes');
        $mediaSize = $backup->getAttribute('media_size_bytes');

        if (! is_string($databaseAlias) || ! is_string($mediaAlias)
            || ! $this->isValidAlias($databaseAlias) || ! $this->isValidAlias($mediaAlias)
            || ! is_string($databaseChecksum) || ! is_string($mediaChecksum)
            || preg_match('/^[a-f0-9]{64}$/', $databaseChecksum) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $mediaChecksum) !== 1
            || ! is_int($databaseSize) || ! is_int($mediaSize)
            || $databaseSize < 1 || $mediaSize < 1) {
            return false;
        }

        $aliases = [strtolower($databaseAlias), strtolower($mediaAlias)];

        return count(array_unique($aliases)) === count($aliases);
    }

    private function isValidAlias(string $alias): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,79}$/', $alias) === 1;
    }

    private function isValidEvidenceReference(mixed $reference): bool
    {
        return is_string($reference) && preg_match('/^[A-Za-z0-9_-]{43}$/', $reference) === 1;
    }

    private function isIsolatedVerificationAlias(mixed $alias): bool
    {
        if (! is_string($alias) || preg_match('/^verify-[A-Za-z0-9][A-Za-z0-9._-]{2,75}$/', $alias) !== 1) {
            return false;
        }

        return ! str_contains(strtolower($alias), 'prod')
            && ! str_contains(strtolower($alias), 'live')
            && ! str_contains(strtolower($alias), 'primary')
            && ! str_contains(strtolower($alias), 'current');
    }
}
