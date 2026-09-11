<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class DatabaseConfigurationTest extends TestCase
{
    public function test_mariadb_connection_uses_required_storage_settings(): void
    {
        self::assertSame('mariadb', config('database.connections.mariadb.driver'));
        self::assertSame('utf8mb4', config('database.connections.mariadb.charset'));
        self::assertSame('utf8mb4_unicode_ci', config('database.connections.mariadb.collation'));
        self::assertSame('InnoDB', config('database.connections.mariadb.engine'));
        self::assertTrue(config('database.connections.mariadb.strict'));
    }

    public function test_automated_tests_use_an_isolated_in_memory_database(): void
    {
        self::assertSame('sqlite', config('database.default'));
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
        self::assertTrue(config('database.connections.sqlite.foreign_key_constraints'));
    }
}
