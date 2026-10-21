<?php

declare(strict_types=1);

use App\Domain\Operations\Services\DeploymentTopologyValidator;

beforeEach(function (): void {
    $fixture = storage_path('app/hosting-'.bin2hex(random_bytes(6)));
    $this->releaseRoot = $fixture.'/bank-sampah';
    $this->documentRoot = $fixture.'/public_html';
    foreach ([$this->releaseRoot.'/public/build', $this->documentRoot.'/build'] as $directory) {
        mkdir($directory, 0755, true);
    }
    foreach ([$this->releaseRoot.'/public', $this->documentRoot] as $directory) {
        copy(public_path('index.php'), $directory.'/index.php');
        file_put_contents($directory.'/build/manifest.json', '{}');
    }
    config()->set([
        'app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://example.invalid',
        'session.secure' => true, 'queue.default' => 'sync', 'operations.queue.worker_mode' => 'none',
        'operations.deployment.layout' => 'split',
        'operations.deployment.release_root' => $this->releaseRoot,
        'operations.deployment.document_root' => $this->documentRoot,
        'operations.deployment.vite_manifest' => $this->releaseRoot.'/public/build/manifest.json',
        'operations.deployment.exposed_paths' => [],
    ]);
});

it('accepts explicit sibling public_html only with matching front controller and manifest', function (): void {
    expect(app(DeploymentTopologyValidator::class)->validate()->passes())->toBeTrue();
});

it('does not weaken the default standard document root policy', function (): void {
    config()->set('operations.deployment.layout', 'standard');
    expect(app(DeploymentTopologyValidator::class)->validate()->issueCodes())->toContain('document_root_outside_public');
});

it('rejects stale files or exposed private source in public_html', function (string $file): void {
    file_put_contents($this->documentRoot.'/'.$file, 'stale-or-private');
    expect(app(DeploymentTopologyValidator::class)->validate()->issueCodes())->toContain('document_root_outside_public');
})->with(['index.php', 'build/manifest.json', '.env', 'artisan', 'composer.json']);

it('rejects nested public_html or a document root containing the private application', function (): void {
    foreach ([$this->releaseRoot.'/public', dirname($this->releaseRoot)] as $documentRoot) {
        config()->set('operations.deployment.document_root', $documentRoot);
        expect(app(DeploymentTopologyValidator::class)->validate()->issueCodes())->toContain('document_root_outside_public');
    }
});
