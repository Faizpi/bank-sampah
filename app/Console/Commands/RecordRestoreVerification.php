<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Models\BackupLog;
use App\Domain\Operations\Services\BackupLifecycleService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Throwable;

final class RecordRestoreVerification extends Command
{
    protected $signature = 'operations:backup-restore-verify
        {--actor-id= : Active user ID authorized to restore-verify backup metadata}
        {--backup-id= : Existing backup-pair record ID}
        {--verification-target-alias= : Isolated verification artifact alias}
        {--evidence-reference= : Opaque URL-safe restore-verification evidence reference}
        {--passed : Record a passed restore verification}
        {--failed : Record a failed restore verification}
        {--correlation-id= : Correlation identifier for the audit record}';

    protected $description = 'Record restore-verification metadata without restoring data, touching files, or invoking external processes.';

    public function handle(BackupLifecycleService $lifecycle): int
    {
        $actorId = $this->positiveIntegerOption('actor-id');
        $backupId = $this->positiveIntegerOption('backup-id');
        $targetAlias = $this->stringOption('verification-target-alias');
        $evidenceReference = $this->stringOption('evidence-reference');
        $correlationId = $this->stringOption('correlation-id');
        $passed = (bool) $this->option('passed');
        $failed = (bool) $this->option('failed');

        if ($actorId === null || $backupId === null || $targetAlias === null || $evidenceReference === null || $correlationId === null || $passed === $failed || ! Str::isUuid($correlationId)) {
            return $this->reportFailure('invalid-input');
        }

        try {
            $actor = User::query()->find($actorId);
            if (! $actor instanceof User) {
                return $this->reportFailure('actor-not-found');
            }

            $backup = BackupLog::query()->find($backupId);
            if (! $backup instanceof BackupLog) {
                return $this->reportFailure('backup-not-found');
            }

            $lifecycle->recordRestoreVerification(
                actor: $actor,
                backup: $backup,
                verificationTargetAlias: $targetAlias,
                evidenceReference: $evidenceReference,
                passed: $passed,
                correlationId: $correlationId,
            );
        } catch (AuthorizationException) {
            return $this->reportFailure('permission-denied');
        } catch (InvalidArgumentException|LogicException) {
            return $this->reportFailure('invalid-input');
        } catch (Throwable) {
            return $this->reportFailure('infrastructure-unavailable');
        }

        $this->components->info('restore-verification: metadata-recorded');
        $this->line('backup-id: '.(string) $backup->getKey());
        $this->line('result: '.($passed ? 'passed' : 'failed'));

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

    private function reportFailure(string $code): int
    {
        $this->components->error('restore-verification: '.$code);

        return self::FAILURE;
    }
}
