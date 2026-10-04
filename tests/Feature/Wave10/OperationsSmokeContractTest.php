<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

it('validates the exact health response through in-process request dispatch', function (): void {
    $response = app()->handle(Request::create('/health', 'GET'));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($response->getContent())->toBe('{"status":"ok"}');
    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate')
        ->toContain('max-age=0');
    expect($response->headers->get('Pragma'))->toBe('no-cache');
    expect($response->headers->get('Expires'))->toBe('0');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    $this->artisan('operations:smoke')
        ->expectsOutputToContain('health-route: ok');
});

it('fails the health check when the registered route returns the wrong contract', function (): void {
    $route = app('router')->getRoutes()->getByName('health');

    expect($route)->toBeInstanceOf(Route::class);
    $route->setAction([
        'uses' => static fn (): JsonResponse => response()->json(['status' => 'degraded']),
    ]);

    $this->artisan('operations:smoke')
        ->expectsOutputToContain('health-route: failed')
        ->assertExitCode(Command::FAILURE);
});

it('returns a failed health check without leaking infrastructure exceptions', function (): void {
    $kernel = Mockery::mock(HttpKernelContract::class);
    $kernel->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('private infrastructure detail'));
    app()->instance(HttpKernelContract::class, $kernel);

    $this->artisan('operations:smoke')
        ->expectsOutputToContain('health-route: failed')
        ->doesntExpectOutputToContain('private infrastructure detail')
        ->assertExitCode(Command::FAILURE);
});
