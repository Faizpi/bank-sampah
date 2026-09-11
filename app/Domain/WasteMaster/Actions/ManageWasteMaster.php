<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Actions;

use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteMasterModel;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class ManageWasteMaster
{
    public function createCategory(User $actor, string $code, string $name, int $sortOrder = 0): WasteCategory
    {
        $this->authorize($actor, 'create', WasteCategory::class);

        return $this->create(WasteCategory::class, compact('code', 'name') + ['sort_order' => $sortOrder, 'is_active' => true]);
    }

    public function updateCategory(User $actor, WasteCategory $category, string $code, string $name, int $sortOrder): void
    {
        $this->authorize($actor, 'update', $category);
        $this->persist($category, compact('code', 'name') + ['sort_order' => $sortOrder]);
    }

    public function createUnit(User $actor, string $code, string $name, string $symbol, string $classification, ?string $conversionFactorToKg = null): WasteUnit
    {
        $this->authorize($actor, 'create', WasteUnit::class);

        return $this->create(WasteUnit::class, [
            'code' => $code,
            'name' => $name,
            'symbol' => $symbol,
            'classification' => $classification,
            'conversion_factor_to_kg' => $conversionFactorToKg,
        ]);
    }

    public function updateUnit(User $actor, WasteUnit $unit, string $code, string $name, string $symbol, string $classification, ?string $conversionFactorToKg): void
    {
        $this->authorize($actor, 'update', $unit);
        $this->persist($unit, [
            'code' => $code,
            'name' => $name,
            'symbol' => $symbol,
            'classification' => $classification,
            'conversion_factor_to_kg' => $conversionFactorToKg,
        ]);
    }

    public function createCondition(User $actor, string $code, string $name, ?string $description, int $sortOrder = 0): WasteCondition
    {
        $this->authorize($actor, 'create', WasteCondition::class);

        return $this->create(WasteCondition::class, compact('code', 'name', 'description') + ['sort_order' => $sortOrder, 'is_active' => true]);
    }

    public function updateCondition(User $actor, WasteCondition $condition, string $code, string $name, ?string $description, int $sortOrder): void
    {
        $this->authorize($actor, 'update', $condition);
        $this->persist($condition, compact('code', 'name', 'description') + ['sort_order' => $sortOrder]);
    }

    /** @param list<int> $conditionIds */
    public function createType(User $actor, WasteCategory $category, WasteUnit $unit, string $code, string $name, ?string $educationDescription, int $sortOrder, bool $isPlastic, bool $isActive, array $conditionIds): WasteType
    {
        $this->authorize($actor, 'create', WasteType::class);
        $this->ensureTypeReferencesAreValid($category, $unit, $isActive, $conditionIds);

        $type = $this->create(WasteType::class, [
            'waste_category_id' => $category->id,
            'waste_unit_id' => $unit->id,
            'code' => $code,
            'name' => $name,
            'education_description' => $educationDescription,
            'sort_order' => $sortOrder,
            'is_plastic' => $isPlastic,
            'is_active' => $isActive,
        ]);
        $this->syncConditions($type, $conditionIds);

        return $type;
    }

    /** @param list<int> $conditionIds */
    public function updateType(User $actor, WasteType $type, WasteCategory $category, WasteUnit $unit, string $code, string $name, ?string $educationDescription, int $sortOrder, bool $isPlastic, bool $isActive, array $conditionIds): void
    {
        $this->authorize($actor, 'update', $type);
        $this->ensureTypeReferencesAreValid($category, $unit, $isActive, $conditionIds);
        $this->persist($type, [
            'waste_category_id' => $category->id,
            'waste_unit_id' => $unit->id,
            'code' => $code,
            'name' => $name,
            'education_description' => $educationDescription,
            'sort_order' => $sortOrder,
            'is_plastic' => $isPlastic,
            'is_active' => $isActive,
        ]);
        $this->syncConditions($type, $conditionIds);
    }

    public function deactivate(User $actor, WasteMasterModel $record): void
    {
        $this->authorize($actor, 'deactivate', $record);
        $this->persist($record, ['is_active' => false]);
    }

    public function activate(User $actor, WasteMasterModel $record): void
    {
        $this->authorize($actor, 'activate', $record);

        if ($record instanceof WasteType) {
            $record->loadMissing(['category', 'unit', 'conditions']);
            $this->ensureTypeReferencesAreValid($record->category, $record->unit, true, $record->conditions->modelKeys());
        }

        $this->persist($record, ['is_active' => true]);
    }

    /**
     * @template TModel of WasteMasterModel
     *
     * @param  class-string<TModel>  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    private function create(string $model, array $attributes): WasteMasterModel
    {
        return WasteMasterMutationGuard::run(fn (): WasteMasterModel => $model::query()->create($attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function persist(WasteMasterModel $record, array $attributes): void
    {
        WasteMasterMutationGuard::run(fn (): bool => $record->forceFill($attributes)->save());
    }

    /** @param list<int> $conditionIds */
    private function syncConditions(WasteType $type, array $conditionIds): void
    {
        WasteMasterMutationGuard::run(fn (): array => $type->conditions()->sync($conditionIds));
    }

    /** @param list<int> $conditionIds */
    private function ensureTypeReferencesAreValid(WasteCategory $category, WasteUnit $unit, bool $isActive, array $conditionIds): void
    {
        $category = $category->fresh();
        if ($category === null || ! $category->is_active) {
            throw ValidationException::withMessages(['waste_category_id' => 'The selected waste category must be active.']);
        }

        $unit = $unit->fresh();
        if ($unit === null || ! $unit->is_active) {
            throw ValidationException::withMessages(['waste_unit_id' => 'The selected waste unit must exist and be active.']);
        }

        if (! $isActive) {
            return;
        }

        if ($conditionIds === [] || count($conditionIds) !== count(array_unique($conditionIds))) {
            throw ValidationException::withMessages(['condition_ids' => 'An active waste type requires one or more unique active conditions.']);
        }

        $conditions = WasteCondition::query()->whereKey($conditionIds)->get();
        if ($conditions->count() !== count($conditionIds) || $conditions->contains(static fn (WasteCondition $condition): bool => ! $condition->is_active)) {
            throw ValidationException::withMessages(['condition_ids' => 'Every selected waste condition must exist and be active.']);
        }
    }

    /** @param class-string<WasteMasterModel>|WasteMasterModel $target */
    private function authorize(User $actor, string $ability, string|WasteMasterModel $target): void
    {
        Gate::forUser($actor)->authorize($ability, $target);
    }
}
