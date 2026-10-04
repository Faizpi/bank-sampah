<?php

declare(strict_types=1);

namespace App\Domain\Corrections\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Corrections\Models\TransactionCorrection;
use App\Domain\Corrections\Models\TransactionReversal;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class TransactionCorrectionService
{
    public function __construct(
        private PermissionChecker $permissions,
        private AuditLogger $auditLogger,
    ) {}

    public function correct(User $actor, Deposit $deposit, int $newValue, string $reason, ?string $idempotencyKey = null): TransactionCorrection
    {
        $this->authorize($actor, 'transaction.correct');
        $this->validateReason($reason);
        if ($newValue < 0) {
            throw ValidationException::withMessages(['new_value' => 'Nilai koreksi tidak boleh negatif.']);
        }
        if ($idempotencyKey !== null && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,190}$/', $idempotencyKey) !== 1) {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key tidak valid.']);
        }

        return DB::transaction(function () use ($actor, $deposit, $newValue, $reason, $idempotencyKey): TransactionCorrection {
            $payloadHash = hash('sha256', json_encode(['deposit' => $deposit->id, 'new_value' => $newValue, 'reason' => trim($reason)], JSON_THROW_ON_ERROR));
            $idempotency = null;
            if ($idempotencyKey !== null) {
                $idempotency = IdempotencyKey::query()->where('actor_id', $actor->id)->where('scope', 'transaction.correct')->where('key', $idempotencyKey)->lockForUpdate()->first();
                if ($idempotency !== null) {
                    if ($idempotency->payload_hash !== $payloadHash) {
                        throw ValidationException::withMessages(['idempotency_key' => 'Permintaan koreksi sudah digunakan untuk data berbeda.']);
                    }
                    if ($idempotency->result_id !== null) {
                        return TransactionCorrection::query()->findOrFail($idempotency->result_id);
                    }
                } else {
                    $idempotency = IdempotencyKey::query()->create(['actor_id' => $actor->id, 'scope' => 'transaction.correct', 'key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'status' => 'processing']);
                }
            }
            $lockedDeposit = Deposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($lockedDeposit->status !== Deposit::STATUS_FINAL) {
                throw ValidationException::withMessages(['deposit' => 'Hanya setoran final yang belum dikoreksi atau dibalik yang dapat dikoreksi.']);
            }
            $originalValue = (int) $lockedDeposit->total_value;
            $delta = $newValue - $originalValue;
            $account = LedgerAccount::query()->where('user_id', $lockedDeposit->customer_id)->lockForUpdate()->firstOrFail();
            if ($delta < 0 && $account->availableBalance() < abs($delta)) {
                throw ValidationException::withMessages(['new_value' => 'Koreksi akan membuat saldo tersedia negatif.']);
            }
            $correction = TransactionCorrection::query()->create([
                'correction_number' => 'COR-'.strtoupper(Str::random(18)),
                'deposit_id' => $lockedDeposit->id,
                'reason' => trim($reason),
                'before_values' => ['total_value' => $originalValue, 'status' => $lockedDeposit->status],
                'after_values' => ['total_value' => $newValue, 'status' => Deposit::STATUS_CORRECTED],
                'delta_value' => $delta,
                'status' => 'final',
                'created_by' => $actor->id,
                'finalized_at' => now(),
            ]);
            if ($delta !== 0) {
                $entry = LedgerEntry::query()->create([
                    'entry_number' => 'LED-'.strtoupper(Str::random(18)),
                    'ledger_account_id' => $account->id,
                    'direction' => $delta > 0 ? LedgerEntry::DIRECTION_IN : LedgerEntry::DIRECTION_OUT,
                    'kind' => LedgerEntry::KIND_CORRECTION,
                    'amount' => abs($delta),
                    'source_type' => TransactionCorrection::class,
                    'source_id' => $correction->id,
                    'source_key' => 'correction:'.$correction->id,
                    'effective_at' => now(),
                    'balance_after' => $account->availableBalance() + $delta,
                ]);
                $this->auditLogger->record($actor, 'transaction.corrected', $correction, ['total_value' => $originalValue], ['total_value' => $newValue, 'ledger_entry_id' => $entry->id], $this->correlationId());
            } else {
                $this->auditLogger->record($actor, 'transaction.corrected', $correction, ['total_value' => $originalValue], ['total_value' => $newValue], $this->correlationId());
            }
            $lockedDeposit->forceFill(['status' => Deposit::STATUS_CORRECTED])->save();
            $idempotency?->forceFill(['status' => 'succeeded', 'result_type' => TransactionCorrection::class, 'result_id' => $correction->id])->save();
            NotificationRequested::dispatch(new NotificationPayload(
                recipientId: $lockedDeposit->customer_id,
                type: 'transaction.corrected',
                title: 'Koreksi setoran tercatat',
                body: 'Koreksi setoran '.$lockedDeposit->deposit_number.' telah tercatat.',
                reference: '/setoran/'.$lockedDeposit->deposit_number.'/koreksi',
                dedupeKey: NotificationDedupeKey::for('transaction.corrected:'.$correction->correction_number, $lockedDeposit->customer_id, 'transaction-corrected-v1'),
            ));

            return $correction;
        });
    }

    public function reverse(User $actor, Deposit $deposit, string $reason): TransactionReversal
    {
        $this->authorize($actor, 'transaction.reverse');
        $this->validateReason($reason);

        return DB::transaction(function () use ($actor, $deposit, $reason): TransactionReversal {
            $lockedDeposit = Deposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            $original = LedgerEntry::query()->where('source_type', Deposit::class)->where('source_id', $lockedDeposit->id)->first();
            if ($lockedDeposit->status !== Deposit::STATUS_FINAL || $original === null) {
                throw ValidationException::withMessages(['deposit' => 'Hanya setoran final dengan ledger masuk yang dapat dibalik.']);
            }
            if (TransactionReversal::query()->where('original_deposit_id', $lockedDeposit->id)->exists()) {
                throw ValidationException::withMessages(['deposit' => 'Setoran sudah memiliki reversal.']);
            }
            $account = LedgerAccount::query()->whereKey($original->ledger_account_id)->lockForUpdate()->firstOrFail();
            $original = LedgerEntry::query()->whereKey($original->id)->lockForUpdate()->firstOrFail();
            if ($account->availableBalance() < $original->amount) {
                throw ValidationException::withMessages(['deposit' => 'Saldo tersedia tidak cukup untuk reversal.']);
            }
            $reversal = TransactionReversal::query()->create([
                'reversal_number' => 'REV-'.strtoupper(Str::random(18)),
                'original_deposit_id' => $lockedDeposit->id,
                'original_entry_id' => $original->id,
                'reason' => trim($reason),
                'created_by' => $actor->id,
                'finalized_at' => now(),
            ]);
            $entry = LedgerEntry::query()->create([
                'entry_number' => 'LED-'.strtoupper(Str::random(18)),
                'ledger_account_id' => $account->id,
                'direction' => LedgerEntry::DIRECTION_OUT,
                'kind' => LedgerEntry::KIND_REVERSAL,
                'amount' => $original->amount,
                'source_type' => TransactionReversal::class,
                'source_id' => $reversal->id,
                'source_key' => 'reversal:'.$reversal->id,
                'related_entry_id' => $original->id,
                'effective_at' => now(),
                'balance_after' => $account->availableBalance() - $original->amount,
            ]);
            $original->forceFill(['related_entry_id' => $entry->id])->saveQuietly();
            $lockedDeposit->forceFill(['status' => Deposit::STATUS_REVERSED])->save();
            $this->auditLogger->record($actor, 'transaction.reversed', $reversal, ['status' => Deposit::STATUS_FINAL], ['status' => Deposit::STATUS_REVERSED, 'ledger_entry_id' => $entry->id], $this->correlationId());
            NotificationRequested::dispatch(new NotificationPayload(
                recipientId: $lockedDeposit->customer_id,
                type: 'transaction.reversed',
                title: 'Setoran dibalik',
                body: 'Setoran '.$lockedDeposit->deposit_number.' telah diproses melalui pembalikan resmi.',
                reference: '/setoran/'.$lockedDeposit->deposit_number.'/koreksi',
                dedupeKey: NotificationDedupeKey::for('transaction.reversed:'.$reversal->reversal_number, $lockedDeposit->customer_id, 'transaction-reversed-v1'),
            ));

            return $reversal;
        });
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Aksi koreksi memerlukan permission khusus.');
        }
    }

    private function validateReason(string $reason): void
    {
        $length = Str::length(trim($reason));
        if ($length < 10 || $length > 1000) {
            throw ValidationException::withMessages(['reason' => 'Alasan harus memiliki 10 sampai 1000 karakter.']);
        }
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
