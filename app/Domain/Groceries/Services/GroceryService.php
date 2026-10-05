<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Services;

use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

final readonly class GroceryService
{
    public function __construct(
        private GroceryPackageService $packages,
        private GroceryRequestService $requests,
        private GroceryDecisionService $decisions,
        private GroceryHandoverService $handovers,
        private GroceryTerminalService $terminals,
    ) {}

    /** @return Builder<GroceryPackage> */
    public function activePackages(User $actor): Builder
    {
        return $this->packages->activeFor($actor);
    }

    /** @param array<string, mixed> $data */
    public function request(User $actor, array $data, string $idempotencyKey): GroceryRedemption
    {
        return $this->requests->handle($actor, $data, $idempotencyKey);
    }

    public function approve(User $actor, GroceryRedemption $redemption, bool $approved, ?string $availabilityNote = null, ?string $reason = null): GroceryRedemption
    {
        return $this->decisions->approve($actor, $redemption, $approved, $availabilityNote, $reason);
    }

    public function prepare(User $actor, GroceryRedemption $redemption): GroceryRedemption
    {
        return $this->decisions->prepare($actor, $redemption);
    }

    public function ready(User $actor, GroceryRedemption $redemption): GroceryRedemption
    {
        return $this->decisions->ready($actor, $redemption);
    }

    public function handover(User $actor, GroceryRedemption $redemption, string $recipientVerification, string $recipientReference, UploadedFile $proof, string $idempotencyKey): GroceryRedemption
    {
        return $this->handovers->handle($actor, $redemption, $recipientVerification, $recipientReference, $proof, $idempotencyKey);
    }

    public function cancel(User $actor, GroceryRedemption $redemption, ?string $reason = null): GroceryRedemption
    {
        return $this->terminals->cancel($actor, $redemption, $reason);
    }

    public function expire(GroceryRedemption $redemption): GroceryRedemption
    {
        return $this->terminals->expire($redemption);
    }

    /** @return Builder<ServiceArea> */
    public function availableAreasFor(User $actor): Builder
    {
        return $this->requests->availableAreasFor($actor);
    }

    /** @return Builder<GroceryRedemption> */
    public function visibleFor(User $actor): Builder
    {
        return $this->requests->visibleFor($actor);
    }

    public function canView(User $actor, GroceryRedemption $redemption): bool
    {
        return $this->requests->canView($actor, $redemption);
    }

    /** @return Builder<GroceryRedemption> */
    public function readyForHandover(User $actor): Builder
    {
        return $this->handovers->readyFor($actor);
    }

    public function canDownloadProof(User $actor, Media $media): bool
    {
        return $this->handovers->canDownloadProof($actor, $media);
    }
}
