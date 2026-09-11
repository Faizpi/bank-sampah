<?php

declare(strict_types=1);

namespace Tests\Feature\WasteMaster;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Models\User;
use App\Policies\WasteCategoryPolicy;
use App\Policies\WasteConditionPolicy;
use App\Policies\WasteTypePolicy;
use App\Policies\WasteUnitPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

final class WasteMasterManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(WasteCategory::class, WasteCategoryPolicy::class);
        Gate::policy(WasteUnit::class, WasteUnitPolicy::class);
        Gate::policy(WasteCondition::class, WasteConditionPolicy::class);
        Gate::policy(WasteType::class, WasteTypePolicy::class);
    }

    public function test_viewers_may_view_but_only_managers_may_mutate(): void
    {
        $viewer = User::factory()->create();
        $manager = User::factory()->create();
        $category = WasteCategory::factory()->create(['code' => 'CAT-01', 'name' => 'Plastic']);
        $this->grant($viewer, 'waste.view');
        $this->grant($manager, 'waste.manage');

        self::assertTrue($viewer->can('viewAny', WasteCategory::class));
        self::assertTrue($viewer->can('view', $category));
        self::assertFalse($viewer->can('create', WasteCategory::class));
        self::assertFalse($viewer->can('update', $category));
        self::assertFalse($viewer->can('deactivate', $category));
        self::assertTrue($manager->can('viewAny', WasteCategory::class));
        self::assertTrue($manager->can('create', WasteCategory::class));
        self::assertTrue($manager->can('update', $category));
        self::assertTrue($manager->can('deactivate', $category));

        $this->expectException(AuthorizationException::class);
        app(ManageWasteMaster::class)->createCategory($viewer, 'CAT-02', 'Paper');
    }

    public function test_service_creates_and_updates_every_master_record(): void
    {
        $manager = User::factory()->create();
        $this->grant($manager, 'waste.manage');
        $service = app(ManageWasteMaster::class);

        $category = $service->createCategory($manager, 'CAT-01', 'Plastic', 1);
        $unit = $service->createUnit($manager, 'KG', 'Kilogram', 'kg', WasteUnit::CLASSIFICATION_WEIGHT, '1.000000');
        $condition = $service->createCondition($manager, 'COND-01', 'Clean', 'No contamination', 2);
        $type = $service->createType($manager, $category, $unit, 'TYPE-01', 'PET', 'Rinse first', 3, true, true, [$condition->id]);

        $service->updateCategory($manager, $category, 'CAT-02', 'Plastic Updated', 4);
        $service->updateUnit($manager, $unit, 'G', 'Gram', 'g', WasteUnit::CLASSIFICATION_WEIGHT, '0.001000');
        $service->updateCondition($manager, $condition, 'COND-02', 'Very Clean', null, 5);
        $service->updateType($manager, $type, $category, $unit, 'TYPE-02', 'PET Updated', null, 6, false, true, [$condition->id]);

        self::assertDatabaseHas('waste_categories', ['id' => $category->id, 'code' => 'CAT-02', 'sort_order' => 4]);
        self::assertDatabaseHas('waste_units', ['id' => $unit->id, 'code' => 'G', 'conversion_factor_to_kg' => '0.001000']);
        self::assertTrue($unit->fresh()->is_active);
        self::assertDatabaseHas('waste_conditions', ['id' => $condition->id, 'code' => 'COND-02', 'sort_order' => 5]);
        self::assertDatabaseHas('waste_types', ['id' => $type->id, 'code' => 'TYPE-02', 'is_active' => true]);
        self::assertDatabaseHas('waste_type_conditions', ['waste_type_id' => $type->id, 'waste_condition_id' => $condition->id]);
    }

    public function test_active_types_require_active_category_and_unique_active_conditions(): void
    {
        $manager = User::factory()->create();
        $this->grant($manager, 'waste.manage');
        $service = app(ManageWasteMaster::class);
        $inactiveCategory = WasteCategory::factory()->inactive()->create();
        $category = WasteCategory::factory()->create();
        $unit = WasteUnit::factory()->weight()->create();
        $activeCondition = WasteCondition::factory()->create();
        $inactiveCondition = WasteCondition::factory()->inactive()->create();

        $this->assertValidationError('waste_category_id', fn (): WasteType => $service->createType($manager, $inactiveCategory, $unit, 'TYPE-01', 'Type', null, 0, false, true, [$activeCondition->id]));
        $this->assertValidationError('condition_ids', fn (): WasteType => $service->createType($manager, $category, $unit, 'TYPE-02', 'Type', null, 0, false, true, []));
        $this->assertValidationError('condition_ids', fn (): WasteType => $service->createType($manager, $category, $unit, 'TYPE-03', 'Type', null, 0, false, true, [$activeCondition->id, $activeCondition->id]));
        $this->assertValidationError('condition_ids', fn (): WasteType => $service->createType($manager, $category, $unit, 'TYPE-04', 'Type', null, 0, false, true, [999_999]));
        $this->assertValidationError('condition_ids', fn (): WasteType => $service->createType($manager, $category, $unit, 'TYPE-05', 'Type', null, 0, false, true, [$inactiveCondition->id]));

        $inactiveType = $service->createType($manager, $category, $unit, 'TYPE-06', 'Inactive type', null, 0, false, false, []);
        self::assertFalse($inactiveType->is_active);
    }

    public function test_deactivation_preserves_rows_and_condition_pivots(): void
    {
        $manager = User::factory()->create();
        $this->grant($manager, 'waste.manage');
        $category = WasteCategory::factory()->create();
        $unit = WasteUnit::factory()->weight()->create();
        $condition = WasteCondition::factory()->create();
        $type = app(ManageWasteMaster::class)->createType($manager, $category, $unit, 'TYPE-01', 'PET', null, 0, false, true, [$condition->id]);

        app(ManageWasteMaster::class)->deactivate($manager, $type);
        app(ManageWasteMaster::class)->deactivate($manager, $unit);
        app(ManageWasteMaster::class)->activate($manager, $unit);

        self::assertFalse($type->fresh()->is_active);
        self::assertTrue($unit->fresh()->is_active);
        self::assertDatabaseHas('waste_types', ['id' => $type->id, 'is_active' => false]);
        self::assertDatabaseHas('waste_type_conditions', ['waste_type_id' => $type->id, 'waste_condition_id' => $condition->id]);

        foreach ([$category, $unit, $condition, $type] as $record) {
            try {
                $record->delete();
                self::fail('Waste master records must never be physically deleted.');
            } catch (LogicException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_model_query_and_pivot_mutation_bypasses_are_denied(): void
    {
        $category = WasteCategory::factory()->create(['code' => 'CAT-01', 'name' => 'Plastic']);
        $unit = WasteUnit::factory()->weight()->create();
        $condition = WasteCondition::factory()->create(['code' => 'COND-01', 'name' => 'Clean']);
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create(['code' => 'TYPE-01', 'name' => 'PET']);

        foreach ([
            fn (): WasteCategory => WasteCategory::query()->create(['code' => 'CAT-02', 'name' => 'Paper']),
            fn (): WasteUnit => WasteUnit::query()->create(['code' => 'UNIT-02', 'name' => 'Piece', 'symbol' => 'pc', 'classification' => WasteUnit::CLASSIFICATION_NON_WEIGHT]),
            fn (): WasteCondition => WasteCondition::query()->create(['code' => 'COND-02', 'name' => 'Dry']),
            fn (): WasteType => WasteType::query()->create(['waste_category_id' => $category->id, 'waste_unit_id' => $unit->id, 'code' => 'TYPE-02', 'name' => 'HDPE']),
            fn (): bool => $category->update(['name' => 'Bypass']),
            fn (): int => WasteCategory::query()->whereKey($category)->update(['name' => 'Bypass']),
            fn (): int => WasteCategory::query()->whereKey($category)->delete(),
            fn () => $type->conditions()->attach($condition),
            fn (): array => $type->conditions()->sync([$condition->id]),
            fn (): int => $type->conditions()->detach($condition),
            fn (): int => $type->conditions()->updateExistingPivot($condition->id, ['waste_condition_id' => $condition->id]),
        ] as $bypass) {
            try {
                $bypass();
                self::fail('Waste master mutation bypass must be denied.');
            } catch (LogicException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_policies_are_explicitly_registered(): void
    {
        self::assertSame(WasteCategoryPolicy::class, Gate::getPolicyFor(WasteCategory::class)::class);
        self::assertSame(WasteUnitPolicy::class, Gate::getPolicyFor(WasteUnit::class)::class);
        self::assertSame(WasteConditionPolicy::class, Gate::getPolicyFor(WasteCondition::class)::class);
        self::assertSame(WasteTypePolicy::class, Gate::getPolicyFor(WasteType::class)::class);
    }

    private function assertValidationError(string $field, callable $callback): void
    {
        try {
            $callback();
            self::fail("Expected a validation error for {$field}.");
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($field, $exception->errors());
        }
    }

    private function grant(User $user, string $permissionName): void
    {
        $role = Role::query()->create(['name' => "{$permissionName}-role", 'description' => 'Waste role']);
        $permission = Permission::query()->create(['name' => $permissionName, 'description' => 'Waste permission']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
    }
}
