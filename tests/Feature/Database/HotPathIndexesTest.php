<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class HotPathIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hot_path_indexes_exist(): void
    {
        self::assertTrue(Schema::hasIndex('users', 'users_deleted_at_index'));
        self::assertTrue(Schema::hasIndex('pickup_requests', 'pickup_requests_status_scheduled_date_index'));
        self::assertTrue(Schema::hasIndex('report_exports', 'report_exports_expires_at_status_index'));
        self::assertTrue(Schema::hasIndex('notification_delivery_failures', 'notification_delivery_failures_last_attempted_at_index'));
    }
}
