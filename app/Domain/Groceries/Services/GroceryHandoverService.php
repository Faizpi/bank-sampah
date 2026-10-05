<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Services;

use App\Authorization\PermissionChecker;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Support\GroceryRedemptionScope;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final readonly class GroceryHandoverService
{
    public function __construct(
        private PermissionChecker $permissions,
        private GroceryRedemptionScope $scope,
    ) {}

    /** @return Builder<GroceryRedemption> */
    public function readyFor(User $actor): Builder
    {
        $this->authorize($actor);

        return $this->scope->applyTo(GroceryRedemption::query()->with(['customer', 'package', 'claimant'])->whereIn('status', [GroceryStatus::Waiting, GroceryStatus::Processing, GroceryStatus::ReadyForPickup])->where(fn (Builder $q): Builder => $q->whereNull('claimed_by_id')->orWhere('claimed_by_id', $actor->id)), $actor);
    }

    public function canDownloadProof(User $actor, Media $media): bool
    {
        if ($media->attachable_type !== GroceryRedemption::class || $media->attachable_id === null || $media->getRawOriginal('visibility') !== 'private') {
            return false;
        }
        $redemption = GroceryRedemption::query()->find($media->attachable_id);
        if (! $redemption instanceof GroceryRedemption) {
            return false;
        }

        return ($redemption->customer_id === $actor->id || $redemption->handover_actor_id === $actor->id || ($this->permissions->allows($actor, 'grocery.view') && $this->scope->canOperate($actor, $redemption))) && ($this->permissions->allows($actor, 'grocery.view') || $this->permissions->allows($actor, 'grocery.handover'));
    }

    private function authorize(User $actor): void
    {
        if (! $this->permissions->allows($actor, 'grocery.handover')) {
            throw new AuthorizationException('Penyerahan sembako memerlukan permission khusus.');
        }
    }

    /** @param array<string, mixed> $payload */
}
