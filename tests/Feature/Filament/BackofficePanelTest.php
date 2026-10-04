<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Filament\Resources\AuditReconciliation\Models\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditReconciliation\Models\Reconciliations\ReconciliationResource;
use App\Filament\Resources\CustomersRegions\Models\Dusuns\DusunResource;
use App\Filament\Resources\CustomersRegions\Models\Rts\RtResource;
use App\Filament\Resources\CustomersRegions\Models\Rws\RwResource;
use App\Filament\Resources\CustomersRegions\Models\ServiceAreas\ServiceAreaResource;
use App\Filament\Resources\Groceries\Models\GroceryPackages\GroceryPackageResource;
use App\Filament\Resources\Groceries\Models\GroceryRedemptions\GroceryRedemptionResource;
use App\Filament\Resources\Identity\Models\CitizenVerifications\CitizenVerificationResource;
use App\Filament\Resources\Identity\Models\PasswordAssistances\PasswordAssistanceResource;
use App\Filament\Resources\Identity\Models\Permissions\PermissionResource;
use App\Filament\Resources\Identity\Models\Roles\RoleResource;
use App\Filament\Resources\Identity\Models\SessionInventories\SessionInventoryResource;
use App\Filament\Resources\Pickups\Models\PickupCapacities\PickupCapacityResource;
use App\Filament\Resources\Pickups\Models\PickupRequests\PickupRequestResource;
use App\Filament\Resources\WasteMaster\Models\WasteCategories\WasteCategoryResource;
use App\Filament\Resources\WasteMaster\Models\WasteConditions\WasteConditionResource;
use App\Filament\Resources\WasteMaster\Models\WastePrices\WastePriceResource;
use App\Filament\Resources\WasteMaster\Models\WasteTypes\WasteTypeResource;
use App\Filament\Resources\WasteMaster\Models\WasteUnits\WasteUnitResource;
use App\Filament\Resources\Withdrawals\Models\WithdrawalRequests\WithdrawalRequestResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BackofficePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_users_with_explicit_backoffice_access_can_access_the_technical_panel(): void
    {
        $admin = User::factory()->create();
        $superadmin = User::factory()->create();
        $this->grant($admin, 'admin', 'backoffice.access');
        $this->grant($superadmin, 'superadmin');

        $panel = Filament::getPanel('backoffice');

        self::assertTrue($admin->fresh()->canAccessPanel($panel));
        self::assertFalse($superadmin->fresh()->canAccessPanel($panel));
        self::assertFalse(User::factory()->create()->canAccessPanel($panel));

        $this->get('/backoffice')->assertRedirect('/backoffice/login');
        $this->actingAs($superadmin->fresh())->get('/backoffice')->assertForbidden();
        $this->actingAs($admin->fresh())->get('/backoffice/login')->assertRedirect('/backoffice');
    }

    public function test_backoffice_navigation_has_exactly_the_four_technical_groups(): void
    {
        $panel = Filament::getPanel('backoffice');

        self::assertSame(
            ['Operasional', 'Data Master', 'Program', 'Pengawasan'],
            array_map(static fn ($group): string => $group->getLabel(), $panel->getNavigationGroups()),
        );
    }

    public function test_backoffice_panel_discovers_only_the_regional_resources(): void
    {
        $panel = Filament::getPanel('backoffice');

        self::assertSame([app_path('Filament/Resources')], $panel->getResourceDirectories());
        self::assertSame(['App\\Filament\\Resources'], $panel->getResourceNamespaces());
        self::assertEqualsCanonicalizing([
            CitizenVerificationResource::class,
            PasswordAssistanceResource::class,
            SessionInventoryResource::class,
            PermissionResource::class,
            RoleResource::class,
            PickupRequestResource::class,
            PickupCapacityResource::class,
            DusunResource::class,
            RwResource::class,
            RtResource::class,
            ServiceAreaResource::class,
            WasteCategoryResource::class,
            WasteConditionResource::class,
            WasteTypeResource::class,
            WasteUnitResource::class,
            WastePriceResource::class,
            WithdrawalRequestResource::class,
            GroceryPackageResource::class,
            GroceryRedemptionResource::class,
            AuditLogResource::class,
            ReconciliationResource::class,
        ], array_values($panel->getResources()));
    }

    public function test_only_authorized_panel_users_see_regional_resources_under_data_master(): void
    {
        $regionalManager = User::factory()->create();
        $panelUserWithoutRegionalPermission = User::factory()->create();
        $this->grant($regionalManager, 'regional-admin', 'backoffice.access', 'region.manage');
        $this->grant($panelUserWithoutRegionalPermission, 'backoffice-user', 'backoffice.access');

        $panel = Filament::getPanel('backoffice');

        self::assertTrue($regionalManager->fresh()->canAccessPanel($panel));
        $this->actingAs($regionalManager->fresh());
        self::assertSame(['Area Pelayanan', 'Dusun', 'RT', 'RW'], $this->dataMasterNavigationLabels($panel));

        self::assertTrue($panelUserWithoutRegionalPermission->fresh()->canAccessPanel($panel));
        $this->actingAs($panelUserWithoutRegionalPermission->fresh());
        self::assertSame([], $this->dataMasterNavigationLabels($panel));
    }

    public function test_only_waste_viewers_and_managers_see_waste_resources_with_mutation_reserved_for_managers(): void
    {
        $viewer = User::factory()->create();
        $manager = User::factory()->create();
        $this->grant($viewer, 'waste-viewer', 'backoffice.access', 'waste.view');
        $this->grant($manager, 'waste-manager', 'backoffice.access', 'waste.manage');

        $panel = Filament::getPanel('backoffice');

        $this->actingAs($viewer->fresh());
        self::assertSame(['Jenis Sampah', 'Kategori Sampah', 'Kondisi Sampah', 'Satuan Sampah'], $this->dataMasterNavigationLabels($panel));
        self::assertFalse($viewer->fresh()->can('create', WasteCategory::class));

        $this->actingAs($manager->fresh());
        self::assertSame(['Jenis Sampah', 'Kategori Sampah', 'Kondisi Sampah', 'Satuan Sampah'], $this->dataMasterNavigationLabels($panel));
        self::assertTrue($manager->fresh()->can('create', WasteCategory::class));
    }

    public function test_price_navigation_requires_price_view_and_creation_requires_price_manage(): void
    {
        $viewer = User::factory()->create();
        $manager = User::factory()->create();
        $this->grant($viewer, 'price-viewer', 'backoffice.access', 'price.view');
        $this->grant($manager, 'price-manager', 'backoffice.access', 'price.manage');

        $panel = Filament::getPanel('backoffice');

        $this->actingAs($viewer->fresh());
        self::assertContains('Harga Sampah', $this->dataMasterNavigationLabels($panel));
        self::assertFalse($viewer->fresh()->can('create', WastePrice::class));

        $this->actingAs($manager->fresh());
        self::assertContains('Harga Sampah', $this->dataMasterNavigationLabels($panel));
        self::assertTrue($manager->fresh()->can('create', WastePrice::class));
    }

    /** @return list<string> */
    private function dataMasterNavigationLabels(Panel $panel): array
    {
        app()->forgetInstance(NavigationManager::class);

        foreach ($panel->getNavigation() as $group) {
            if ($group->getLabel() !== 'Data Master') {
                continue;
            }

            $labels = [];

            foreach ($group->getItems() as $item) {
                $labels[] = $item->getLabel();
            }

            sort($labels);

            return $labels;
        }

        return [];
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
}
