<?php

declare(strict_types=1);

namespace App\Domain\Withdrawals\Services;

use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Platform\Models\Media;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

final readonly class WithdrawalService
{
    public function __construct(
        private WithdrawalRequestService $requests,
        private WithdrawalDecisionService $decisions,
        private WithdrawalPaymentService $payments,
        private WithdrawalTerminalService $terminals,
    ) {}

    /** @param array<string, mixed> $data */
    public function request(User $actor, array $data, string $idempotencyKey): WithdrawalRequest
    {
        return $this->requests->handle($actor, $data, $idempotencyKey);
    }

    public function approve(User $actor, WithdrawalRequest $withdrawal, bool $approved, ?string $reason = null, ?string $location = null, ?string $date = null): WithdrawalRequest
    {
        return $this->decisions->approve($actor, $withdrawal, $approved, $reason, $location, $date);
    }

    public function assignPayer(User $actor, WithdrawalRequest $withdrawal, User $payer): WithdrawalRequest
    {
        return $this->decisions->assignPayer($actor, $withdrawal, $payer);
    }

    public function pay(User $actor, WithdrawalRequest $withdrawal, string $recipientVerification, string $recipientReference, UploadedFile $proof, string $idempotencyKey): WithdrawalRequest
    {
        return $this->payments->handle($actor, $withdrawal, $recipientVerification, $recipientReference, $proof, $idempotencyKey);
    }

    public function cancel(User $actor, WithdrawalRequest $withdrawal, ?string $reason = null): WithdrawalRequest
    {
        return $this->terminals->cancel($actor, $withdrawal, $reason);
    }

    public function expire(WithdrawalRequest $withdrawal): WithdrawalRequest
    {
        return $this->terminals->expire($withdrawal);
    }

    /** @return Builder<ServiceArea> */
    public function availableAreasFor(User $actor): Builder
    {
        return $this->requests->availableAreasFor($actor);
    }

    /** @return Builder<WithdrawalRequest> */
    public function visibleFor(User $actor): Builder
    {
        return $this->requests->visibleFor($actor);
    }

    public function canView(User $actor, WithdrawalRequest $withdrawal): bool
    {
        return $this->requests->canView($actor, $withdrawal);
    }

    /** @return Builder<WithdrawalRequest> */
    public function payableFor(User $actor): Builder
    {
        return $this->payments->payableFor($actor);
    }

    public function canDownloadProof(User $actor, Media $media): bool
    {
        return $this->payments->canDownloadProof($actor, $media);
    }
}
