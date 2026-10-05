<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class GroceryShow extends Component
{
    public GroceryRedemption $redemption;

    public function mount(GroceryRedemption $redemption, GroceryService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        abort_unless($service->canView($actor, $redemption), 404);
        $this->redemption = $redemption->load(['package', 'balanceHold', 'statusHistory.actor', 'proofMedia', 'receiptLedgerEntry']);
    }

    public function cancel(GroceryService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->redemption = $service->cancel($actor, $this->redemption, 'Warga membatalkan pengajuan sebelum persetujuan.');
        session()->flash('success', 'Pengajuan sembako dibatalkan dan hold dilepas bila ada.');
    }

    public function render(): View
    {
        return view('livewire.citizen.grocery-show');
    }
}
