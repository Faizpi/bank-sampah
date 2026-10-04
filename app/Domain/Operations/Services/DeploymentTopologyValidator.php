<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

final class DeploymentTopologyValidator
{
    public function validate(): DeploymentValidationResult
    {
        $issues = [];
        $releaseRoot = $this->normalized((string) config('operations.deployment.release_root', base_path()));
        $documentRoot = $this->normalized((string) config('operations.deployment.document_root', public_path()));
        $publicRoot = $this->normalized($releaseRoot.DIRECTORY_SEPARATOR.'public');
        $manifest = (string) config('operations.deployment.vite_manifest', public_path('build/manifest.json'));
        $queueDriver = (string) config('queue.default');
        $workerMode = (string) config('operations.queue.worker_mode', 'none');
        $appUrl = (string) config('app.url', '');
        $sessionSecure = (bool) config('session.secure', false);

        if ($documentRoot !== $publicRoot) {
            $issues[] = 'document_root_outside_public';
        }
        if (config('app.env') !== 'production') {
            $issues[] = 'production_environment_required';
        }
        if ((bool) config('app.debug')) {
            $issues[] = 'debug_enabled';
        }
        if (! is_string(config('app.key')) || config('app.key') === '') {
            $issues[] = 'app_key_missing';
        }
        if (filter_var($appUrl, FILTER_VALIDATE_URL) === false || ! str_starts_with(strtolower($appUrl), 'https://')) {
            $issues[] = 'https_application_url_required';
        }
        if (! $sessionSecure) {
            $issues[] = 'secure_session_cookie_required';
        }
        if (($queueDriver === 'sync' && $workerMode !== 'none')
            || ($queueDriver === 'database' && $workerMode !== 'oneshot')
            || ! in_array($queueDriver, ['sync', 'database'], true)
            || ! in_array($workerMode, ['none', 'oneshot'], true)) {
            $issues[] = 'unsafe_queue_worker_configuration';
        }
        if (! is_file($manifest)) {
            $issues[] = 'vite_manifest_missing';
        }
        if ($this->hasExposedSensitivePath()) {
            $issues[] = 'sensitive_source_path_exposed';
        }

        return new DeploymentValidationResult(array_values(array_unique($issues)));
    }

    private function hasExposedSensitivePath(): bool
    {
        $configured = config('operations.deployment.exposed_paths', []);
        if (! is_array($configured)) {
            return true;
        }

        $sensitivePaths = ['.env', '.git', 'app', 'bootstrap', 'config', 'database', 'storage', 'vendor', 'artisan', 'composer.json'];

        foreach ($configured as $path) {
            if (! is_string($path)) {
                return true;
            }
            if (in_array(trim(str_replace('\\', '/', $path), '/'), $sensitivePaths, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalized(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
