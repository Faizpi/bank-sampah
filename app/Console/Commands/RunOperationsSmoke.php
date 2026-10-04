<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Services\PrivateStorageBoundaryValidator;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class RunOperationsSmoke extends Command
{
    public function __construct(
        private readonly PrivateStorageBoundaryValidator $privateStorageBoundary,
    ) {
        parent::__construct();
    }

    protected $signature = 'operations:smoke';

    protected $description = 'Run read-only, sanitized operational smoke checks without changing application data.';

    public function handle(): int
    {
        $checks = [
            'health-route' => $this->hasHealthResponse(),
            'db-connectivity' => $this->hasDatabaseConnectivity(),
            'private-disk' => $this->hasWritablePrivateDisk(),
            'vite-manifest' => is_file((string) config('operations.deployment.vite_manifest', public_path('build/manifest.json'))),
            'config-cache' => is_file(base_path('bootstrap/cache/config.php')),
            'scheduler-topology' => in_array(config('operations.scheduler.topology'), ['cron', 'cron-oneshot'], true),
        ];

        foreach ($checks as $name => $passed) {
            $this->line("{$name}: ".($passed ? 'ok' : 'failed'));
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function hasHealthResponse(): bool
    {
        try {
            $request = Request::create('/health', 'GET');
            $response = app()->handle($request);

            return $response->getStatusCode() === Response::HTTP_OK
                && $response->getContent() === '{"status":"ok"}'
                && str_contains((string) $response->headers->get('Cache-Control'), 'private')
                && str_contains((string) $response->headers->get('Cache-Control'), 'no-store')
                && str_contains((string) $response->headers->get('Cache-Control'), 'no-cache')
                && str_contains((string) $response->headers->get('Cache-Control'), 'must-revalidate')
                && str_contains((string) $response->headers->get('Cache-Control'), 'max-age=0')
                && $response->headers->get('Pragma') === 'no-cache'
                && $response->headers->get('Expires') === '0'
                && $response->headers->get('X-Content-Type-Options') === 'nosniff';
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasDatabaseConnectivity(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasWritablePrivateDisk(): bool
    {
        return $this->privateStorageBoundary->validate()->isValid();
    }
}
