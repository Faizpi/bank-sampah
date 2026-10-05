<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\CustomersRegions;

use App\Domain\CustomersRegions\Contracts\CustomerNumber;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Shared\InvalidValue;
use PHPUnit\Framework\TestCase;

final class W2ContractsTest extends TestCase
{
    public function test_customer_number_is_fixed_and_immutable(): void
    {
        $number = CustomerNumber::fromString('CST-12345678');

        self::assertSame('CST-12345678', $number->value());
        self::assertSame('CST-12345678', (string) $number);
        self::assertTrue($number->equals(CustomerNumber::fromString('CST-12345678')));
    }

    public function test_customer_number_rejects_non_canonical_values(): void
    {
        foreach (['12345678', 'CST-1234567', 'CST-123456789', 'cst-12345678', ' CST-12345678'] as $value) {
            try {
                CustomerNumber::fromString($value);
                self::fail('Invalid customer number was accepted.');
            } catch (InvalidValue) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_qr_token_is_random_url_safe_and_only_hash_is_persisted(): void
    {
        $token = QrToken::generate();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token->value());
        self::assertSame(hash('sha256', $token->value()), $token->hash());
        self::assertTrue($token->matches(QrToken::fromValue($token->value())));
        self::assertFalse($token->matches(QrToken::generate()));
    }
}
