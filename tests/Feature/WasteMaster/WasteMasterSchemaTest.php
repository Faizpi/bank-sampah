<?php

declare(strict_types=1);

namespace Tests\Feature\WasteMaster;

use App\Domain\Platform\Models\Media;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

final class WasteMasterSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_contains_master_tables_and_price_period_contract(): void
    {
        self::assertTrue(Schema::hasColumns('waste_categories', ['id', 'code', 'name', 'sort_order', 'is_active']));
        self::assertTrue(Schema::hasColumns('waste_units', ['id', 'code', 'name', 'symbol', 'classification', 'conversion_factor_to_kg', 'is_active']));
        self::assertTrue(Schema::hasColumns('waste_conditions', ['id', 'code', 'name', 'description', 'sort_order', 'is_active']));
        self::assertTrue(Schema::hasColumns('waste_types', ['id', 'waste_category_id', 'waste_unit_id', 'code', 'name', 'education_description', 'sort_order', 'is_plastic', 'media_id', 'is_active']));
        self::assertTrue(Schema::hasColumns('waste_type_conditions', ['waste_type_id', 'waste_condition_id']));
        self::assertTrue(Schema::hasColumns('waste_prices', ['id', 'waste_type_id', 'waste_condition_id', 'price', 'effective_from', 'effective_to', 'created_by', 'rounding_version']));
    }

    public function test_all_master_codes_and_type_condition_pairs_reject_duplicates(): void
    {
        $category = WasteCategory::factory()->create(['code' => 'CATEGORY']);
        $unit = WasteUnit::factory()->weight()->create(['code' => 'UNIT']);
        $condition = WasteCondition::factory()->create(['code' => 'CONDITION']);
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create(['code' => 'TYPE']);
        WasteMasterMutationGuard::run(fn () => $type->conditions()->attach($condition));

        foreach ([
            fn (): WasteCategory => WasteCategory::factory()->create(['code' => 'CATEGORY']),
            fn (): WasteUnit => WasteUnit::factory()->create(['code' => 'UNIT']),
            fn (): WasteCondition => WasteCondition::factory()->create(['code' => 'CONDITION']),
            fn (): WasteType => WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create(['code' => 'TYPE']),
            function () use ($type, $condition): void {
                WasteMasterMutationGuard::run(fn () => $type->conditions()->attach($condition));
            },
        ] as $duplicate) {
            try {
                $duplicate();
                self::fail('The master unique constraint must reject duplicate values.');
            } catch (QueryException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_category_unit_type_and_condition_relationships_are_typed_and_history_safe(): void
    {
        $category = WasteCategory::factory()->create();
        $unit = WasteUnit::factory()->weight()->create();
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create();
        $condition = WasteCondition::factory()->create();
        WasteMasterMutationGuard::run(fn () => $type->conditions()->attach($condition));

        self::assertTrue($type->category->is($category));
        self::assertTrue($type->unit->is($unit));
        self::assertTrue($category->wasteTypes->contains($type));
        self::assertTrue($unit->wasteTypes->contains($type));
        self::assertTrue($type->conditions->contains($condition));
        self::assertTrue($condition->wasteTypes->contains($type));

        WasteMasterMutationGuard::run(fn (): bool => $type->update(['is_active' => false]));
        self::assertFalse($type->refresh()->is_active);

        $this->expectException(LogicException::class);
        $type->delete();
    }

    public function test_waste_type_parent_foreign_keys_restrict_deletion_and_media_is_nulled_on_delete(): void
    {
        $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('waste_types')"))->keyBy('from');
        self::assertSame('RESTRICT', $foreignKeys->get('waste_category_id')->on_delete);
        self::assertSame('RESTRICT', $foreignKeys->get('waste_unit_id')->on_delete);
        self::assertSame('SET NULL', $foreignKeys->get('media_id')->on_delete);

        $category = WasteCategory::factory()->create();
        $unit = WasteUnit::factory()->weight()->create();
        $media = Media::query()->create([
            'uuid' => (string) str()->uuid(),
            'disk' => 'test',
            'path' => 'waste/test-file',
            'original_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1,
            'checksum' => 'test-checksum',
        ]);
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create(['media_id' => $media->id]);

        $media->delete();
        self::assertNull($type->refresh()->media_id);
    }

    public function test_kg_is_canonical_and_physical_weight_units_convert_with_an_explicit_exact_factor(): void
    {
        $kilogram = WasteUnit::factory()->weight()->create(['code' => 'KG', 'symbol' => 'kg']);
        $physicalWeight = WasteUnit::factory()->weight('0.500000')->create();

        self::assertTrue($kilogram->isCanonicalKilogram());
        self::assertSame('1.25', $kilogram->toCanonicalKilograms('1.250'));
        self::assertSame('1.25', $physicalWeight->toCanonicalKilograms('2.5'));
    }

    public function test_non_weight_null_and_non_representable_factors_are_rejected_without_float_conversion(): void
    {
        $piece = WasteUnit::factory()->nonWeight()->create();
        $weightWithoutFactor = WasteUnit::factory()->weight()->create();
        $fractionalGramFactor = WasteUnit::factory()->weight('0.000001')->create();

        foreach ([$piece, $weightWithoutFactor, $fractionalGramFactor] as $unit) {
            try {
                $unit->toCanonicalKilograms('1');
                self::fail('Only physical weight units with an exact canonical result may convert.');
            } catch (LogicException $exception) {
                self::assertContains(
                    $exception->getMessage(),
                    [
                        'Only physical weight units with an explicit conversion factor can convert to kilograms.',
                        'The conversion result cannot be represented exactly to three decimal kilogram places.',
                    ],
                );
            }
        }
    }

    public function test_conversion_factor_is_limited_to_positive_weight_units(): void
    {
        foreach ([
            fn (): WasteUnit => WasteUnit::factory()->nonWeight()->create(['conversion_factor_to_kg' => '1.000000']),
            fn (): WasteUnit => WasteUnit::factory()->weight('0.000000')->create(),
        ] as $invalidUnit) {
            try {
                $invalidUnit();
                self::fail('Conversion factors must be positive and only assigned to physical weight units.');
            } catch (ValidationException $exception) {
                self::assertSame(
                    ['A conversion factor must be positive and is allowed only for physical weight units.'],
                    $exception->errors()['conversion_factor_to_kg'],
                );
            }
        }
    }

    public function test_type_condition_minimum_is_enforced_by_the_imp_040_attach_lifecycle_not_initial_schema_creation(): void
    {
        $type = WasteType::factory()->create();

        self::assertSame(0, $type->conditions()->count());
    }
}
