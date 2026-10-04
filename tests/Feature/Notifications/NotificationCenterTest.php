<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Http\Middleware\EnsureSessionIsFresh;
use App\Http\Middleware\RequirePermission;
use App\Livewire\Notifications\NotificationCenter;
use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

final class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_only_the_authenticated_recipients_notifications(): void
    {
        $recipient = User::factory()->create();
        $otherRecipient = User::factory()->create();
        $own = Notification::factory()->create([
            'recipient_id' => $recipient->getKey(),
            'type' => 'deposit.finalized',
            'title' => 'Notifikasi milik saya',
        ]);
        Notification::factory()->create([
            'recipient_id' => $otherRecipient->getKey(),
            'title' => 'Notifikasi orang lain',
        ]);

        $this->notificationCenterFor($recipient)
            ->assertSee($own->title)
            ->assertDontSee('Notifikasi orang lain')
            ->assertSee('Belum dibaca');
    }

    public function test_it_marks_only_the_authenticated_recipients_unread_notification_as_read(): void
    {
        $recipient = User::factory()->create();
        $otherRecipient = User::factory()->create();
        $own = Notification::factory()->create(['recipient_id' => $recipient->getKey(), 'read_at' => null]);
        $other = Notification::factory()->create(['recipient_id' => $otherRecipient->getKey(), 'read_at' => null]);

        $this->notificationCenterFor($recipient)
            ->call('markAsRead', $own->getKey())
            ->assertHasNoErrors();

        self::assertNotNull($own->refresh()->read_at);
        self::assertNull($other->refresh()->read_at);
    }

    public function test_it_is_idempotent_when_own_notification_was_already_read_or_won_by_a_concurrent_request(): void
    {
        $recipient = User::factory()->create();
        $readAt = CarbonImmutable::parse('2026-08-01 10:00:00');
        $notification = Notification::factory()->create([
            'recipient_id' => $recipient->getKey(),
            'read_at' => $readAt,
        ]);

        $this->notificationCenterFor($recipient)
            ->call('markAsRead', $notification->getKey())
            ->assertHasNoErrors();

        self::assertSame($readAt->format('Y-m-d H:i:s'), $notification->refresh()->getRawOriginal('read_at'));
    }

    public function test_it_denies_attempts_to_mark_another_recipients_notification_as_read(): void
    {
        $recipient = User::factory()->create();
        $other = Notification::factory()->create(['read_at' => null]);

        $this->notificationCenterFor($recipient)
            ->call('markAsRead', $other->getKey())
            ->assertForbidden();

        self::assertNull($other->refresh()->read_at);
    }

    public function test_it_redacts_unapproved_and_sensitive_content_by_default(): void
    {
        $recipient = User::factory()->create();
        Notification::factory()->create([
            'recipient_id' => $recipient->getKey(),
            'type' => 'account.security',
            'title' => 'Kode pemulihan tersedia',
            'body' => 'Gunakan kode ABC-123 untuk melanjutkan.',
        ]);
        Notification::factory()->create([
            'recipient_id' => $recipient->getKey(),
            'type' => 'unknown.event',
            'title' => 'Judul internal yang tidak dikenal',
            'body' => 'Isi internal yang tidak dikenal.',
        ]);

        $this->notificationCenterFor($recipient)
            ->assertDontSee('Kode pemulihan tersedia')
            ->assertDontSee('Gunakan kode ABC-123')
            ->assertDontSee('Judul internal yang tidak dikenal')
            ->assertDontSee('Isi internal yang tidak dikenal')
            ->assertSee('Notifikasi baru')
            ->assertSee('Detail notifikasi tidak dapat ditampilkan.', 2);
    }

    public function test_it_renders_only_explicitly_approved_content_types(): void
    {
        $recipient = User::factory()->create();
        Notification::factory()->create([
            'recipient_id' => $recipient->getKey(),
            'type' => 'deposit.finalized',
            'title' => 'Setoran selesai',
            'body' => 'Setoran Anda telah selesai diproses.',
        ]);

        $this->notificationCenterFor($recipient)
            ->assertSee('Setoran selesai')
            ->assertSee('Setoran Anda telah selesai diproses.');
    }

    public function test_it_denies_reference_destinations_when_no_production_gate_is_registered(): void
    {
        $recipient = User::factory()->create();
        Notification::factory()->create([
            'recipient_id' => $recipient->getKey(),
            'reference' => '/riwayat',
        ]);

        $this->notificationCenterFor($recipient)
            ->assertDontSee('href="/riwayat"', false);
    }

    public function test_it_only_renders_a_destination_when_an_existing_gate_authorizes_a_safe_non_id_reference(): void
    {
        $recipient = User::factory()->create();
        Gate::define('view-notification-reference', static fn (User $actor, string $reference): bool => $actor->is($recipient) && $reference === '/riwayat');

        Notification::factory()->create([
            'recipient_id' => $recipient->getKey(),
            'reference' => '/riwayat',
            'title' => 'Riwayat tersedia',
        ]);
        Notification::factory()->create([
            'recipient_id' => $recipient->getKey(),
            'reference' => '/transactions/42',
            'title' => 'Referensi ID langsung',
        ]);

        $this->notificationCenterFor($recipient)
            ->assertSee('href="/riwayat"', false)
            ->assertDontSee('href="/transactions/42"', false);
    }

    public function test_it_renders_an_accessible_empty_state(): void
    {
        $this->notificationCenterFor(User::factory()->create())
            ->assertSee('Belum ada notifikasi')
            ->assertSee('aria-live="polite"', false);
    }

    public function test_notification_center_route_has_the_exact_effective_middleware_contract(): void
    {
        $route = Route::getRoutes()->getByName('notifications.index');

        self::assertNotNull($route);
        self::assertSame([
            'web',
            'auth',
            'session.fresh:30',
            'permission:notification.view',
        ], $route->middleware());
        self::assertSame($this->gatheredNotificationMiddleware(), app('router')->gatherRouteMiddleware($route));
    }

    public function test_notification_center_route_redirects_guests_to_login(): void
    {
        $this->get(route('notifications.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_notification_center_route_forbids_authenticated_actor_without_notification_view_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('notifications.index'))
            ->assertForbidden();
    }

    public function test_notification_center_route_redirects_a_stale_session_to_login_without_rendering_notifications(): void
    {
        $recipient = User::factory()->create();
        $this->grant($recipient, 'warga', 'notification.view');

        $this->withSession([
            EnsureSessionIsFresh::LAST_ACTIVITY_KEY => now()->subMinutes(30)->getTimestamp(),
        ])
            ->actingAs($recipient)
            ->get(route('notifications.index'))
            ->assertRedirectToRoute('login');

        $this->assertGuest();
    }

    /** @return list<string> */
    private function gatheredNotificationMiddleware(): array
    {
        return [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            Authenticate::class,
            SubstituteBindings::class,
            EnsureSessionIsFresh::class.':30',
            RequirePermission::class.':notification.view',
        ];
    }

    private function grant(User $user, string $roleName, string ...$permissionNames): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => "Test role {$roleName}"],
        );

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                ['description' => "Test permission {$permissionName}"],
            );
            $role->permissions()->syncWithoutDetaching($permission);
        }

        $user->roles()->attach($role);
    }

    /** @return Testable<NotificationCenter> */
    private function notificationCenterFor(User $user): Testable
    {
        Livewire::actingAs($user);

        return Livewire::test(NotificationCenter::class);
    }
}
