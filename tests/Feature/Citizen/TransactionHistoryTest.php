<?php

declare(strict_types=1);

namespace Tests\Feature\Citizen;

use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Livewire\Citizen\GroceryHistory;
use App\Livewire\Citizen\WithdrawalHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class TransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawal_history_is_owner_scoped_and_filters_status_number_and_dates(): void
    {
        $citizen = $this->citizenWithPermissions(['withdrawal.view']);
        $other = User::factory()->create();
        $matching = $this->withdrawal($citizen, 'WDR-CARI-001', WithdrawalStatus::Paid, '2026-08-10');
        $this->withdrawal($citizen, 'WDR-LAIN-002', WithdrawalStatus::Rejected, '2026-08-11');
        $this->withdrawal($other, 'WDR-CARI-RAHASIA', WithdrawalStatus::Paid, '2026-08-10');

        Livewire::actingAs($citizen)
            ->test(WithdrawalHistory::class)
            ->set('requestNumber', 'CARI')
            ->set('status', WithdrawalStatus::Paid->value)
            ->set('dateFrom', '2026-08-10')
            ->set('dateUntil', '2026-08-10')
            ->assertSee($matching->request_number)
            ->assertDontSee('WDR-LAIN-002')
            ->assertDontSee('WDR-CARI-RAHASIA')
            ->assertSeeHtml(route('citizen.withdrawal.show', $matching))
            ->assertSeeHtml(route('citizen.withdrawal.receipt', $matching))
            ->assertSeeHtml(route('citizen.deposit-history'))
            ->assertSeeHtml(route('citizen.grocery-history'));
    }

    public function test_grocery_history_is_owner_scoped_and_filters_status_number_and_dates(): void
    {
        $citizen = $this->citizenWithPermissions(['grocery.view']);
        $other = User::factory()->create();
        $matching = $this->redemption($citizen, 'GRC-CARI-001', GroceryStatus::Completed, '2026-08-10', 'Paket Beras');
        $this->redemption($citizen, 'GRC-LAIN-002', GroceryStatus::Rejected, '2026-08-11', 'Paket Minyak');
        $this->redemption($other, 'GRC-CARI-RAHASIA', GroceryStatus::Completed, '2026-08-10', 'Paket Rahasia');

        Livewire::actingAs($citizen)
            ->test(GroceryHistory::class)
            ->set('requestNumber', 'CARI')
            ->set('status', GroceryStatus::Completed->value)
            ->set('dateFrom', '2026-08-10')
            ->set('dateUntil', '2026-08-10')
            ->assertSee($matching->request_number)
            ->assertSee('Paket Beras')
            ->assertDontSee('GRC-LAIN-002')
            ->assertDontSee('GRC-CARI-RAHASIA')
            ->assertSeeHtml(route('citizen.grocery.show', $matching))
            ->assertSeeHtml(route('citizen.grocery.receipt', $matching))
            ->assertSeeHtml(route('citizen.deposit-history'))
            ->assertSeeHtml(route('citizen.withdrawal-history'));
    }

    public function test_new_history_routes_keep_existing_detail_routes_available(): void
    {
        self::assertSame('/warga/riwayat-pencairan', route('citizen.withdrawal-history', absolute: false));
        self::assertSame('/warga/riwayat-sembako', route('citizen.grocery-history', absolute: false));
        self::assertSame('/warga/pencairan/17', route('citizen.withdrawal.show', 17, false));
        self::assertSame('/warga/sembako/23', route('citizen.grocery.show', 23, false));
    }

    public function test_history_routes_resolve_with_expected_middleware_and_permissions(): void
    {
        $routes = app('router')->getRoutes();
        $expectedPermissions = [
            'citizen.deposit-history' => 'permission:deposit.view',
            'citizen.withdrawal-history' => 'permission:withdrawal.view',
            'citizen.grocery-history' => 'permission:grocery.view',
        ];

        foreach ($expectedPermissions as $routeName => $permission) {
            $route = $routes->getByName($routeName);
            self::assertNotNull($route);
            self::assertContains('auth', $route->gatherMiddleware());
            self::assertContains('session.fresh:30', $route->gatherMiddleware());
            self::assertContains($permission, $route->gatherMiddleware());
        }

        $citizen = $this->citizenWithPermissions(['deposit.view', 'withdrawal.view', 'grocery.view']);
        foreach (array_keys($expectedPermissions) as $routeName) {
            $response = $this->actingAs($citizen)->get(route($routeName))->assertOk();
            $response->assertSee('Riwayat');
            self::assertMatchesRegularExpression(
                '/href="[^"]*\/warga\/riwayat-setoran"\s+aria-current="page"/',
                $response->getContent(),
            );
        }
    }

    public function test_history_routes_deny_users_without_the_required_permissions(): void
    {
        $citizen = User::factory()->create();

        foreach (['citizen.deposit-history', 'citizen.withdrawal-history', 'citizen.grocery-history'] as $routeName) {
            $this->actingAs($citizen)->get(route($routeName))->assertForbidden();
        }
    }

    private function withdrawal(User $customer, string $number, WithdrawalStatus $status, string $createdAt): WithdrawalRequest
    {
        return WithdrawalRequest::factory()->create([
            'request_number' => $number,
            'customer_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'status' => $status,
            'created_at' => $createdAt.' 10:00:00',
            'updated_at' => $createdAt.' 10:00:00',
        ]);
    }

    private function redemption(User $customer, string $number, GroceryStatus $status, string $createdAt, string $packageName): GroceryRedemption
    {
        return GroceryRedemption::factory()->create([
            'request_number' => $number,
            'customer_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'status' => $status,
            'package_snapshot' => ['name' => $packageName, 'contents' => 'Kebutuhan pokok'],
            'created_at' => $createdAt.' 10:00:00',
            'updated_at' => $createdAt.' 10:00:00',
        ]);
    }

    /** @param list<string> $permissions */
    private function citizenWithPermissions(array $permissions): User
    {
        $citizen = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'citizen-transactions-'.str()->random(12),
            'description' => 'Citizen transaction history test role',
        ]);

        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                ['description' => 'View citizen transaction history'],
            );
            $role->permissions()->attach($permission);
        }

        $citizen->roles()->attach($role);

        return $citizen->fresh();
    }
}
