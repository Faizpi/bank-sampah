<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Authorization\PermissionChecker;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.citizen')]
final class GroceryHistory extends Component
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
        abort_unless($actor instanceof User && $permissions->allows($actor, 'grocery.view'), 403);

        $this->statuses = array_map(
            static fn (GroceryStatus $status): string => $status->value,
            GroceryStatus::cases(),
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
        $redemptions = GroceryRedemption::query()
            ->where('customer_id', $actor->id)
            ->when($filters['requestNumber'], static fn (Builder $query, string $number) => $query->where('request_number', 'like', "%{$number}%"))
            ->when($filters['status'], static fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['dateFrom'], static fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['dateUntil'], static fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(10);

        return view('livewire.citizen.grocery-history', compact('redemptions'));
    }
}
