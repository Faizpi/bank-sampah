<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.officer')]
final class PickupTask extends Component
{
    public PickupRequest $pickup;

    /** @var list<array{waste_type_id: string, condition_id: string, weight_kg: string}> */
    public array $actualItems = [];

    public string $idempotencyKey = '';

    public function mount(PickupRequest $pickup, PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        abort_unless($service->canView($actor, $pickup) && $pickup->assigned_staff_id === $actor->id, 404);
        $this->pickup = $pickup->load(['customer', 'items.wasteType', 'media', 'statusHistory.actor']);
        $this->idempotencyKey = (string) str()->uuid();
        $this->actualItems = [['waste_type_id' => '', 'condition_id' => '', 'weight_kg' => '']];
    }

    public function begin(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->pickup = $service->begin($actor, $this->pickup);
    }

    public function markPickedUp(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->pickup = $service->markPickedUp($actor, $this->pickup);
    }

    public function complete(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->pickup = $service->complete($actor, $this->pickup, array_map(static fn (array $item): DepositItemInput => DepositItemInput::fromArray($item), $this->actualItems), $this->idempotencyKey);
        session()->flash('success', 'Penjemputan selesai dan setoran aktual telah dibuat.');
    }

    public function render(): View
    {
        return view('livewire.officer.pickup-task', [
            'types' => WasteType::query()->where('is_active', true)->orderBy('name')->get(),
            'conditions' => WasteCondition::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'canComplete' => $this->pickup->status === PickupStatus::PickedUp,
        ]);
    }
}
