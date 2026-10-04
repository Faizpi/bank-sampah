<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Operations\Services\OperationalHealthService;
use Illuminate\Http\JsonResponse;

final readonly class OperationalHealthController
{
    public function __construct(private OperationalHealthService $health) {}

    public function __invoke(): JsonResponse
    {
        $result = $this->health->check();
        $status = $result->isHealthy() ? 'ok' : 'degraded';

        return response()
            ->json([
                'status' => $status,
                'checks' => $result->toArray(),
            ], $result->isHealthy() ? 200 : 503)
            ->header('Cache-Control', 'private, no-store');
    }
}
