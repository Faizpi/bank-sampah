<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class NotificationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_storage_has_the_documented_columns_and_indexes(): void
    {
        self::assertTrue(Schema::hasColumns('notifications', [
            'id', 'recipient_id', 'type', 'title', 'body', 'reference', 'read_at', 'scheduled_at', 'dedupe_key', 'created_at', 'updated_at',
        ]));
        self::assertSame([
            ['dedupe_key'],
            ['recipient_id', 'read_at', 'created_at'],
        ], $this->namedIndexes('notifications'));
    }

    public function test_factory_creates_an_unread_notification_for_its_recipient(): void
    {
        $notification = Notification::factory()->create();

        expect($notification->recipient)->toBeInstanceOf(User::class)
            ->and($notification->read_at)->toBeNull()
            ->and($notification->scheduled_at)->toBeNull()
            ->and($notification->recipient->notifications->contains($notification))->toBeTrue();
    }

    public function test_duplicate_dedupe_key_is_rejected(): void
    {
        Notification::factory()->create(['dedupe_key' => 'user:42:account.status_changed']);

        $this->expectException(QueryException::class);
        Notification::factory()->create(['dedupe_key' => 'user:42:account.status_changed']);
    }

    public function test_unread_notification_can_be_marked_as_read(): void
    {
        $notification = Notification::factory()->create(['read_at' => null]);
        $readAt = CarbonImmutable::parse('2026-07-30 12:30:00');

        $notification->update(['read_at' => $readAt]);

        expect($notification->refresh()->read_at)->toEqual($readAt)
            ->and($notification->read_at)->toBeInstanceOf(CarbonImmutable::class);
    }

    public function test_scheduled_notification_persists_its_future_state(): void
    {
        $scheduledAt = CarbonImmutable::now()->startOfSecond()->addHour();
        $notification = Notification::factory()->create(['scheduled_at' => $scheduledAt]);

        $persistedScheduledAt = $notification->scheduled_at;
        self::assertInstanceOf(CarbonImmutable::class, $persistedScheduledAt);

        expect($persistedScheduledAt)->toEqual($scheduledAt);
        self::assertTrue($persistedScheduledAt->isFuture());
        expect($notification->read_at)->toBeNull();
    }

    /** @return list<list<string>> */
    private function namedIndexes(string $table): array
    {
        $indexes = array_values(array_map(
            fn (object $index): array => array_column(\DB::select("PRAGMA index_info('{$index->name}')"), 'name'),
            array_filter(\DB::select("PRAGMA index_list('{$table}')"), static fn (object $index): bool => $index->origin !== 'pk'),
        ));
        sort($indexes);

        return $indexes;
    }
}
