<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final class LogContextSanitizer
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'file',
        'file_content',
        'file_contents',
        'filename',
        'path',
        'password',
        'password_confirmation',
        'restore_verification_evidence_reference',
        'secret',
        'session',
        'token',
        'url',
    ];

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    public function sanitize(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            $sanitized[$key] = match (true) {
                is_array($value) => $this->sanitize($value),
                is_object($value), is_resource($value) => '[REDACTED]',
                default => $value,
            };
        }

        return $sanitized;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '.'], '_', $key));

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_ends_with($normalized, "_{$sensitiveKey}")) {
                return true;
            }
        }

        return false;
    }
}
