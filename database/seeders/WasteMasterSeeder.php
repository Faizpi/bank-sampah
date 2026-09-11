<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Single source of truth for waste master data (categories, unit, conditions,
 * types, and prices).
 *
 * Both the local demo dataset and the production bootstrap delegate here so the
 * code list can never drift between environments. The canonical type `code`
 * values are also the class labels expected by the waste-image classifier, so
 * the order and spelling of `TYPES` must stay stable.
 */
final class WasteMasterSeeder extends Seeder
{
    public const UNIT_CODE = 'KG';

    /** @var list<array{code: string, name: string}> */
    public const CATEGORIES = [
        ['code' => 'PLASTIK', 'name' => 'Plastik'],
        ['code' => 'KERTAS', 'name' => 'Kertas'],
        ['code' => 'LOGAM', 'name' => 'Logam'],
    ];

    /** @var list<array{code: string, name: string}> */
    public const CONDITIONS = [
        ['code' => 'BERSIH', 'name' => 'Bersih'],
        ['code' => 'CAMPUR', 'name' => 'Campur'],
    ];

    /**
     * The five common, visually distinct waste types used across the app.
     *
     * @var list<array{category: string, code: string, name: string, plastic: bool, price: int}>
     */
    public const TYPES = [
        ['category' => 'PLASTIK', 'code' => 'BOTOL-PLASTIK', 'name' => 'Botol Plastik', 'plastic' => true, 'price' => 3000],
        ['category' => 'PLASTIK', 'code' => 'GELAS-PLASTIK', 'name' => 'Gelas Plastik', 'plastic' => true, 'price' => 4000],
        ['category' => 'KERTAS', 'code' => 'KARDUS', 'name' => 'Kardus', 'plastic' => false, 'price' => 1500],
        ['category' => 'KERTAS', 'code' => 'KERTAS', 'name' => 'Kertas', 'plastic' => false, 'price' => 2000],
        ['category' => 'LOGAM', 'code' => 'LOGAM', 'name' => 'Logam', 'plastic' => false, 'price' => 8000],
    ];

    /** Difference applied to the clean price for the mixed condition. */
    private const MIXED_CONDITION_DISCOUNT = 250;

    private const MINIMUM_PRICE = 500;

    public function run(): void
    {
        self::seed(self::resolveActor());
    }

    /**
     * Idempotently seed the canonical waste master data.
     *
     * @return array{categories: array<string, WasteCategory>, types: list<WasteType>, conditions: array<string, WasteCondition>, prices: array<int, array<int, WastePrice>>}
     */
    public static function seed(User $actor): array
    {
        $manager = app(ManageWasteMaster::class);

        $categories = [];
        foreach (self::CATEGORIES as $sortOrder => $definition) {
            $category = WasteCategory::query()->where('code', $definition['code'])->first();
            $category ??= $manager->createCategory($actor, $definition['code'], $definition['name'], $sortOrder + 1);
            if (! $category->is_active) {
                $manager->activate($actor, $category);
            }
            $categories[$definition['code']] = $category;
        }

        $unit = WasteUnit::query()->where('code', self::UNIT_CODE)->first();
        $unit ??= $manager->createUnit($actor, self::UNIT_CODE, 'Kilogram', 'kg', WasteUnit::CLASSIFICATION_WEIGHT, '1.000000');
        if (! $unit->is_active) {
            $manager->activate($actor, $unit);
        }

        $conditions = [];
        foreach (self::CONDITIONS as $sortOrder => $definition) {
            $condition = WasteCondition::query()->where('code', $definition['code'])->first();
            $condition ??= $manager->createCondition($actor, $definition['code'], $definition['name'], 'Kondisi material untuk pencatatan setoran.', $sortOrder + 1);
            if (! $condition->is_active) {
                $manager->activate($actor, $condition);
            }
            $conditions[$definition['code']] = $condition;
        }

        $conditionIds = array_values(array_map(
            static fn (WasteCondition $condition): int => (int) $condition->id,
            $conditions,
        ));

        $types = [];
        $prices = [];
        foreach (self::TYPES as $sortOrder => $definition) {
            $type = WasteType::query()->where('code', $definition['code'])->first();
            if ($type === null) {
                $type = $manager->createType(
                    $actor,
                    $categories[$definition['category']],
                    $unit,
                    $definition['code'],
                    $definition['name'],
                    'Material terpilah yang diterima Bank Sampah.',
                    $sortOrder + 1,
                    $definition['plastic'],
                    true,
                    $conditionIds,
                );
            }

            $types[] = $type;
            $prices[$type->id] = self::seedPricesForType($actor, $type, $conditions, $definition['price']);
        }

        self::deactivateStaleMasterData($categories, $conditions);

        return [
            'categories' => $categories,
            'types' => $types,
            'conditions' => array_values($conditions),
            'prices' => $prices,
        ];
    }

