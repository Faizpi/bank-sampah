<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class ApplicationConfigurationTest extends TestCase
{
    public function test_application_uses_the_business_timezone_and_locale(): void
    {
        self::assertSame('Asia/Jakarta', config('app.timezone'));
        self::assertSame('id', config('app.locale'));
        self::assertSame('id', config('app.fallback_locale'));
    }
}
