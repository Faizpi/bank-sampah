<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Domain\WasteMaster\Models\WastePrice;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class WastePrices extends Component
{
    public function render(): View
    {
        $now = Carbon::now();

        /** @var Collection<int, WastePrice> $prices */
        $prices = WastePrice::query()
            ->with([
                'wasteType:id,waste_category_id,waste_unit_id,code,name,is_active',
                'wasteType.category:id,name,is_active',
                'wasteType.unit:id,name,symbol',
                'condition:id,code,name,sort_order,is_active',
            ])
            ->where('effective_from', '<=', $now)
            ->where(static function ($query) use ($now): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->whereHas('wasteType', static fn ($query) => $query->where('is_active', true)->whereHas('category', static fn ($categoryQuery) => $categoryQuery->where('is_active', true)))
            ->whereHas('condition', static fn ($query) => $query->where('is_active', true))
            ->orderBy('waste_type_id')
            ->orderBy('waste_condition_id')
            ->get(['id', 'waste_type_id', 'waste_condition_id', 'price', 'effective_from', 'effective_to']);

        return view('livewire.public-site.waste-prices', compact('prices'));
    }
}
