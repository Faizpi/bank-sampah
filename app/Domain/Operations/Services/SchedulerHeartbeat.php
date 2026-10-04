<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

final class SchedulerHeartbeat
{
    public const EXPIRE_KEY = 'operations.scheduler.expire.last_success';

    public const PURGE_KEY = 'operations.scheduler.purge.last_success';

    public const MAX_SECONDS = 172800;

    public function record(string $key): void
    {
        if (! in_array($key, [self::EXPIRE_KEY, self::PURGE_KEY], true)) {
            throw new InvalidArgumentException('Unsupported scheduler heartbeat key.');
        }

        $ttl = min(self::MAX_SECONDS, max(60, (int) config('operations.scheduler.heartbeat_ttl_seconds', self::MAX_SECONDS)));
        if (! Cache::put($key, now()->getTimestamp(), $ttl)) {
            throw new RuntimeException('Scheduler heartbeat persistence failed.');
        }
    }

    public function exists(string $key): bool
    {
        return Cache::has($key);
    }

    public function isFresh(string $key): bool
    {
        $lastSuccess = Cache::get($key);
        $now = now()->getTimestamp();
        $freshest = $now - $this->freshnessSeconds();

        return is_int($lastSuccess) && $lastSuccess >= $freshest && $lastSuccess <= $now;
    }

    private function freshnessSeconds(): int
    {
        return min(self::MAX_SECONDS, max(60, (int) config('operations.scheduler.heartbeat_freshness_seconds', self::MAX_SECONDS)));
    }
}
