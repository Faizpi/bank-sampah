<?php

declare(strict_types=1);

namespace App\Domain\Withdrawals\Services;

use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\Platform\Models\Media;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

final readonly class WithdrawalService
{
    public function __construct(
        private WithdrawalRequestService $requests,
        private WithdrawalPaymentService $payments,
    ) {}

    /** @param array<string, mixed> $data */
    public function request(User $actor, array $data, string $idempotencyKey): WithdrawalRequest
    {
        return $this->requests->handle($actor, $data, $idempotencyKey);
    }

    public function pay(User $actor, WithdrawalRequest $withdrawal, ?UploadedFile $proof, string $idempotencyKey): WithdrawalRequest
    {
        return app(ClaimedServiceWorkflow::class)->complete($actor, $withdrawal, $proof, $idempotencyKey);
    }

    public function cancel(User $actor, WithdrawalRequest $withdrawal, ?string $reason = null): WithdrawalRequest
    {
        return app(ClaimedServiceWorkflow::class)->cancel($actor, $withdrawal, $reason ?? 'Dibatalkan oleh pemilik pengajuan.');
    }

    public function claim(User $actor, WithdrawalRequest $withdrawal): WithdrawalRequest
    {
        return app(ClaimedServiceWorkflow::class)->claim($actor, $withdrawal);
    }

    /** @return Builder<ServiceArea> */
    public function availableAreasFor(User $actor): Builder
    {
        return $this->requests->availableAreasFor($actor);
    }

    public function resolveRequestArea(User $customer, mixed $areaId): ServiceArea
    {
        return $this->requests->areaForRequest($customer, $areaId);
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
