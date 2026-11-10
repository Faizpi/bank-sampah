<?php

declare(strict_types=1);

namespace App\Livewire\Treasurer;

use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutChannels;
use App\Domain\Payouts\Services\PayoutReport;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.officer')]
final class PayoutMonitor extends Component
{
    use WithPagination;

    public string $start = '';

    public string $end = '';

    public string $search = '';

    public function mount(): void
    {
        $this->start = today()->subDays(29)->toDateString();
        $this->end = today()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(PayoutReport $reports): View
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        try {
            $query = $reports->query($actor, ['start' => $this->start, 'end' => $this->end, 'search' => $this->search]);
            $this->resetValidation();
        } catch (ValidationException $error) {
            foreach ($error->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $query = SandboxPayout::query()->whereKey([]);
        }

        return view('livewire.treasurer.payout-monitor', [
            'payouts' => $query->latest('id')->paginate(10), 'channels' => app(PayoutChannels::class)->all(),
            'exportUrl' => route('payout.report', ['start' => $this->start, 'end' => $this->end, 'search' => $this->search]),
        ]);
    }
}
