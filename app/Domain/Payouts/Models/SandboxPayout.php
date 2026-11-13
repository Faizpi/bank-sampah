<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Models;

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Services\PayoutApiFailure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $service_area_id
 * @property int $amount
 * @property int|null $balance_hold_id
 * @property string $request_number
 * @property string $channel
 * @property array{name: string, number: string} $recipient
 * @property array<string, mixed> $request_payload
 * @property string $reference_id
 * @property string $provider_key
 * @property string|null $provider_id
 * @property string|null $business_id
 * @property string $payload_hash
 * @property string $environment
 * @property string $status
 * @property string|null $provider_status
 * @property string|null $failure_code
 * @property int $attempts
 * @property string $consent_version
 * @property CarbonImmutable $consented_at
 * @property string|null $lease
 * @property CarbonImmutable|null $leased_at
 * @property CarbonImmutable|null $next_check_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $reversed_at
 * @property int|null $ledger_entry_id
 * @property int|null $reversal_entry_id
 */
final class SandboxPayout extends Model
{
    protected $guarded = [];

    protected $hidden = ['recipient', 'request_payload', 'provider_key', 'failure_code'];

    protected function casts(): array
    {
        return ['recipient' => 'encrypted:array', 'request_payload' => 'encrypted:array', 'amount' => 'integer', 'attempts' => 'integer', 'consented_at' => 'immutable_datetime', 'leased_at' => 'immutable_datetime', 'next_check_at' => 'immutable_datetime', 'settled_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<Rt, $this> */
    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    /** @return BelongsTo<ServiceArea, $this> */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    /** @return BelongsTo<BalanceHold, $this> */
    public function balanceHold(): BelongsTo
    {
        return $this->belongsTo(BalanceHold::class);
    }

    /** @return BelongsTo<LedgerEntry, $this> */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['succeeded', 'failed', 'reversed', 'cancelled'], true);
    }

    public function label(): string
    {
        return match ($this->status) {
            'succeeded' => 'Simulasi berhasil', 'failed' => 'Simulasi gagal', 'reversed' => 'Saldo simulasi dikembalikan',
            'cancelled' => 'Dibatalkan', 'uncertain' => 'Status sedang diperiksa', default => 'Simulasi sedang diproses',
        };
    }

    public function maskedNumber(): string
    {
        return '••••'.substr($this->recipient['number'], -4);
    }

    public function failureExplanation(): ?string
    {
        return PayoutApiFailure::explanation($this->failure_code);
    }

    public function failureNotice(): ?string
    {
        return PayoutApiFailure::publicMessage($this->failure_code);
    }

    protected static function booted(): void
    {
        self::updating(static function (self $row): void {
            $identity = ['request_number', 'customer_id', 'rt_id', 'service_area_id', 'amount', 'channel', 'recipient', 'request_payload', 'payload_hash', 'reference_id', 'provider_key', 'environment', 'consent_version', 'consented_at'];
            if (array_intersect($identity, array_keys($row->getDirty())) !== [] || ($row->isDirty('balance_hold_id') && $row->getOriginal('balance_hold_id') !== null)
                || ($row->isDirty('provider_id') && $row->getOriginal('provider_id') !== null)) {
                throw new LogicException('Identitas pencairan simulasi tidak dapat diubah.');
            }
        });
        self::deleting(static function (): void {
            throw new LogicException('Riwayat pencairan simulasi tidak dapat dihapus.');
        });
    }
}
