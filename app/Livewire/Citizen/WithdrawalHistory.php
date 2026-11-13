<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Authorization\PermissionChecker;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutChannels;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use App\Support\StatusLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.citizen')]
final class WithdrawalHistory extends Component
{
    use WithPagination;

    #[Url(as: 'metode', history: true)]
    public string $method = 'tunai';

    public string $requestNumber = '';

    public string $status = '';

    public string $dateFrom = '';

    public string $dateUntil = '';

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'withdrawal.view'), 403);

        if (! in_array($this->method, ['tunai', 'digital'], true) || ($this->method === 'digital' && ! Schema::hasTable('sandbox_payouts'))) {
            $this->method = 'tunai';
        }
    }

    /** @return array<string, list<string|In>> */
    protected function rules(): array
    {
        return [
            'method' => ['required', Rule::in(Schema::hasTable('sandbox_payouts') ? ['tunai', 'digital'] : ['tunai'])],
            'requestNumber' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(array_keys($this->statusOptions()))],
            'dateFrom' => ['nullable', 'date'],
            'dateUntil' => ['nullable', 'date', 'after_or_equal:dateFrom'],
        ];
    }

    public function updated(string $property): void
    {
        if ($property === 'method') {
            $this->status = '';
        }
        $this->validateOnly($property);
        $this->resetPage();
    }

    public function selectMethod(string $method): void
    {
        abort_unless(in_array($method, ['tunai', 'digital'], true) && ($method !== 'digital' || Schema::hasTable('sandbox_payouts')), 422);
        $this->method = $method;
        $this->updated('method');
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        if ($this->method === 'digital') {
            return ['processing' => 'Sedang diproses', 'uncertain' => 'Sedang diperiksa', 'succeeded' => 'Berhasil',
                'failed' => 'Gagal', 'reversed' => 'Dikembalikan', 'cancelled' => 'Dibatalkan'];
        }

        $options = [];
        foreach (WithdrawalStatus::cases() as $status) {
            $options[$status->value] = StatusLabel::for($status);
        }

        return $options;
    }

    public function render(): View
    {
        $filters = $this->validate();

        $actor = auth()->user();
        abort_unless($actor instanceof User && $actor->hasRole('warga') && app(PermissionChecker::class)->allows($actor, 'withdrawal.view'), 403);
        $query = $this->method === 'digital'
            ? app(PayoutAccess::class)->visibleFor($actor)
            : WithdrawalRequest::query()->where('customer_id', $actor->id);
        if ($filters['status']) {
            $query->whereIn('status', $this->method === 'digital' && $filters['status'] === 'processing'
                ? ['queued', 'dispatching', 'pending'] : [$filters['status']]);
        }
        $records = $query
            ->when($filters['requestNumber'], static fn (Builder $query, string $number) => $query->where('request_number', 'like', "%{$number}%"))
            ->when($filters['dateFrom'], static fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['dateUntil'], static fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(10);

        return view('livewire.citizen.withdrawal-history', [
            'records' => $records, 'statusOptions' => $this->statusOptions(), 'channels' => app(PayoutChannels::class)->all(),
            'digitalAvailable' => Schema::hasTable('sandbox_payouts'),
            'hasFilters' => filled($this->requestNumber) || filled($this->status) || filled($this->dateFrom) || filled($this->dateUntil),
        ]);
    }
}
