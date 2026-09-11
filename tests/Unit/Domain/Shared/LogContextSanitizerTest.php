<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use App\Domain\Shared\LogContextSanitizer;
use PHPUnit\Framework\TestCase;

final class LogContextSanitizerTest extends TestCase
{
    public function test_sensitive_values_are_redacted_recursively(): void
    {
        $context = [
            'actor_id' => 17,
            'password' => 'rahasia',
            'restore_verification_evidence_reference' => str_repeat('e', 43),
            'request' => [
                'api_token' => 'token-rahasia',
                'signed_url' => 'https://bank-sampah.test/evidence?signature=secret',
                'status' => 'final',
                'backup_pair_uuid' => '018f4ca4-2e67-7c16-a455-8f610f6f5642',
                'database_sha256' => str_repeat('a', 64),
                'headers' => [
                    'Authorization' => 'Bearer secret',
                    'X-Correlation-ID' => 'safe-reference',
                ],
            ],
        ];

        self::assertSame([
            'actor_id' => 17,
            'password' => '[REDACTED]',
            'restore_verification_evidence_reference' => '[REDACTED]',
            'request' => [
                'api_token' => '[REDACTED]',
                'signed_url' => '[REDACTED]',
                'status' => 'final',
                'backup_pair_uuid' => '018f4ca4-2e67-7c16-a455-8f610f6f5642',
                'database_sha256' => str_repeat('a', 64),
                'headers' => [
                    'Authorization' => '[REDACTED]',
                    'X-Correlation-ID' => 'safe-reference',
                ],
            ],
        ], (new LogContextSanitizer)->sanitize($context));
    }
}
