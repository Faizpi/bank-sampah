<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class PickupShow extends Component
{
    public PickupRequest $pickup;

    public function mount(PickupRequest $pickup, PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        abort_unless($service->canView($actor, $pickup), 404);
        $this->pickup = $pickup->load(['items.wasteType', 'media', 'statusHistory.actor', 'assignedStaff']);
    }

    public function cancel(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->pickup = $service->cancel($actor, $this->pickup);
        session()->flash('success', 'Pengajuan penjemputan dibatalkan.');
    }

    public function render(): View
    {
        return view('livewire.citizen.pickup-show');
    }
}
