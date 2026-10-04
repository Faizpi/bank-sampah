<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Authorization\PermissionChecker;
use App\Domain\Corrections\Models\TransactionCorrection;
use App\Domain\Deposits\Models\Deposit;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.citizen')]
final class DepositHistory extends Component
{
    use WithPagination;

    public string $transactionNumber = '';

    public string $status = '';

    public string $dateFrom = '';

    public string $dateUntil = '';

    /** @var list<string> */
    public array $statuses = [
        Deposit::STATUS_DRAFT,
        Deposit::STATUS_PENDING_REVIEW,
        Deposit::STATUS_FINAL,
        Deposit::STATUS_REJECTED,
        Deposit::STATUS_CORRECTED,
        Deposit::STATUS_REVERSED,
    ];

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'deposit.view'), 403);
    }

    /** @return array<string, list<string|Rule>> */
    protected function rules(): array
    {
        return [
            'transactionNumber' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in($this->statuses)],
            'dateFrom' => ['nullable', 'date'],
            'dateUntil' => ['nullable', 'date', 'after_or_equal:dateFrom'],
        ];
    }

    public function updated(string $property): void
    {
        $this->validateOnly($property);
        $this->resetPage();
    }

    public function render(): View
    {
        $filters = $this->validate();

        /** @var User $actor */
        $actor = auth()->user();
        $deposits = Deposit::query()
            ->with(['items', 'correction'])
            ->where('customer_id', $actor->id)
            ->when($filters['transactionNumber'], static fn (Builder $query, string $number) => $query->where('deposit_number', 'like', "%{$number}%"))
            ->when($filters['status'], static fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['dateFrom'], static fn (Builder $query, string $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['dateUntil'], static fn (Builder $query, string $date) => $query->whereDate('occurred_at', '<=', $date))
            ->latest('occurred_at')
            ->paginate(10);
        $corrections = TransactionCorrection::query()->whereHas('deposit', static fn ($query) => $query->where('customer_id', $actor->id))->latest('finalized_at')->paginate(10, ['*'], 'corrections');

        return view('livewire.citizen.deposit-history', compact('deposits', 'corrections'));
    }
}
