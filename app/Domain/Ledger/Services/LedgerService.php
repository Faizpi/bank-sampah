<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Authorization\PermissionChecker;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Shared\InvalidValue;
use App\Domain\Shared\Money;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class LedgerService
{
    public function __construct(private PermissionChecker $permissions) {}

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

        $existing = LedgerEntry::query()->where('source_key', $sourceKey)->first();
        if ($existing !== null) {
            if ($existing->source_type !== Deposit::class || $existing->source_id !== $deposit->id) {
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

            $existing = BalanceHold::query()->where('source_key', $sourceKey)->first();
            if ($existing !== null) {
                if ($existing->ledger_account_id !== $account->id || $existing->amount !== $amount) {
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

    public function assertCanAdjust(User $actor): void
    {
        if (! $this->permissions->allows($actor, 'ledger.adjust')) {
            throw new AuthorizationException('Penyesuaian ledger memerlukan permission khusus.');
        }
    }

    private function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.strtoupper(Str::random(8));
    }
}
