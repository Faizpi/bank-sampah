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
use Livewire\Attributes\Layout;
use Livewire\Component;
use LogicException;

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
        $presenter->present($deposit);
        $this->deposit = $deposit->load('items');
        $token = $deposit->verificationToken();
        if ($token === null) {
            throw new LogicException('Final deposit must have a verification token.');
        }
        $this->qrDataUri = $qrPresenter->dataUri(QrToken::fromValue($token));
    }

    public function render(DepositPublicPresenter $presenter): View
    {
        return view('livewire.citizen.deposit-receipt', ['receipt' => $presenter->present($this->deposit)]);
    }
}
