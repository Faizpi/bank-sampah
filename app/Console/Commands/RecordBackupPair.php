<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Exceptions\BackupRequestConflict;
use App\Domain\Operations\Services\BackupArtifact;
use App\Domain\Operations\Services\BackupArtifactPair;
use App\Domain\Operations\Services\BackupLifecycleService;
use App\Domain\Operations\Services\BackupRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class RecordBackupPair extends Command
{
    protected $signature = 'operations:backup-record
        {--actor-id= : Active user ID authorized to run backup operations}
        {--database-alias= : Sanitized database artifact location alias}
        {--database-sha256= : Lowercase SHA-256 checksum for the database artifact}
        {--database-size-bytes= : Positive database artifact size in bytes}
        {--media-alias= : Sanitized media artifact location alias}
        {--media-sha256= : Lowercase SHA-256 checksum for the media artifact}
        {--media-size-bytes= : Positive media artifact size in bytes}
        {--retention-until= : Future retention timestamp in ISO-8601 or Y-m-d format}
        {--operator-key= : Explicit operator idempotency key for this metadata request}
        {--correlation-id= : Correlation identifier for the audit record}';

    protected $description = 'Record backup-pair metadata without dumping data, touching files, or invoking external processes.';

    public function handle(BackupLifecycleService $lifecycle): int
    {
        $actorId = $this->positiveIntegerOption('actor-id');
        $databaseSize = $this->positiveIntegerOption('database-size-bytes');
        $mediaSize = $this->positiveIntegerOption('media-size-bytes');
        $databaseAlias = $this->stringOption('database-alias');
        $databaseSha256 = $this->stringOption('database-sha256');
        $mediaAlias = $this->stringOption('media-alias');
        $mediaSha256 = $this->stringOption('media-sha256');
        $retentionUntil = $this->stringOption('retention-until');
        $operatorKey = $this->stringOption('operator-key');
        $correlationId = $this->stringOption('correlation-id');

        if ($actorId === null || $databaseSize === null || $mediaSize === null || $databaseAlias === null || $databaseSha256 === null || $mediaAlias === null || $mediaSha256 === null || $retentionUntil === null || $operatorKey === null || $correlationId === null || ! Str::isUuid($correlationId)) {
            return $this->reportFailure('invalid-input');
        }

        try {
            $actor = User::query()->find($actorId);
            if (! $actor instanceof User) {
                return $this->reportFailure('actor-not-found');
            }

            $backup = $lifecycle->request(new BackupRequest(
                actor: $actor,
                artifacts: new BackupArtifactPair(
                    database: new BackupArtifact($databaseAlias, $databaseSha256, $databaseSize),
                    media: new BackupArtifact($mediaAlias, $mediaSha256, $mediaSize),
                ),
                retentionUntil: $this->parseRetentionUntil($retentionUntil),
                operatorKey: $operatorKey,
                correlationId: $correlationId,
            ));
        } catch (AuthorizationException) {
            return $this->reportFailure('permission-denied');
        } catch (BackupRequestConflict) {
            return $this->reportFailure('operator-key-conflict');
        } catch (InvalidArgumentException|InvalidFormatException) {
            return $this->reportFailure('invalid-input');
        } catch (Throwable) {
            return $this->reportFailure('infrastructure-unavailable');
        }

        $this->components->info('backup-pair: metadata-recorded');
        $this->line('backup-id: '.(string) $backup->getKey());

        return self::SUCCESS;
    }

    private function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);
        if (! is_string($value) || $value === '' || ! ctype_digit($value)) {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($parsed) ? $parsed : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function parseRetentionUntil(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?)?$/', $value) !== 1) {
            throw new InvalidArgumentException('Backup retention timestamp format is invalid.');
        }

        return CarbonImmutable::parse($value);
    }

    private function reportFailure(string $code): int
    {
        $this->components->error('backup-pair: '.$code);

        return self::FAILURE;
    }
}
