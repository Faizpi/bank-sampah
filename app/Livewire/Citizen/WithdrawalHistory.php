<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Authorization\PermissionChecker;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.citizen')]
final class WithdrawalHistory extends Component
{
    use WithPagination;

    public string $requestNumber = '';

    public string $status = '';

    public string $dateFrom = '';

    public string $dateUntil = '';

    /** @var list<string> */
    public array $statuses = [];

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'withdrawal.view'), 403);

        $this->statuses = array_map(
            static fn (WithdrawalStatus $status): string => $status->value,
            WithdrawalStatus::cases(),
        );
    }

    /** @return array<string, list<string|In>> */
    protected function rules(): array
    {
        return [
            'requestNumber' => ['nullable', 'string', 'max:100'],
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
        $withdrawals = WithdrawalRequest::query()
            ->where('customer_id', $actor->id)
            ->when($filters['requestNumber'], static fn (Builder $query, string $number) => $query->where('request_number', 'like', "%{$number}%"))
            ->when($filters['status'], static fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['dateFrom'], static fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['dateUntil'], static fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(10);

        return view('livewire.citizen.withdrawal-history', compact('withdrawals'));
    }
}