    /**
     * @param  array<string, WasteCondition>  $conditions
     * @return array<int, WastePrice>
     */
    private static function seedPricesForType(User $actor, WasteType $type, array $conditions, int $basePrice): array
    {
        $prices = [];
        foreach (array_values($conditions) as $conditionIndex => $condition) {
            $value = max(self::MINIMUM_PRICE, $basePrice - ($conditionIndex * self::MIXED_CONDITION_DISCOUNT));
            $price = WastePrice::query()
                ->where('waste_type_id', $type->id)
                ->where('waste_condition_id', $condition->id)
                ->whereNull('effective_to')
                ->first();
            $price ??= WasteMasterMutationGuard::run(fn (): WastePrice => WastePrice::query()->create([
                'waste_type_id' => $type->id,
                'waste_condition_id' => $condition->id,
                'price' => $value,
                'effective_from' => now('Asia/Jakarta')->subDays(30)->startOfDay(),
                'effective_to' => null,
                'created_by' => $actor->id,
                'rounding_version' => 'half_up_v1',
            ]));
            $prices[$condition->id] = $price;
        }

        return $prices;
    }

    /**
     * Soft-deactivate master rows that are no longer canonical. History is
     * preserved: deposits keep their stored price/type snapshots.
     *
     * @param  array<string, WasteCategory>  $categories
     * @param  array<string, WasteCondition>  $conditions
     */
    private static function deactivateStaleMasterData(array $categories, array $conditions): void
    {
        $categoryIds = array_map(static fn (WasteCategory $category): int => (int) $category->id, $categories);
        $conditionIds = array_map(static fn (WasteCondition $condition): int => (int) $condition->id, $conditions);
        $typeCodes = array_map(static fn (array $definition): string => $definition['code'], self::TYPES);

        WasteMasterMutationGuard::run(function () use ($categoryIds, $conditionIds, $typeCodes): void {
            WasteType::query()->whereNotIn('code', $typeCodes)->where('is_active', true)
                ->update(['is_active' => false]);
            WasteCategory::query()->whereNotIn('id', $categoryIds)->where('is_active', true)
                ->update(['is_active' => false]);
            WasteCondition::query()->whereNotIn('id', $conditionIds)->where('is_active', true)
                ->update(['is_active' => false]);
        });
    }

    private static function resolveActor(): User
    {
        $actor = User::query()
            ->whereHas('roles', static fn ($query) => $query->where('name', 'admin'))
            ->orderBy('id')
            ->first();

        $actor ??= User::query()->where('username', 'admin')->first();

        if (! $actor instanceof User) {
            $actor = User::query()
                ->whereHas('roles', static fn ($query) => $query->where('name', 'superadmin'))
                ->orderBy('id')
                ->first();
        }

        if (! $actor instanceof User && Role::query()->where('name', 'admin')->exists()) {
            $actor = User::query()->orderBy('id')->first();
        }

        if (! $actor instanceof User) {
            throw new \RuntimeException('WasteMasterSeeder membutuhkan user admin. Jalankan seeder user terlebih dahulu.');
        }

        return $actor;
    }
}
