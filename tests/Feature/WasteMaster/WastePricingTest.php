<?php

declare(strict_types=1);

namespace Tests\Feature\WasteMaster;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Shared\InvalidValue;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Actions\ManageWastePricing;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Services\ResolveWastePrice;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WastePricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_audited_price_period_and_viewer_cannot_mutate(): void
    {
        [$type, $condition] = $this->activePriceContext();
        $manager = User::factory()->create();
        $viewer = User::factory()->create();
        $this->grant($manager, 'price.manage');
        $this->grant($viewer, 'price.view');

        $price = app(ManageWastePricing::class)->createPeriod(
            $manager,
            $type,
            $condition,
            3_000,
            CarbonImmutable::parse('2026-08-01 00:00:00', 'Asia/Jakarta'),
            null,
            (string) str()->uuid(),
        );

        self::assertSame(3_000, $price->price);
        $this->assertDatabaseHas('waste_prices', ['id' => $price->id, 'price' => 3_000]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'waste.price.created', 'auditable_id' => $price->id]);

        $this->expectException(AuthorizationException::class);
        app(ManageWastePricing::class)->createPeriod(
            $viewer,
            $type,
            $condition,
            3_500,
            CarbonImmutable::parse('2026-09-01 00:00:00', 'Asia/Jakarta'),
            null,
            (string) str()->uuid(),
        );
    }

    public function test_zero_price_requires_explicit_policy_confirmation_and_inactive_context_is_rejected(): void
    {
        [$type, $condition] = $this->activePriceContext();
        $manager = User::factory()->create();
        $this->grant($manager, 'price.manage');

        $this->assertValidationError('price', fn (): WastePrice => app(ManageWastePricing::class)->createPeriod(
            $manager,
            $type,
            $condition,
            0,
            CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'),
            null,
            (string) str()->uuid(),
        ));

        $inactiveType = WasteType::factory()->inactive()->create();
        $inactiveCondition = WasteCondition::factory()->inactive()->create();

        $this->assertValidationError('waste_type_id', fn (): WastePrice => app(ManageWastePricing::class)->createPeriod(
            $manager,
            $inactiveType,
            $condition,
            3_000,
            CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'),
            null,
            (string) str()->uuid(),
        ));

        $this->assertValidationError('waste_condition_id', fn (): WastePrice => app(ManageWastePricing::class)->createPeriod(
            $manager,
            $type,
            $inactiveCondition,
            3_000,
            CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'),
            null,
            (string) str()->uuid(),
        ));

        $confirmed = app(ManageWastePricing::class)->createPeriod(
            $manager,
            $type,
            $condition,
            0,
            CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'),
            null,
            (string) str()->uuid(),
            true,
        );

        self::assertSame(0, $confirmed->price);
    }

    public function test_adjacent_period_closes_open_history_but_real_overlap_is_rejected_without_changes(): void
    {
        [$type, $condition] = $this->activePriceContext();
        $manager = User::factory()->create();
        $this->grant($manager, 'price.manage');
        $pricing = app(ManageWastePricing::class);

        $first = $pricing->createPeriod($manager, $type, $condition, 3_000, CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'), null, (string) str()->uuid());
        $second = $pricing->createPeriod($manager, $type, $condition, 3_500, CarbonImmutable::parse('2026-08-10', 'Asia/Jakarta'), null, (string) str()->uuid());

        self::assertSame('2026-08-10 00:00:00', $first->fresh()->effectiveToDate()?->format('Y-m-d H:i:s'));
        self::assertNull($second->effective_to);

        $this->assertValidationError('effective_from', fn (): WastePrice => $pricing->createPeriod(
            $manager,
            $type,
            $condition,
            3_250,
            CarbonImmutable::parse('2026-08-05', 'Asia/Jakarta'),
            CarbonImmutable::parse('2026-08-06', 'Asia/Jakarta'),
            (string) str()->uuid(),
        ));

        self::assertSame(2, WastePrice::query()->count());
        self::assertSame(3_000, $first->fresh()->price);
        self::assertSame(3_500, $second->fresh()->price);
    }

    public function test_resolver_selects_the_single_price_active_at_transaction_time_and_fails_closed(): void
    {
        [$type, $condition] = $this->activePriceContext();
        $manager = User::factory()->create();
        $this->grant($manager, 'price.manage');
        $pricing = app(ManageWastePricing::class);
        $pricing->createPeriod($manager, $type, $condition, 3_000, CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'), null, (string) str()->uuid());
        $pricing->createPeriod($manager, $type, $condition, 3_500, CarbonImmutable::parse('2026-08-10', 'Asia/Jakarta'), null, (string) str()->uuid());

        self::assertSame(3_000, app(ResolveWastePrice::class)->resolve($type, $condition->id, CarbonImmutable::parse('2026-08-09 23:59:59', 'Asia/Jakarta'))->price);
        self::assertSame(3_500, app(ResolveWastePrice::class)->resolve($type, $condition->id, CarbonImmutable::parse('2026-08-10', 'Asia/Jakarta'))->price);

        $this->assertValidationError('price', fn (): WastePrice => app(ResolveWastePrice::class)->resolve($type, $condition->id, CarbonImmutable::parse('2026-07-31', 'Asia/Jakarta')));
    }

    public function test_snapshot_is_independent_and_uses_canonical_weight_precision_and_half_up(): void
    {
        [$type, $condition] = $this->activePriceContext();
        $manager = User::factory()->create();
        $this->grant($manager, 'price.manage');
        $this->grant($manager, 'waste.manage');
        $price = app(ManageWastePricing::class)->createPeriod($manager, $type, $condition, 3_333, CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'), null, (string) str()->uuid());

        $snapshot = $price->snapshot()->withWeight('1.250');
        self::assertSame(4_166, $snapshot->subtotal);
        self::assertSame('1.25', $snapshot->weightKg);
        self::assertSame('half_up_v1', $snapshot->roundingVersion);

        app(ManageWastePricing::class)->createPeriod($manager, $type, $condition, 4_000, CarbonImmutable::parse('2026-08-10', 'Asia/Jakarta'), null, (string) str()->uuid());
        self::assertSame(3_333, $snapshot->pricePerUnit);
        self::assertSame(4_166, $snapshot->subtotal);

        app(ManageWasteMaster::class)->updateType($manager, $type, $type->category, $type->unit, $type->code, 'Nama Baru', $type->education_description, $type->sort_order, $type->is_plastic, true, [$condition->id]);
        self::assertSame('Nama Lama', $snapshot->wasteTypeName);

        $this->expectException(InvalidValue::class);
        $snapshot->withWeight('1.2345');
    }

    /** @return array{WasteType, WasteCondition} */
    private function activePriceContext(): array
    {
        $category = WasteCategory::factory()->create(['name' => 'Plastik']);
        $unit = WasteUnit::factory()->weight()->create(['code' => 'KG', 'symbol' => 'kg']);
        $condition = WasteCondition::factory()->create(['name' => 'Bersih']);
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create(['name' => 'Nama Lama']);
        WasteMasterMutationGuard::run(fn (): array => $type->conditions()->sync([$condition->id]));

        return [$type, $condition];
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
        $role = Role::query()->create(['name' => "{$permissionName}-role-{$user->id}", 'description' => 'Price role']);
        $permission = Permission::query()->create(['name' => $permissionName, 'description' => 'Price permission']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
    }
}
