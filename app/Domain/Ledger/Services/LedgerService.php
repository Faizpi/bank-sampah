<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Shared\InvalidValue;
use App\Domain\Shared\Money;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class LedgerService
{
    public function __construct(
        private PermissionChecker $permissions,
        private VisibleUsers $visibleUsers,
        private AuditLogger $auditLogger,
    ) {}

    public function ensureAccount(User $owner): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['user_id' => $owner->id],
            ['status' => 'aktif', 'currency' => 'IDR'],
        );
    }

    public function balanceFor(User $owner): int
    {
        $account = $this->ensureAccount($owner);

        return $account->availableBalance();
    }

    /**
     * @return array{account: LedgerAccount, entry: LedgerEntry}
     */
    public function postDeposit(Deposit $deposit, int $amount, string $sourceKey): array
    {
        Money::rupiah($amount);
        if ($amount <= 0) {
            throw new InvalidValue('Mutasi saldo harus lebih dari nol.');
        }

        $account = LedgerAccount::query()->where('user_id', $deposit->customer_id)->lockForUpdate()->first();
        if ($account === null) {
            $account = LedgerAccount::query()->create(['user_id' => $deposit->customer_id, 'status' => 'aktif', 'currency' => 'IDR']);
            $account = LedgerAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
        }

        $existingSource = LedgerEntry::query()
            ->where('source_type', Deposit::class)
            ->where('source_id', $deposit->id)
            ->where('kind', LedgerEntry::KIND_DEPOSIT)
            ->first();
        if ($existingSource !== null && $existingSource->source_key !== $sourceKey) {
            throw ValidationException::withMessages(['idempotency_key' => 'Setoran sudah memiliki ledger masuk dengan source berbeda.']);
        }

        $existing = LedgerEntry::query()->where('source_key', $sourceKey)->first();
        if ($existing !== null) {
            if ($existing->source_type !== Deposit::class || $existing->source_id !== $deposit->id || $existing->kind !== LedgerEntry::KIND_DEPOSIT) {
                throw ValidationException::withMessages(['idempotency_key' => 'Source ledger sudah digunakan oleh data lain.']);
            }

            return ['account' => $account, 'entry' => $existing];
        }

        $entry = LedgerEntry::query()->create([
            'entry_number' => $this->number('LED'),
            'ledger_account_id' => $account->id,
            'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => LedgerEntry::KIND_DEPOSIT,
            'amount' => $amount,
            'source_type' => Deposit::class,
            'source_id' => $deposit->id,
            'source_key' => $sourceKey,
            'effective_at' => $deposit->occurred_at,
            'balance_after' => $account->availableBalance() + $amount,
        ]);

        return ['account' => $account, 'entry' => $entry];
    }

    public function createHold(User $owner, Model $source, int $amount, string $sourceKey): BalanceHold
    {
        if ($amount <= 0) {
            throw new InvalidValue('Hold harus bernilai positif.');
        }

        return DB::transaction(function () use ($owner, $source, $amount, $sourceKey): BalanceHold {
            $account = LedgerAccount::query()->where('user_id', $owner->id)->lockForUpdate()->first();
            if ($account === null) {
                throw ValidationException::withMessages(['balance' => 'Rekening saldo tidak ditemukan.']);
            }

            $existingSource = BalanceHold::query()
                ->where('source_type', $source::class)
                ->where('source_id', (int) $source->getKey())
                ->first();
            if ($existingSource !== null && $existingSource->source_key !== $sourceKey) {
                throw ValidationException::withMessages(['idempotency_key' => 'Sumber hold sudah digunakan oleh hold lain.']);
            }

            $existing = BalanceHold::query()->where('source_key', $sourceKey)->first();
            if ($existing !== null) {
                if ($existing->ledger_account_id !== $account->id || $existing->amount !== $amount || $existing->source_type !== $source::class || $existing->source_id !== (int) $source->getKey()) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Hold sudah digunakan untuk nilai berbeda.']);
                }

                return $existing;
            }

            if ($account->availableBalance() < $amount) {
                throw ValidationException::withMessages(['amount' => 'Saldo tersedia tidak mencukupi.']);
            }

            return BalanceHold::query()->create([
                'hold_number' => $this->number('HLD'),
                'ledger_account_id' => $account->id,
                'source_type' => $source::class,
                'source_id' => (int) $source->getKey(),
                'source_key' => $sourceKey,
                'amount' => $amount,
                'status' => BalanceHold::STATUS_ACTIVE,
                'held_at' => now(),
            ]);
        });
    }

    public function releaseHold(BalanceHold $hold): BalanceHold
    {
        return DB::transaction(function () use ($hold): BalanceHold {
            LedgerAccount::query()->whereKey($hold->ledger_account_id)->lockForUpdate()->firstOrFail();
            $locked = BalanceHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isActive()) {
                return $locked;
            }

            $locked->forceFill(['status' => BalanceHold::STATUS_RELEASED, 'released_at' => now()])->save();

            return $locked;
        });
    }

    public function convertHold(BalanceHold $hold, string $sourceKey): LedgerEntry
    {
        return DB::transaction(function () use ($hold, $sourceKey): LedgerEntry {
            $account = LedgerAccount::query()->whereKey($hold->ledger_account_id)->lockForUpdate()->firstOrFail();
            $locked = BalanceHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === BalanceHold::STATUS_CONVERTED) {
                return LedgerEntry::query()->where('source_key', $sourceKey)->firstOrFail();
            }
            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['hold' => 'Hold tidak aktif.']);
            }

            $entry = LedgerEntry::query()->where('source_key', $sourceKey)->first();
            if ($entry !== null) {
                if ($entry->ledger_account_id !== $account->id || $entry->source_type !== $locked->source_type || $entry->source_id !== $locked->source_id || $entry->amount !== $locked->amount || $entry->direction !== LedgerEntry::DIRECTION_OUT) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Source ledger sudah digunakan oleh data lain.']);
                }

                $locked->forceFill(['status' => BalanceHold::STATUS_CONVERTED, 'converted_at' => now()])->save();

                return $entry;
            }

            $availableBefore = $account->availableBalance() + $locked->amount;
            if ($availableBefore < $locked->amount) {
                throw ValidationException::withMessages(['amount' => 'Saldo tersedia tidak mencukupi.']);
            }

            $entry = LedgerEntry::query()->create([
                'entry_number' => $this->number('LED'),
                'ledger_account_id' => $account->id,
                'direction' => LedgerEntry::DIRECTION_OUT,
                'kind' => 'hold_conversion',
                'amount' => $locked->amount,
                'source_type' => $locked->source_type,
                'source_id' => $locked->source_id,
                'source_key' => $sourceKey,
                'effective_at' => now(),
                'balance_after' => $availableBefore - $locked->amount,
            ]);
            $locked->forceFill(['status' => BalanceHold::STATUS_CONVERTED, 'converted_at' => now()])->save();

            return $entry;
        });
    }

    /** @return Builder<LedgerEntry> */
    public function visibleEntries(User $actor): Builder
    {
        if (! $this->permissions->allows($actor, 'ledger.view')) {
            return LedgerEntry::query()->whereKey([]);
        }

        $query = LedgerEntry::query()->with(['account.user', 'relatedEntry']);
        if ($this->permissions->allows($actor, 'user.view.all')) {
            return $query;
        }
        if ($this->permissions->allows($actor, 'user.view.area')) {
            return $query->whereHas('account', fn (Builder $account): Builder => $account->whereIn('user_id', $this->visibleUsers->queryFor($actor)->select('users.id')));
        }

        return $query->whereHas('account', fn (Builder $account): Builder => $account->where('user_id', $actor->id));
    }

    /** @return Builder<BalanceHold> */
    public function visibleHolds(User $actor): Builder
    {
        if (! $this->permissions->allows($actor, 'ledger.view')) {
            return BalanceHold::query()->whereKey([]);
        }

        $query = BalanceHold::query()->with(['account.user']);
        if ($this->permissions->allows($actor, 'user.view.all')) {
            return $query;
        }
        if ($this->permissions->allows($actor, 'user.view.area')) {
            return $query->whereHas('account', fn (Builder $account): Builder => $account->whereIn('user_id', $this->visibleUsers->queryFor($actor)->select('users.id')));
        }

        return $query->whereHas('account', fn (Builder $account): Builder => $account->where('user_id', $actor->id));
    }

    public function assertCanAdjust(User $actor): void
    {
        if (! $this->permissions->allows($actor, 'ledger.adjust')) {
            throw new AuthorizationException('Penyesuaian ledger memerlukan permission khusus.');
        }
    }

    public function adjust(User $actor, User $owner, int $delta, string $reason, string $idempotencyKey): LedgerEntry
    {
        $this->assertCanAdjust($actor);
        if ($delta === 0 || mb_strlen(trim($reason)) < 10 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,190}$/', $idempotencyKey) !== 1) {
            throw ValidationException::withMessages(['adjustment' => 'Penyesuaian wajib memiliki nominal, alasan, dan idempotency key yang valid.']);
        }
        if (! $this->permissions->allows($actor, 'user.view.all') && ! $this->visibleUsers->canView($actor, $owner)) {
            throw new AuthorizationException('Rekening berada di luar scope Anda.');
        }

        $payloadHash = hash('sha256', json_encode(['owner_id' => $owner->id, 'delta' => $delta, 'reason' => trim($reason)], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $owner, $delta, $idempotencyKey, $payloadHash): LedgerEntry {
            $key = IdempotencyKey::query()->where('actor_id', $actor->id)->where('scope', 'ledger.adjust')->where('key', $idempotencyKey)->lockForUpdate()->first();
            if ($key !== null) {
                if ($key->payload_hash !== $payloadHash || $key->result_id === null) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Permintaan penyesuaian sudah digunakan untuk payload berbeda.']);
                }

                return LedgerEntry::query()->findOrFail($key->result_id);
            }
            $key = IdempotencyKey::query()->create(['actor_id' => $actor->id, 'scope' => 'ledger.adjust', 'key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'status' => 'processing']);
            $account = LedgerAccount::query()->where('user_id', $owner->id)->lockForUpdate()->first();
            if ($account === null) {
                throw ValidationException::withMessages(['account' => 'Rekening saldo tidak ditemukan.']);
            }
            if ($delta < 0 && $account->availableBalance() < abs($delta)) {
                throw ValidationException::withMessages(['delta' => 'Penyesuaian keluar membuat saldo tersedia negatif.']);
            }
            $entry = LedgerEntry::query()->create([
                'entry_number' => $this->number('LED'),
                'ledger_account_id' => $account->id,
                'direction' => $delta > 0 ? LedgerEntry::DIRECTION_IN : LedgerEntry::DIRECTION_OUT,
                'kind' => LedgerEntry::KIND_ADJUSTMENT,
                'amount' => abs($delta),
                'source_type' => IdempotencyKey::class,
                'source_id' => $key->id,
                'source_key' => 'ledger-adjustment:'.$idempotencyKey,
                'effective_at' => now(),
                'balance_after' => $account->availableBalance() + $delta,
            ]);
            $key->forceFill(['status' => 'succeeded', 'result_type' => LedgerEntry::class, 'result_id' => $entry->id])->save();
            $this->auditLogger->record($actor, 'ledger.adjusted', $entry, [], ['owner_id' => $owner->id, 'delta' => $delta, 'amount' => abs($delta)], $this->correlationId());

            return $entry;
        });
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }

    private function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.strtoupper(Str::random(8));
    }
}
