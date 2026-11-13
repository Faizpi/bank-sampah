<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Operations\Queries\OfficerWorkHistory;
use App\Domain\Pickups\Models\PickupRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.officer')]
final class WorkHistory extends Component
{
    use WithPagination;

    #[Locked]
    public string $type = 'deposit';

    #[Locked]
    public ?int $recordId = null;

    public string $search = '';

    public function mount(OfficerWorkHistory $history): void
    {
        $type = request()->query('type', 'deposit');
        abort_unless(is_string($type) && in_array($type, ['deposit', 'pickup', 'grocery'], true), 404);
        $this->type = $type;
        $recordNumber = request()->query('recordNumber');
        $legacyRecordId = request()->query('recordId');
        if (is_string($recordNumber) && $recordNumber !== '') {
            $numberColumn = $this->type === 'deposit' ? 'deposit_number' : 'request_number';
            $record = $this->query($history)->where($numberColumn, $recordNumber)->first();
            abort_unless($record !== null, 404);
            $this->recordId = (int) $record->getKey();
        } elseif ($legacyRecordId !== null) {
            $id = filter_var($legacyRecordId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            abort_unless(is_int($id), 404);
            $this->recordId = $id;
            abort_unless($this->query($history)->whereKey($id)->exists(), 404);
        }
    }

    public function updatedSearch(): void
    {
        $this->validateOnly('search', ['search' => ['nullable', 'string', 'max:100']]);
        $this->resetPage('workHistory');
    }

    public function render(OfficerWorkHistory $history): View
    {
        $query = $this->query($history);
        $numberColumn = $this->type === 'deposit' ? 'deposit_number' : 'request_number';
        $timeColumn = match ($this->type) {
            'deposit' => 'occurred_at',
            'grocery' => 'handed_over_at',
            default => 'updated_at',
        };
        $search = mb_substr(trim($this->search), 0, 100);
        $query->when($this->recordId !== null, fn (Builder $q): Builder => $q->whereKey($this->recordId))
            ->when($search !== '', fn (Builder $q): Builder => $q->where(fn (Builder $filter): Builder => $filter
                ->where($numberColumn, 'like', '%'.$search.'%')
                ->orWhereHas('customer', fn (Builder $customer): Builder => $customer->where('name', 'like', '%'.$search.'%'))));
        $records = $query->orderByDesc($timeColumn)->orderByDesc('id')->paginate(10, ['*'], 'workHistory');

        return view('livewire.officer.work-history', compact('records'));
    }

    /** @return Builder<Deposit>|Builder<PickupRequest>|Builder<GroceryRedemption> */
    private function query(OfficerWorkHistory $history): Builder
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return match ($this->type) {
            'deposit' => $history->deposits($actor),
            'pickup' => $history->pickups($actor),
            'grocery' => $history->groceries($actor),
            default => abort(404),
        };
    }
}
