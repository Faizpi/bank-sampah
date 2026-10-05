<?php

declare(strict_types=1);

namespace App\Domain\Withdrawals\Services;

use App\Authorization\PermissionChecker;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Platform\Models\Media;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final readonly class WithdrawalPaymentService
{
    public function __construct(
        private PermissionChecker $permissions,
        private VisibleUsers $visibleUsers,
    ) {}

    /** @return Builder<WithdrawalRequest> */
    public function payableFor(User $actor): Builder
    {
        $this->authorize($actor, 'withdrawal.pay');

        return WithdrawalRequest::query()
            ->with(['customer', 'payer', 'balanceHold', 'serviceArea'])
            ->whereIn('status', [WithdrawalStatus::Waiting, WithdrawalStatus::Processing])
            ->where(fn (Builder $q): Builder => $q->whereNull('payer_id')->orWhere('payer_id', $actor->id))
            ->whereNotNull('service_area_id')
            ->whereHas('serviceArea', static fn (Builder $area): Builder => $area->where('is_active', true))
            ->whereExists(function ($query) use ($actor): void {
                $query->selectRaw('1')
                    ->from('staff_service_areas')
                    ->whereColumn('staff_service_areas.service_area_id', 'withdrawal_requests.service_area_id')
                    ->where('staff_service_areas.staff_profile_user_id', $actor->id)
                    ->where(static fn ($dates) => $dates->whereNull('staff_service_areas.active_from')->orWhereDate('staff_service_areas.active_from', '<=', today()))
                    ->where(static fn ($dates) => $dates->whereNull('staff_service_areas.active_to')->orWhereDate('staff_service_areas.active_to', '>=', today()));
            });
    }

    public function canDownloadProof(User $actor, Media $media): bool
    {
        if ($media->attachable_type !== WithdrawalRequest::class || $media->attachable_id === null || $media->getRawOriginal('visibility') !== 'private') {
            return false;
        }
        $withdrawal = WithdrawalRequest::query()->find($media->attachable_id);

        return $withdrawal instanceof WithdrawalRequest
            && ($withdrawal->customer_id === $actor->id || $withdrawal->payer_id === $actor->id || ($this->permissions->allows($actor, 'withdrawal.view') && ($this->permissions->allows($actor, 'user.view.all') || $this->visibleUsers->queryFor($actor)->whereKey($withdrawal->customer_id)->exists())))
            && ($this->permissions->allows($actor, 'withdrawal.view') || $this->permissions->allows($actor, 'withdrawal.pay'));
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk pembayaran.');
        }
    }

    /** @param array<string, mixed> $payload */
}
