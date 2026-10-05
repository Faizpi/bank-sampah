<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Support\GroceryRedemptionScope;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class GroceryTerminalService
{
    public function __construct(
        private PermissionChecker $permissions,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
        private GroceryRedemptionScope $scope,
    ) {}

    public function cancel(User $actor, GroceryRedemption $redemption, ?string $reason = null): GroceryRedemption
    {
        $this->authorize($actor);
        $reason = $this->requiredReason($reason);

        return DB::transaction(function () use ($actor, $redemption, $reason): GroceryRedemption {
            $locked = $this->lock($redemption);
            if ($locked->customer_id !== $actor->id && ! $this->scope->canOperate($actor, $locked)) {
                throw new AuthorizationException('Penukaran berada di luar scope area snapshot Anda.');
            }
            if ($locked->status === GroceryStatus::Cancelled) {
                return $locked->fresh(['package', 'balanceHold', 'customer']);
            }
            if ($locked->customer_id === $actor->id && $locked->status !== GroceryStatus::PendingVerification) {
                throw new AuthorizationException('Warga hanya dapat membatalkan sebelum persetujuan.');
            }

            return $this->finish($actor, $locked, GroceryStatus::Cancelled, $reason, 'grocery.cancelled');
        });
    }

    public function expire(GroceryRedemption $redemption): GroceryRedemption
    {
        return DB::transaction(function () use ($redemption): GroceryRedemption {
            $locked = $this->lock($redemption);
            if ($locked->status->isTerminal() || $locked->expires_at === null || $locked->expires_at->isFuture()) {
                return $locked->fresh(['package', 'balanceHold', 'customer']);
            }

            return $this->finish(null, $locked, GroceryStatus::Expired, 'Penukaran melewati batas waktu pengambilan.', 'grocery.expired');
        });
    }

    private function finish(?User $actor, GroceryRedemption $locked, GroceryStatus $next, string $reason, string $event): GroceryRedemption
    {
        if ($locked->status === $next) {
            return $locked->fresh(['package', 'balanceHold', 'customer']);
        }
        if (! $locked->canTransitionTo($next)) {
            throw ValidationException::withMessages(['status' => 'Transisi status penukaran sembako tidak valid.']);
        }
        $old = $locked->status;
        $locked->forceFill(['status' => $next, 'cancellation_reason' => $reason])->save();
        $hold = $locked->balanceHold()->first();
        if ($hold instanceof BalanceHold) {
            $this->ledger->releaseHold($hold);
        }
        $locked->statusHistory()->create(['old_status' => $old->value, 'new_status' => $next->value, 'actor_id' => $actor?->id, 'reason' => $reason, 'occurred_at' => now()]);
        $this->auditLogger->record($actor, $event, $locked, ['status' => $old->value], ['status' => $next->value, 'reason' => $reason], $this->correlationId());
        $this->notify($locked, $event);

        return $locked->fresh(['package', 'balanceHold', 'customer']);
    }

    private function lock(GroceryRedemption $redemption): GroceryRedemption
    {
        return GroceryRedemption::query()->whereKey($redemption->id)->lockForUpdate()->firstOrFail();
    }

    private function authorize(User $actor): void
    {
        if (! $this->permissions->allows($actor, 'grocery.cancel')) {
            throw new AuthorizationException('Pembatalan penukaran sembako memerlukan permission khusus.');
        }
    }

    private function requiredReason(?string $reason): string
    {
        $value = trim((string) $reason);
        if (mb_strlen($value) < 10 || mb_strlen($value) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Alasan wajib 10–1000 karakter.']);
        }

        return $value;
    }

    private function notify(GroceryRedemption $redemption, string $type): void
    {
        $label = $type === 'grocery.expired' ? 'kedaluwarsa' : 'dibatalkan';
        NotificationRequested::dispatch(new NotificationPayload(recipientId: $redemption->customer_id, type: $type, title: 'Status penukaran diperbarui', body: 'Penukaran '.$redemption->request_number.' '.$label.' dan hold saldo dilepas.', reference: '/notifikasi', dedupeKey: NotificationDedupeKey::for($type.':'.$redemption->request_number, $redemption->customer_id, 'grocery-v1')));
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
