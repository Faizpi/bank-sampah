<?php

declare(strict_types=1);

use App\Support\Seeding\HostingDemoGuard;
use Tests\Fixtures\MasterDataPayload;

it('never allows the hosting installer against the ordinary SQLite test connection', function (): void {
    config()->set(['operations.hosting_demo.enabled' => true, 'operations.hosting_demo.database' => 'bank_sampah_test_hosting']);

    expect(fn () => app(HostingDemoGuard::class)->validateTarget('bank_sampah_test_hosting'))->toThrow(LogicException::class);
});

it('rejects missing or invalid private source data before installation', function (): void {
    config()->set('app.master_seed_path', storage_path('app/does-not-exist.json'));
    expect(fn () => app(HostingDemoGuard::class)->source())->toThrow(RuntimeException::class);

    $source = storage_path('app/invalid-source.json');
    file_put_contents($source, '{invalid');
    config()->set('app.master_seed_path', $source);
    expect(fn () => app(HostingDemoGuard::class)->source())->toThrow(Exception::class);
});

it('rejects source data inside the served public_html directory', function (): void {
    $documentRoot = storage_path('app/public_html');
    mkdir($documentRoot);
    $source = $documentRoot.'/master-data.json';
    file_put_contents($source, json_encode(MasterDataPayload::make(), JSON_THROW_ON_ERROR));
    config()->set(['operations.deployment.document_root' => $documentRoot, 'app.master_seed_path' => $source]);

    expect(fn () => app(HostingDemoGuard::class)->source())->toThrow(LogicException::class);
});

it('reads a valid private master dataset without creating a second application seeder', function (): void {
    $source = storage_path('app/master-data.json');
    file_put_contents($source, json_encode(MasterDataPayload::make(), JSON_THROW_ON_ERROR));
    config()->set('app.master_seed_path', $source);

    expect(app(HostingDemoGuard::class)->source()->tables['users'])->toHaveCount(5);
});
