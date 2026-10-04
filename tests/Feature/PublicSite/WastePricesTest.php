<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\WasteMaster\Actions\ManageWastePricing;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WastePricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_prices_show_only_current_active_periods_without_private_fields(): void
    {
        $category = WasteCategory::factory()->create(['name' => 'Plastik']);
        $unit = WasteUnit::factory()->weight()->create(['code' => 'KG', 'symbol' => 'kg']);
        $condition = WasteCondition::factory()->create(['name' => 'Bersih']);
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create(['name' => 'Botol PET']);
        WasteMasterMutationGuard::run(fn (): array => $type->conditions()->sync([$condition->id]));
        $manager = User::factory()->create();
        $this->grant($manager, 'price.manage');
        $pricing = app(ManageWastePricing::class);
        $pricing->createPeriod($manager, $type, $condition, 3_000, CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'), CarbonImmutable::parse('2026-08-10', 'Asia/Jakarta'), (string) str()->uuid());
        $pricing->createPeriod($manager, $type, $condition, 3_500, CarbonImmutable::parse('2026-08-10', 'Asia/Jakarta'), null, (string) str()->uuid());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-11', 'Asia/Jakarta'));

        try {
            $response = $this->get(route('public.prices'));

            $response->assertOk()
                ->assertSee('Botol PET')
                ->assertSee('Rp3.500')
                ->assertDontSee('Rp3.000')
                ->assertDontSee('created_by');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_price_policy_allows_viewer_to_read_but_only_manager_to_create(): void
    {
        $viewer = User::factory()->create();
        $manager = User::factory()->create();
        $this->grant($viewer, 'price.view');
        $this->grant($manager, 'price.manage');
        $price = WastePrice::factory()->create();

        self::assertTrue($viewer->can('view', $price));
        self::assertFalse($viewer->can('create', WastePrice::class));
        self::assertTrue($manager->can('view', $price));
        self::assertTrue($manager->can('create', WastePrice::class));
        self::assertFalse($manager->can('update', $price));
        self::assertFalse($manager->can('delete', $price));
    }

    private function grant(User $user, string $permissionName): void
    {
        $role = Role::query()->create(['name' => "{$permissionName}-role-{$user->id}", 'description' => 'Price role']);
        $permission = Permission::query()->create(['name' => $permissionName, 'description' => 'Price permission']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
    }
}
