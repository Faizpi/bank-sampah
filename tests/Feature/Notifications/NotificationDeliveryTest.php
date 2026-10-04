<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Listeners\PersistNotification;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

final class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_dispatched_only_after_the_originating_transaction_commits(): void
    {
        Event::fake([NotificationRequested::class]);
        $recipient = User::factory()->create();
        $payload = $this->payload($recipient);

        DB::transaction(function () use ($payload): void {
            NotificationRequested::dispatch($payload);
            Event::assertNotDispatched(NotificationRequested::class);
        });

        Event::assertDispatched(NotificationRequested::class, fn (NotificationRequested $event): bool => $event->payload === $payload);
        self::assertContains(ShouldDispatchAfterCommit::class, class_implements(NotificationRequested::class));
    }

    public function test_event_dispatcher_queues_persist_notification_exactly_once_after_the_originating_transaction_commits(): void
    {
        $payload = $this->payload(User::factory()->create());

        DB::transaction(function () use ($payload): void {
            NotificationRequested::dispatch($payload);
            self::assertDatabaseCount('jobs', 0);
        });

        self::assertDatabaseCount('jobs', 1);
        self::assertStringContainsString('PersistNotification', DB::table('jobs')->sole()->payload);
    }

    public function test_persistence_is_idempotent_for_the_same_event_recipient_and_template(): void
    {
        $recipient = User::factory()->create();
        $listener = new PersistNotification;
        $event = new NotificationRequested($this->payload($recipient));

        $listener->handle($event);
        $listener->handle($event);

        self::assertDatabaseCount('notifications', 1);
        $notification = Notification::query()->sole();
        self::assertSame($event->payload->dedupeKey, $notification->dedupe_key);
        self::assertSame('/transactions/DEP-2026-0001', $notification->reference);
    }

    public function test_dedupe_key_is_deterministic_and_scoped_to_event_recipient_and_template(): void
    {
        $first = NotificationDedupeKey::for('deposit.finalized:DEP-2026-0001', 42, 'deposit-finalized-v1');

        self::assertSame($first, NotificationDedupeKey::for('deposit.finalized:DEP-2026-0001', 42, 'deposit-finalized-v1'));
        self::assertNotSame($first, NotificationDedupeKey::for('deposit.finalized:DEP-2026-0001', 43, 'deposit-finalized-v1'));
        self::assertNotSame($first, NotificationDedupeKey::for('deposit.finalized:DEP-2026-0001', 42, 'deposit-finalized-v2'));
        self::assertLessThanOrEqual(191, strlen($first));
    }

    public function test_payload_is_minimal_serializable_data_and_rejects_secret_bearing_content(): void
    {
        $payload = $this->payload(User::factory()->create());

        self::assertSame([
            'recipient_id', 'type', 'title', 'body', 'reference', 'dedupe_key', 'scheduled_at',
        ], array_keys($payload->toArray()));
        self::assertNotContains('password', array_keys($payload->toArray()));
        self::assertNotContains('token', array_keys($payload->toArray()));

        $this->expectException(RuntimeException::class);
        new NotificationPayload(
            recipientId: 1,
            type: 'account.security',
            title: 'Reset akun',
            body: 'Gunakan token reset rahasia berikut',
            reference: '/account',
            dedupeKey: str_repeat('a', 64),
        );
    }

    public function test_listener_has_bounded_retry_and_after_commit_queue_semantics(): void
    {
        $listener = new PersistNotification;

        self::assertInstanceOf(ShouldQueueAfterCommit::class, $listener);
        self::assertSame(3, $listener->tries);
        self::assertSame([10, 60], $listener->backoff());
        self::assertSame('database', $listener->connection);
    }

    public function test_queued_listener_failure_does_not_rollback_an_already_committed_originating_transaction(): void
    {
        $recipient = User::factory()->create();
        $invalidPayload = $this->payload($recipient, recipientId: PHP_INT_MAX);

        DB::transaction(function () use ($recipient, $invalidPayload): void {
            $recipient->forceFill(['name' => 'Transaksi Sudah Commit'])->save();
            NotificationRequested::dispatch($invalidPayload);

            self::assertDatabaseCount('jobs', 0);
        });

        self::assertDatabaseCount('jobs', 1);

        $job = Queue::connection('database')->pop('default');

        try {
            $job?->fire();
            self::fail('Queued notification persistence should fail for a missing recipient.');
        } catch (QueryException) {
            // The database queue executes outside the committed originating transaction.
        }

        self::assertSame('Transaksi Sudah Commit', $recipient->refresh()->name);
        self::assertDatabaseCount('notifications', 0);
    }

    private function payload(User $recipient, ?int $recipientId = null): NotificationPayload
    {
        return new NotificationPayload(
            recipientId: $recipientId ?? (int) $recipient->getKey(),
            type: 'deposit.finalized',
            title: 'Setoran selesai',
            body: 'Setoran DEP-2026-0001 telah selesai diproses.',
            reference: '/transactions/DEP-2026-0001',
            dedupeKey: NotificationDedupeKey::for(
                'deposit.finalized:DEP-2026-0001',
                $recipientId ?? (int) $recipient->getKey(),
                'deposit-finalized-v1',
            ),
        );
    }
}
