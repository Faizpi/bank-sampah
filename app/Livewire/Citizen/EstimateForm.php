<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Programs\Services\EstimateService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class EstimateForm extends Component
{
    public int $wasteTypeId = 0;

    public int $conditionId = 0;

    public string $weightKg = '';

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public function calculate(EstimateService $estimates): void
    {
        $this->validate(['wasteTypeId' => ['required', 'integer', 'min:1'], 'conditionId' => ['required', 'integer', 'min:1'], 'weightKg' => ['required', 'regex:/^(?:0\.[0-9]{1,3}|[1-9][0-9]*(?:\.[0-9]{1,3})?)$/']]);
        $this->result = $estimates->calculate($this->wasteTypeId, $this->conditionId, $this->weightKg);
    }

    public function render(): View
    {
        return view('livewire.citizen.estimate-form', ['types' => WasteType::query()->with('category')->where('is_active', true)->orderBy('name')->get(['id', 'waste_category_id', 'name']), 'conditions' => WasteCondition::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }
}
