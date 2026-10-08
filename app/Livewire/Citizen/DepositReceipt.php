<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\CustomersRegions\Services\CustomerQrPresenter;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositPublicPresenter;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class DepositReceipt extends Component
{
    public Deposit $deposit;

    public string $qrDataUri = '';

    public function mount(Deposit $deposit, PermissionChecker $permissions, DepositPublicPresenter $presenter, CustomerQrPresenter $qrPresenter): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'deposit.view'), 403);
        abort_unless($deposit->customer_id === $actor->id, 404);
        try {
            $presenter->present($deposit);
        } catch (ValidationException) {
            abort(404, 'Bukti setoran ini tidak tersedia.');
        }
        $this->deposit = $deposit->load(['items', 'correction']);
        $token = $deposit->verificationToken();
        if ($token === null) {
            abort(404, 'Bukti setoran ini tidak tersedia.');
        }
        $this->qrDataUri = $qrPresenter->dataUri(QrToken::fromValue($token));
    }

    public function render(DepositPublicPresenter $presenter): View
    {
        $deposit = $this->deposit->fresh(['items', 'correction']);
        abort_unless($deposit !== null && $deposit->customer_id === auth()->id() && $deposit->isFinal() && $deposit->status !== Deposit::STATUS_REVERSED, 404);

        return view('livewire.citizen.deposit-receipt', ['receipt' => $presenter->present($deposit)]);
    }
}
