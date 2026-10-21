<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Operations\Queries\OfficerWorkHistory;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

abstract class ServiceTaskQueue extends Component
{
    use InteractsWithMediaPicker;
    use WithFileUploads;
    use WithPagination;

    #[Locked]
    public ?int $selectedId = null;

    #[Locked]
    public string $idempotencyKey = '';

    public ?UploadedFile $proof = null;

    public string $reason = '';

    public string $queueSearch = '';

    public string $queueTab = 'waiting';

    /** @var array{number: string, value: int, time: string, status: string}|null */
    #[Locked]
    public ?array $receipt = null;

    public function clearProof(): void
    {
        $this->clearMediaPickerUpload('proof');
    }

    /** @return list<array{name: string, size: int, mimeType: string, previewUrl: string}> */
    public function confirmProofUpload(): array
    {
        return $this->confirmMediaPickerUpload('proof', ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp']);
    }

    abstract protected function isCash(): bool;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole($this->isCash() ? 'bendahara' : 'petugas'), 403);
        $this->idempotencyKey = (string) str()->uuid();
        if ($this->queue()->where($this->isCash() ? 'payer_id' : 'claimed_by_id', auth()->id())->exists()) {
            $this->queueTab = 'mine';
        }
        if (request()->integer('taskId') > 0) {
            $this->select(request()->integer('taskId'));
        }
    }

    public function select(int $id): void
    {
        if (! $this->isCash() && app(OfficerWorkHistory::class)->groceries($this->actor())->whereKey($id)->exists()) {
            $this->redirectRoute('officer.history', ['type' => 'grocery', 'recordId' => $id]);

            return;
        }
        abort_unless($this->queue()->whereKey($id)->exists(), 404);
        $this->selectedId = $id;
        $this->proof = null;
        $this->reason = '';
        $this->receipt = null;
        $this->idempotencyKey = (string) str()->uuid();
        $this->resetValidation();
    }

    public function claim(): void
    {
        app(ClaimedServiceWorkflow::class)->claim($this->actor(), $this->selected());
        $this->queueTab = 'mine';
        $this->resetPage('serviceTasks');
        session()->flash('success', 'Tugas berhasil diambil. Pastikan penyerahan fisik hanya dilakukan satu kali.');
    }

    public function release(): void
    {
        $this->validateReason();
        app(ClaimedServiceWorkflow::class)->release($this->actor(), $this->selected(), $this->reason);
        $this->selectedId = null;
        $this->resetPage('serviceTasks');
        session()->flash('success', 'Tugas kembali ke antrean; saldo tetap ditahan.');
    }

    public function ready(): void
    {
        $request = $this->selected();
        abort_unless($request instanceof GroceryRedemption, 403);
        app(ClaimedServiceWorkflow::class)->ready($this->actor(), $request);
        session()->flash('success', 'Paket siap diambil.');
    }

    public function unavailable(): void
    {
        $this->validateReason();
        $request = $this->selected();
        abort_unless($request instanceof GroceryRedemption, 403);
        app(ClaimedServiceWorkflow::class)->cancel($this->actor(), $request, $this->reason, true);
        $this->selectedId = null;
        $this->resetPage('serviceTasks');
        session()->flash('success', 'Paket tidak tersedia; saldo yang ditahan sudah dilepas.');
    }

    public function reportException(): void
    {
        $this->validateReason();
        app(ClaimedServiceWorkflow::class)->reportException($this->actor(), $this->selected(), $this->reason);
        session()->flash('success', 'Transaksi dikunci untuk pemeriksaan Admin. Jangan serahkan ulang.');
    }

    public function confirmCancellation(): void
    {
        app(ClaimedServiceWorkflow::class)->cancel($this->actor(), $this->selected(), 'Warga meminta batal dan pelaksana memastikan manfaat belum diserahkan.');
        $this->selectedId = null;
        $this->resetPage('serviceTasks');
        session()->flash('success', 'Pembatalan dikonfirmasi; saldo tertahan sudah dilepas.');
    }

    public function complete(): void
    {
        $this->validate(['proof' => ['nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp']]);
        $request = app(ClaimedServiceWorkflow::class)->complete($this->actor(), $this->selectedForCompletion(), $this->proof, $this->idempotencyKey);
        $recordedAt = $request instanceof WithdrawalRequest ? $request->paid_at : $request->handed_over_at;
        if (! $recordedAt instanceof CarbonInterface) {
            throw ValidationException::withMessages(['workflow' => 'Waktu pencatatan tidak lengkap; minta pemeriksaan Admin.']);
        }
        $this->receipt = ['number' => $request->request_number, 'value' => $this->isCash() ? $request->amount : $request->value_snapshot, 'time' => $recordedAt->translatedFormat('d F Y H:i'), 'status' => $request->status->value];
        $this->selectedId = null;
        $this->proof = null;
        $this->resetPage('serviceTasks');
        session()->flash('success', 'Penyerahan sudah tercatat. Jangan lakukan penyerahan kedua.');
    }

    /** @return Builder<WithdrawalRequest>|Builder<GroceryRedemption> */
    private function queue(): Builder
    {
        return $this->isCash()
            ? app(WithdrawalService::class)->payableFor($this->actor())
            : app(GroceryService::class)->readyForHandover($this->actor());
    }

    /** @return Builder<WithdrawalRequest>|Builder<GroceryRedemption> */
    private function filteredQueue(): Builder
    {
        $query = $this->queue();
        $claimField = $this->isCash() ? 'payer_id' : 'claimed_by_id';
        $this->queueTab === 'mine' ? $query->where($claimField, auth()->id()) : $query->whereNull($claimField);
        $search = mb_substr(trim($this->queueSearch), 0, 100);

        return $query->when($search !== '', fn (Builder $q): Builder => $q->where(fn (Builder $filter): Builder => $filter->where('request_number', 'like', '%'.$search.'%')->orWhereHas('customer', fn (Builder $customer): Builder => $customer->where('name', 'like', '%'.$search.'%'))));
    }

    private function selected(): WithdrawalRequest|GroceryRedemption
    {
        abort_unless($this->selectedId !== null, 404);

        return $this->queue()->whereKey($this->selectedId)->firstOrFail();
    }

    private function selectedForCompletion(): WithdrawalRequest|GroceryRedemption
    {
        abort_unless($this->selectedId !== null, 404);
        $actor = $this->actor();

        return $this->isCash()
            ? app(WithdrawalService::class)->visibleFor($actor)->whereKey($this->selectedId)->where('payer_id', $actor->id)->firstOrFail()
            : app(GroceryService::class)->visibleFor($actor)->whereKey($this->selectedId)->where('claimed_by_id', $actor->id)->firstOrFail();
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    public function render(): View
    {
        if (! in_array($this->queueTab, ['waiting', 'mine'], true)) {
            $this->queueTab = 'waiting';
            $this->addError('queueTab', 'Pilih antrean Menunggu atau Tugas saya.');
        }
        $query = $this->filteredQueue()->with(['customer.customerProfile', 'serviceArea', 'balanceHold']);
        $requests = (clone $query)
            ->orderBy('created_at')->paginate(10, ['*'], 'serviceTasks');
        if ($requests->currentPage() > $requests->lastPage()) {
            $this->setPage($requests->lastPage(), 'serviceTasks');
            $requests = (clone $query)->orderBy('created_at')->paginate(10, ['*'], 'serviceTasks');
        }

        $selected = $this->selectedId === null ? null : $this->queue()->with(['customer.customerProfile', 'serviceArea', 'balanceHold'])->whereKey($this->selectedId)->first();

        return view('livewire.service-task-queue', ['requests' => $requests, 'selected' => $selected, 'cash' => $this->isCash()]);
    }

    public function updatedQueueSearch(): void
    {
        $this->validateOnly('queueSearch', ['queueSearch' => ['nullable', 'string', 'max:100']]);
        $this->resetPage('serviceTasks');
    }

    public function updatedQueueTab(): void
    {
        $this->validateOnly('queueTab', ['queueTab' => ['required', 'in:waiting,mine']]);
        $this->resetPage('serviceTasks');
    }

    private function validateReason(): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']], ['reason.required' => 'Isi alasan kendala.', 'reason.min' => 'Alasan minimal 10 karakter.', 'reason.max' => 'Alasan maksimal 1000 karakter.']);
    }
}
