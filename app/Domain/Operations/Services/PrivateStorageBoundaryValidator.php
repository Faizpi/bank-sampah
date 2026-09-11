<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use Throwable;

final class PrivateStorageBoundaryValidator
{
    public function validate(): PrivateStorageBoundaryResult
    {
        try {
            $disk = config('filesystems.disks.media_private');
            if (! is_array($disk) || ($disk['driver'] ?? null) !== 'local' || ! is_string($disk['root'] ?? null) || $disk['root'] === '') {
                return PrivateStorageBoundaryResult::invalid('private_storage_configuration_invalid');
            }

            $root = $this->canonicalPath($disk['root']);
            $storage = $this->canonicalPath(storage_path());
            $public = $this->canonicalPath(public_path());

            if ($root === null || $storage === null || $public === null || ! is_dir($root) || ! is_writable($root)) {
                return PrivateStorageBoundaryResult::invalid('private_storage_unavailable');
            }

            if (! $this->isStrictChild($root, $storage) || $this->isSameOrChild($root, $public)) {
                return PrivateStorageBoundaryResult::invalid('private_storage_unavailable');
            }

            return PrivateStorageBoundaryResult::valid();
        } catch (Throwable) {
            return PrivateStorageBoundaryResult::invalid('private_storage_unavailable');
        }
    }

    private function canonicalPath(string $path): ?string
    {
        $normalized = $this->normalizePath($path);
        if ($normalized === '') {
            return null;
        }

        $canonical = realpath($normalized);
        if (! is_string($canonical)) {
            return null;
        }

        return $this->normalizePath($canonical);
    }

    private function isStrictChild(string $path, string $parent): bool
    {
        return $path !== $parent && $this->isSameOrChild($path, $parent);
    }

    private function isSameOrChild(string $path, string $parent): bool
    {
        return $path === $parent || str_starts_with($path, $parent.DIRECTORY_SEPARATOR);
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if ($normalized !== DIRECTORY_SEPARATOR && ! preg_match('/^[A-Za-z]:'.preg_quote(DIRECTORY_SEPARATOR, '/').'$/', $normalized)) {
            $normalized = rtrim($normalized, DIRECTORY_SEPARATOR);
        }

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($normalized) : $normalized;
    }
}
