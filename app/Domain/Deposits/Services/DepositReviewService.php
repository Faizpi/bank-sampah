<?php

declare(strict_types=1);

namespace App\Domain\Deposits\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class DepositReviewService
{
    public function __construct(
        private PermissionChecker $permissions,
        private VisibleUsers $visibleUsers,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
    ) {}

    public function canReview(User $actor, Deposit $deposit): bool
    {
        return $this->permissions->allows($actor, 'deposit.approve')
            && $deposit->isPendingReview()
            && $deposit->staff_id !== $actor->id
            && $this->isWithinScope($actor, $deposit);
    }

    public function approve(User $actor, Deposit $deposit, string $reason, string $idempotencyKey): Deposit
    {
        return $this->handle($actor, $deposit, $reason, $idempotencyKey, true);
    }

    public function reject(User $actor, Deposit $deposit, string $reason, string $idempotencyKey): Deposit
    {
        return $this->handle($actor, $deposit, $reason, $idempotencyKey, false);
    }

    private function handle(User $actor, Deposit $deposit, string $reason, string $idempotencyKey, bool $approved): Deposit
    {
        $this->authorize($actor, $deposit);
        $reason = trim($reason);
        if (Str::length($reason) < 10 || Str::length($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Catatan pemeriksaan harus memiliki 10 sampai 1000 karakter.']);
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,190}$/', $idempotencyKey) !== 1) {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key tidak valid.']);
        }

        $scope = $approved ? 'deposit.review.approve' : 'deposit.review.reject';
        $payloadHash = hash('sha256', json_encode(['deposit_id' => $deposit->id, 'decision' => $approved ? 'approve' : 'reject', 'reason' => $reason], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $deposit, $reason, $idempotencyKey, $scope, $payloadHash, $approved): Deposit {
            $existing = IdempotencyKey::activeForUpdate($actor->id, $scope, $idempotencyKey);
            if ($existing !== null) {
                if ($existing->payload_hash !== $payloadHash || $existing->result_id === null) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Permintaan pemeriksaan sudah digunakan untuk data berbeda atau masih diproses.']);
                }

                return Deposit::query()->findOrFail($existing->result_id);
            }

            $key = IdempotencyKey::query()->create(['actor_id' => $actor->id, 'scope' => $scope, 'key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'status' => 'processing']);
            $locked = Deposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPendingReview()) {
                throw ValidationException::withMessages(['deposit' => 'Setoran tidak lagi menunggu persetujuan.']);
            }
            if ($locked->staff_id === $actor->id) {
                throw new AuthorizationException('Petugas pembuat tidak dapat menyetujui setoran sendiri.');
            }

            $now = now();
            if ($approved) {
                $ledger = $this->ledger->postDeposit($locked, (int) $locked->total_value, 'deposit:'.$locked->id.':deposit');
                $locked->forceFill(['status' => Deposit::STATUS_FINAL, 'finalized_at' => $now, 'reviewed_by' => $actor->id, 'reviewed_at' => $now, 'review_reason' => $reason])->save();
                $this->auditLogger->record($actor, 'deposit.review_approved', $locked, ['status' => Deposit::STATUS_PENDING_REVIEW], ['status' => Deposit::STATUS_FINAL, 'ledger_entry_id' => $ledger['entry']->id, 'review_reason' => $reason], $this->correlationId());
                NotificationRequested::dispatch(new NotificationPayload(
                    recipientId: $locked->customer_id,
                    type: 'deposit.finalized',
                    title: 'Setoran selesai',
                    body: 'Setoran '.$locked->deposit_number.' telah selesai diproses.',
                    reference: '/setoran/'.$locked->deposit_number,
                    dedupeKey: NotificationDedupeKey::for('deposit.finalized:'.$locked->deposit_number, $locked->customer_id, 'deposit-finalized-v1'),
                ));
            } else {
                $locked->forceFill(['status' => Deposit::STATUS_REJECTED, 'reviewed_by' => $actor->id, 'reviewed_at' => $now, 'review_reason' => $reason])->save();
                $this->auditLogger->record($actor, 'deposit.review_rejected', $locked, ['status' => Deposit::STATUS_PENDING_REVIEW], ['status' => Deposit::STATUS_REJECTED, 'review_reason' => $reason], $this->correlationId());
            }

            $key->forceFill(['status' => 'succeeded', 'result_type' => Deposit::class, 'result_id' => $locked->id])->save();

            return $locked->fresh(['items', 'correction']);
        });
    }

    private function authorize(User $actor, Deposit $deposit): void
    {
        if (! $this->permissions->allows($actor, 'deposit.approve')) {
            throw new AuthorizationException('Persetujuan setoran memerlukan permission khusus.');
        }
        if ($deposit->staff_id === $actor->id || ! $this->isWithinScope($actor, $deposit)) {
            throw new AuthorizationException('Setoran berada di luar kewenangan pemeriksaan Anda.');
        }
    }

    private function isWithinScope(User $actor, Deposit $deposit): bool
    {
        if ($this->permissions->allows($actor, 'user.view.all')) {
            return true;
        }

        return $this->permissions->allows($actor, 'user.view.area')
            && Deposit::query()->whereKey($deposit->id)->whereIn('customer_id', $this->visibleUsers->queryFor($actor)->select('users.id'))->exists();
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
