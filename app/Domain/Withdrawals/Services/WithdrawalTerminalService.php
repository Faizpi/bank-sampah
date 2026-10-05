<?php

declare(strict_types=1);

namespace App\Domain\Withdrawals\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class WithdrawalTerminalService
{
    public function __construct(
        private PermissionChecker $permissions,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
        private VisibleUsers $visibleUsers,
    ) {}

    public function cancel(User $actor, WithdrawalRequest $withdrawal, ?string $reason = null): WithdrawalRequest
    {
        $this->authorize($actor, 'withdrawal.cancel');
        if ($withdrawal->customer_id === $actor->id && $withdrawal->status === WithdrawalStatus::Cancelled) {
            return $withdrawal->fresh(['balanceHold', 'customer']);
        }
        if ($withdrawal->customer_id === $actor->id && $withdrawal->status !== WithdrawalStatus::PendingVerification) {
            throw new AuthorizationException('Warga hanya dapat membatalkan pengajuan sebelum persetujuan.');
        }
        if ($withdrawal->customer_id !== $actor->id && ! ($this->permissions->allows($actor, 'user.view.all') || $this->visibleUsers->queryFor($actor)->whereKey($withdrawal->customer_id)->exists())) {
            throw new AuthorizationException('Pencairan berada di luar scope Anda.');
        }

        return $this->terminal($actor, $withdrawal, WithdrawalStatus::Cancelled, $this->requiredReason($reason), 'withdrawal.cancelled');
    }

    public function expire(WithdrawalRequest $withdrawal): WithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawal): WithdrawalRequest {
            $locked = $this->lock($withdrawal);
            if ($locked->status->isTerminal() || $locked->expires_at === null || $locked->expires_at->isFuture()) {
                return $locked;
            }
            $reason = 'Pengajuan kedaluwarsa melewati batas pengambilan.';

            return $this->finish($locked, null, WithdrawalStatus::Expired, $reason, 'withdrawal.expired');
        });
    }

    private function terminal(User $actor, WithdrawalRequest $withdrawal, WithdrawalStatus $next, string $reason, string $event): WithdrawalRequest
    {
        return DB::transaction(function () use ($actor, $withdrawal, $next, $reason, $event): WithdrawalRequest {
            $locked = $this->lock($withdrawal);
            if ($locked->status === $next) {
                return $locked;
            }

            return $this->finish($locked, $actor, $next, $reason, $event);
        });
    }

    private function finish(WithdrawalRequest $withdrawal, ?User $actor, WithdrawalStatus $next, string $reason, string $event): WithdrawalRequest
    {
        if (! $withdrawal->canTransitionTo($next)) {
            throw ValidationException::withMessages(['status' => 'Transisi status pencairan tidak valid.']);
        }
        $old = $withdrawal->status;
        $withdrawal->forceFill(['status' => $next, 'cancellation_reason' => $reason])->save();
        $hold = $withdrawal->balanceHold()->first();
        if ($hold instanceof BalanceHold) {
            $this->ledger->releaseHold($hold);
        }
        $withdrawal->statusHistory()->create(['old_status' => $old->value, 'new_status' => $next->value, 'actor_id' => $actor?->id, 'reason' => $reason, 'occurred_at' => now()]);
        $this->auditLogger->record($actor, $event, $withdrawal, ['status' => $old->value], ['status' => $next->value, 'reason' => $reason], $this->correlationId());
        $this->notify($withdrawal, $event);

        return $withdrawal->fresh(['balanceHold', 'customer']);
    }

    private function lock(WithdrawalRequest $withdrawal): WithdrawalRequest
    {
        return WithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk membatalkan pencairan.');
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

    private function notify(WithdrawalRequest $withdrawal, string $type): void
    {
        $label = $type === 'withdrawal.expired' ? 'kedaluwarsa' : 'dibatalkan';
        NotificationRequested::dispatch(new NotificationPayload(
            recipientId: $withdrawal->customer_id,
            type: $type,
            title: 'Status pencairan diperbarui',
            body: 'Pencairan '.$withdrawal->request_number.' '.$label.' dan hold dilepas.',
            reference: '/notifikasi',
            dedupeKey: NotificationDedupeKey::for($type.':'.$withdrawal->request_number, $withdrawal->customer_id, 'withdrawal-v1'),
        ));
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
