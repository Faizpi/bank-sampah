<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Http\Middleware\EnsureSessionIsFresh;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use ReflectionMethod;
use Tests\TestCase;

final class PersistentMiddlewareHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_persistent_filter_preserves_middleware_arguments(): void
    {
        $router = app('router');
        $route = $router->getRoutes()->getByName('officer.deposit-form');
        self::assertNotNull($route);

        $gathered = $router->gatherRouteMiddleware($route);
        self::assertContains('App\\Http\\Middleware\\RequirePermission:deposit.create', $gathered);
        self::assertContains('App\\Http\\Middleware\\EnsureSessionIsFresh:30', $gathered);

        $mechanism = app(PersistentMiddleware::class);
        $filter = new ReflectionMethod($mechanism, 'filterMiddlewareByPersistentMiddleware');
        $filter->setAccessible(true);
        $filtered = array_values($filter->invoke($mechanism, $gathered));

        // Livewire uses the `Class:argument` string only as a filter predicate
        // (Str::before($value, ':') == $registeredClass) and returns the original
        // value. Arguments must therefore survive into the re-applied pipeline.
        self::assertContains('App\\Http\\Middleware\\RequirePermission:deposit.create', $filtered);
        self::assertContains('App\\Http\\Middleware\\EnsureSessionIsFresh:30', $filtered);
    }

    public function test_allowed_actor_can_call_deposit_component_over_http(): void
    {
        [$snapshot] = $this->openDepositForm();

        $response = $this->addItem($snapshot)->assertOk();
        $updated = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);

        self::assertCount(1, $updated['data']['items'][0]);
        self::assertSame('GET', $updated['memo']['method']);
        self::assertSame(json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['path'], $updated['memo']['path']);
    }

    public function test_revoked_permission_denies_update_from_previously_allowed_page(): void
    {
        [$snapshot, $role] = $this->openDepositForm();
        $role->permissions()->detach(Permission::query()->where('name', 'deposit.create')->value('id'));

        $this->addItem($snapshot)->assertForbidden();
        $this->assertAuthenticated();
    }

    public function test_actor_without_permission_cannot_reuse_an_allowed_snapshot(): void
    {
        [$snapshot] = $this->openDepositForm();
        $this->actingAs(User::factory()->create());

        $this->addItem($snapshot)->assertForbidden();
    }

    public function test_update_at_idle_boundary_logs_out_instead_of_refreshing_expired_session(): void
    {
        $this->freezeTime();
        [$snapshot] = $this->openDepositForm();
        $this->travel(30)->minutes();

        $this->addItem($snapshot)->assertRedirect(route('login'));
        $this->assertGuest();
        self::assertNull(session(EnsureSessionIsFresh::LAST_ACTIVITY_KEY));

        $this->addItem($snapshot)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_valid_activity_refreshes_idle_timer_within_window(): void
    {
        $this->freezeTime();
        [$snapshot] = $this->openDepositForm();
        $this->travel(29)->minutes();

        $this->addItem($snapshot)->assertOk();
        self::assertSame(now()->getTimestamp(), session(EnsureSessionIsFresh::LAST_ACTIVITY_KEY));
    }

    public function test_later_idle_gap_still_expires_after_a_refresh(): void
    {
        $this->freezeTime();
        [$snapshot] = $this->openDepositForm();
        $this->travel(29)->minutes();
        $response = $this->addItem($snapshot)->assertOk();
        self::assertSame(now()->getTimestamp(), session(EnsureSessionIsFresh::LAST_ACTIVITY_KEY));

        $snapshot = $response->json('components.0.snapshot');
        self::assertIsString($snapshot);
        $this->travel(30)->minutes();

        // Livewire keeps a per-mechanism guard that re-applies middleware once per
        // request (see PersistentMiddleware::applyPersistentMiddleware). Production
        // starts a fresh process per click, so reset it to emulate the next request.
        app('livewire')->flushState();

        $this->addItem($snapshot)->assertRedirect(route('login'));
        $this->assertGuest();
        self::assertNull(session(EnsureSessionIsFresh::LAST_ACTIVITY_KEY));
    }

    /** @return array{string, Role} */
    private function openDepositForm(): array
    {
        self::assertSame('sqlite', config('database.default'));
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutVite();
        $actor = User::factory()->create();
        $customer = User::factory()->create();
        $role = Role::query()->create(['name' => 'http-officer', 'description' => 'HTTP middleware regression']);
        foreach (['deposit.create', 'user.view', 'user.view.all', 'customer.view'] as $name) {
            $permission = Permission::query()->create(['name' => $name, 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $actor->roles()->attach($role);
        $path = route('officer.deposit-form', $customer->id, false);
        $html = $this->actingAs($actor)->get($path)->assertOk()->getContent();
        self::assertIsString($html);
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decoded = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);
            if (($decoded['data']['customerId'] ?? null) === $customer->id) {
                self::assertSame(ltrim($path, '/'), $decoded['memo']['path']);
                self::assertSame('GET', $decoded['memo']['method']);

                return [$snapshot, $role];
            }
        }

        self::fail('The real deposit GET response must contain its signed component snapshot.');
    }

    private function addItem(string $snapshot): TestResponse
    {
        return $this->postJson(app(HandleRequests::class)->getUpdateUri(), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [['path' => '', 'method' => 'addItem', 'params' => []]],
            ]],
        ], ['X-Livewire' => 'true']);
    }
}
