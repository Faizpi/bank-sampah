<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutChannels;
use App\Domain\Payouts\Services\PayoutService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class PayoutShow extends Component
{
    #[Locked]
    public int $payoutId;

    public function mount(SandboxPayout $payout): void
    {
        $this->payoutId = $payout->id;
        $this->record();
    }

    public function cancel(PayoutService $service): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $service->cancel($actor, $this->record());
        session()->flash('payout_success', 'Pengajuan dibatalkan. Saldo riset yang disisihkan sudah dilepas.');
    }

    public function render(): View
    {
        $row = $this->record();

        return view('livewire.citizen.payout-show', ['payout' => $row, 'channel' => app(PayoutChannels::class)->all()[$row->channel]]);
    }

    private function record(): SandboxPayout
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $actor->hasRole('warga'), 403);

        return app(PayoutAccess::class)->visibleFor($actor)->with(['balanceHold', 'ledgerEntry'])->findOrFail($this->payoutId);
    }
}
