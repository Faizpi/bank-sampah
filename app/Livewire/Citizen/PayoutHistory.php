<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutChannels;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.citizen')]
final class PayoutHistory extends Component
{
    use WithPagination;

    public function render(): View
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $actor->hasRole('warga'), 403);

        return view('livewire.citizen.payout-history', ['payouts' => app(PayoutAccess::class)->visibleFor($actor)->latest('id')->paginate(10), 'channels' => app(PayoutChannels::class)->all()]);
    }
}
