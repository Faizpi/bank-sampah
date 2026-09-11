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
use Livewire\Component;

#[Layout('layouts.public')]
final class DepositVerification extends Component
{
    /** @var array{number: string, date: string, weight_kg: string, value: int, status: string}|null */
    public ?array $receipt = null;

    public function mount(string $token, DepositPublicPresenter $presenter): void
    {
        try {
            $qrToken = QrToken::fromValue($token);
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
    }

    public function render(): View
    {
        return view('livewire.public-site.deposit-verification');
    }
}
