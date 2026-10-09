<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Queries;

use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Storage;

/** One visibility, photo, and current-price contract for all three public surfaces. */
final class PublicWasteCatalog
{
    /** @return Builder<WasteType> */
    public function visibleTypes(): Builder
    {
        return WasteType::query()->where('is_active', true)
            ->whereHas('category', fn (Builder $query): Builder => $query->where('is_active', true))
            ->whereHas('unit', fn (Builder $query): Builder => $query->where('is_active', true))
            ->whereHas('conditions', fn (Builder $query): Builder => $query->where('waste_conditions.is_active', true));
    }

    /** @return Collection<int, WasteType> */
    public function types(?int $limit = null): Collection
    {
        return $this->visibleTypes()->with(['category', 'unit', 'publicPhoto',
            'conditions' => fn ($query) => $query->where('waste_conditions.is_active', true)->orderBy('sort_order'),
            'prices' => fn ($query) => $this->currentPrices($query->getQuery())->with('condition'),
        ])->orderBy('sort_order')->orderBy('name')->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit))->get();
    }

    /** @return Collection<int, WastePrice> */
    public function prices(): Collection
    {
        return $this->currentPrices(WastePrice::query())
            ->whereIn('waste_type_id', $this->visibleTypes()->select('id'))
            ->with(['wasteType.category', 'wasteType.unit', 'wasteType.publicPhoto', 'condition'])
            ->orderBy('waste_type_id')->orderBy('waste_condition_id')->get();
    }

    /** @param Builder<WastePrice> $query
     * @return Builder<WastePrice>
     */
    private function currentPrices(Builder $query): Builder
    {
        $now = now();

        return $query->where('effective_from', '<=', $now)
            ->where(fn (Builder $period): Builder => $period->whereNull('effective_to')->orWhere('effective_to', '>', $now))
            ->whereHas('condition', fn (Builder $condition): Builder => $condition->where('is_active', true))
            ->whereExists(fn (QueryBuilder $pair): QueryBuilder => $pair->selectRaw('1')->from('waste_type_conditions')
                ->whereColumn('waste_type_conditions.waste_type_id', 'waste_prices.waste_type_id')
                ->whereColumn('waste_type_conditions.waste_condition_id', 'waste_prices.waste_condition_id'));
    }

    public function photoUrl(WasteType $type): ?string
    {
        $photo = $type->publicPhoto;
        if ($photo === null || ! preg_match('/^[a-f0-9-]{36}\.jpg$/', $photo->path) || ! Storage::disk('waste_photos')->exists($photo->path)) {
            return null;
        }

        return route('public.waste-photo', [$type, $photo->uuid]);
    }
}
