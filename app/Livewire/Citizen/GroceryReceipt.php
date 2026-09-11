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
final class GroceryReceipt extends Component
{
    public GroceryRedemption $redemption;

    public function mount(GroceryRedemption $redemption, GroceryService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        abort_unless($redemption->customer_id === $actor->id && $service->canView($actor, $redemption), 404);
        abort_unless($redemption->status->value === 'selesai', 404);
        $this->redemption = $redemption->load(['package', 'proofMedia', 'receiptLedgerEntry', 'handoverActor']);
    }

    public function render(): View
    {
        return view('livewire.citizen.grocery-receipt');
    }
}
