<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use App\Domain\Shared\Result;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ResultTest extends TestCase
{
    public function test_success_exposes_only_its_value(): void
    {
        $result = Result::success('tersimpan');

        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isFailure());
        self::assertSame('tersimpan', $result->value());

        $this->expectException(LogicException::class);
        $result->error();
    }

    public function test_failure_exposes_only_its_error(): void
    {
        $result = Result::failure('saldo_tidak_cukup');

        self::assertTrue($result->isFailure());
        self::assertFalse($result->isSuccess());
        self::assertSame('saldo_tidak_cukup', $result->error());

        $this->expectException(LogicException::class);
        $result->value();
    }
}
