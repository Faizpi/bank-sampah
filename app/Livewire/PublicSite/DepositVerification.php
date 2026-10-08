<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositPublicPresenter;
use App\Domain\Shared\InvalidValue;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.public')]
final class DepositVerification extends Component
{
    /** @var array{number: string, date: string, weight_kg: string, value: int, status: string}|null */
    #[Locked]
    public ?array $receipt = null;

    #[Locked]
    public string $token;

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function render(DepositPublicPresenter $presenter): View
    {
        try {
            $qrToken = QrToken::fromValue($this->token);
        } catch (InvalidValue) {
            abort(404);
        }

        $deposit = Deposit::query()->where('verification_token_hash', $qrToken->hash())->first();
        if ($deposit === null) {
            abort(404);
        }

        try {
            $this->receipt = $presenter->present($deposit);
        } catch (ValidationException) {
            abort(404);
        }

        return view('livewire.public-site.deposit-verification');
    }
}
