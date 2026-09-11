<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Domain\WasteMaster\Models\WasteType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class WasteCatalog extends Component
{
    public function render(): View
    {
        /** @var Collection<int, WasteType> $wasteTypes */
        $wasteTypes = WasteType::query()
            ->with([
                'category:id,code,name,is_active',
                'unit:id,code,name,symbol,classification',
                'conditions' => static fn ($query) => $query->select(['waste_conditions.id', 'waste_conditions.code', 'waste_conditions.name'])->where('waste_conditions.is_active', true)->orderBy('waste_conditions.sort_order'),
            ])
            ->where('is_active', true)
            ->whereHas('category', static fn ($query) => $query->where('is_active', true))
            ->whereHas('conditions', static fn ($query) => $query->where('waste_conditions.is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'waste_category_id', 'waste_unit_id', 'code', 'name', 'education_description', 'sort_order', 'is_plastic', 'is_active']);

        return view('livewire.public-site.waste-catalog', compact('wasteTypes'));
    }
}
