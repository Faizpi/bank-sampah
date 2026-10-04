<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AuditReconciliation\Services\AuditRetentionService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ExecuteAuditRetention extends Command
{
    protected $signature = 'operations:audit-retention
        {--actor-id= : Active user ID authorized to execute audit retention}
        {--before= : Delete eligible audit rows before this Y-m-d cutoff}
        {--dry-run : Preview eligible and protected rows without deleting}';

    protected $description = 'Preview or execute bounded, protected audit-log retention.';

    public function handle(AuditRetentionService $retention): int
    {
        $actorId = $this->positiveIntegerOption('actor-id');
        $before = $this->option('before');
        if ($actorId === null || ! is_string($before) || $before === '') {
            return $this->failure('invalid-input');
        }

        $actor = User::query()->find($actorId);
        if (! $actor instanceof User) {
            return $this->failure('actor-not-found');
        }

        try {
            if ((bool) $this->option('dry-run')) {
                $preview = $retention->preview($actor, $before);
                $this->components->info(sprintf('audit-retention: preview; deletable: %d; protected: %d.', $preview->deletableCount, $preview->protectedCount));
            } else {
                $count = $retention->execute($actor, $before);
                $this->components->info('audit-retention: executed; deleted: '.$count.'.');
            }
        } catch (AuthorizationException) {
            return $this->failure('permission-denied');
        } catch (ValidationException) {
            return $this->failure('invalid-input');
        } catch (Throwable) {
            return $this->failure('infrastructure-unavailable');
        }

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

    private function failure(string $code): int
    {
        $this->components->error('audit-retention: '.$code);

        return self::FAILURE;
    }
}
