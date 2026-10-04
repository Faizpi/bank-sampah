<?php

declare(strict_types=1);

namespace App\Domain\Withdrawals\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class WithdrawalRequestService
{
    private const string SCOPE = 'withdrawal.request';

    public function __construct(
        private PermissionChecker $permissions,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
        private VisibleUsers $visibleUsers,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data, string $idempotencyKey): WithdrawalRequest
    {
        $this->authorize($actor, 'withdrawal.request');
        $this->validateIdempotencyKey($idempotencyKey);
        $customer = $this->customerForRequest($data['customer_id'] ?? $actor->id);
        if ($customer->id !== $actor->id && ! $this->visibleUsers->canView($actor, $customer)) {
            throw new AuthorizationException('Nasabah berada di luar scope tugas Anda.');
        }
        $amount = $this->amount($data['amount'] ?? null);
        $location = $this->text($data['pickup_location'] ?? null, 'pickup_location', 3, 255);
        $pickupDate = $this->date($data['pickup_date'] ?? null);
        $payloadHash = $this->payloadHash([
            'customer_id' => $customer->id,
            'amount' => $amount,
            'pickup_location' => $location,
            'pickup_date' => $pickupDate->toDateString(),
        ]);

        return DB::transaction(function () use ($actor, $customer, $amount, $location, $pickupDate, $idempotencyKey, $payloadHash): WithdrawalRequest {
            $existing = $this->existingIdempotency($actor, $idempotencyKey, $payloadHash);
            if ($existing !== null) {
                return WithdrawalRequest::query()->findOrFail($existing->result_id);
            }
            $key = $this->createIdempotency($actor, $idempotencyKey, $payloadHash);
            $withdrawal = WithdrawalRequest::query()->create([
                'request_number' => $this->number('WDR'),
                'customer_id' => $customer->id,
                'requested_by_id' => $actor->id,
                'amount' => $amount,
                'status' => WithdrawalStatus::PendingVerification,
                'pickup_location' => $location,
                'pickup_date' => $pickupDate->toDateString(),
            ]);
            $hold = $this->ledger->createHold($customer, $withdrawal, $amount, 'withdrawal:'.$withdrawal->id.':hold');
            $withdrawal->forceFill(['balance_hold_id' => $hold->id])->save();
            $withdrawal->statusHistory()->create(['old_status' => null, 'new_status' => WithdrawalStatus::PendingVerification->value, 'actor_id' => $actor->id, 'reason' => 'Pengajuan pencairan dibuat.', 'occurred_at' => now()]);
            $this->auditLogger->record($actor, 'withdrawal.requested', $withdrawal, [], ['status' => WithdrawalStatus::PendingVerification->value, 'amount' => $amount, 'hold_id' => $hold->id], $this->correlationId());
            $key->forceFill(['status' => 'succeeded', 'result_type' => WithdrawalRequest::class, 'result_id' => $withdrawal->id])->save();
            $this->notify($withdrawal, 'Pengajuan pencairan diterima', 'Pengajuan '.$withdrawal->request_number.' telah menerima penahanan saldo.', 'withdrawal.requested');

            return $withdrawal->fresh(['balanceHold', 'customer']);
        });
    }

    /** @return Builder<WithdrawalRequest> */
    public function visibleFor(User $actor): Builder
    {
        $query = WithdrawalRequest::query()->with(['customer', 'payer', 'approver', 'balanceHold']);
        if ($this->permissions->allows($actor, 'withdrawal.view') && $this->permissions->allows($actor, 'user.view.all')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($actor): void {
            $scope->where('customer_id', $actor->id)->orWhere('payer_id', $actor->id);
            if ($this->permissions->allows($actor, 'withdrawal.view') && $this->permissions->allows($actor, 'user.view.area')) {
                $scope->orWhereIn('customer_id', $this->visibleUsers->queryFor($actor)->select('users.id'));
            }
        });
    }

    public function canView(User $actor, WithdrawalRequest $withdrawal): bool
    {
        if ($withdrawal->customer_id === $actor->id || $withdrawal->payer_id === $actor->id) {
            return $this->permissions->allows($actor, 'withdrawal.view') || $this->permissions->allows($actor, 'withdrawal.pay');
        }

        return $this->permissions->allows($actor, 'withdrawal.view') && ($this->permissions->allows($actor, 'user.view.all') || $this->visibleUsers->queryFor($actor)->whereKey($withdrawal->customer_id)->exists());
    }

    private function customerForRequest(mixed $customerId): User
    {
        if (! is_numeric($customerId) || (int) $customerId < 1) {
            throw ValidationException::withMessages(['customer_id' => 'Nasabah tidak valid.']);
        }
        $customer = User::query()->with(['customerProfile.rt'])->whereKey((int) $customerId)->where('status', 'aktif')->first();
        if ($customer === null || $customer->customerProfile === null) {
            throw ValidationException::withMessages(['customer_id' => 'Nasabah aktif tidak ditemukan.']);
        }

        return $customer;
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk tindakan ini.');
        }
    }

    private function amount(mixed $value): int
    {
        if ((is_string($value) && ! ctype_digit($value)) || (! is_int($value) && ! is_string($value)) || (int) $value < (int) config('app.withdrawal_minimum_amount', 10_000)) {
            throw ValidationException::withMessages(['amount' => 'Nominal harus berupa rupiah integer dan memenuhi minimum.']);
        }

        return (int) $value;
    }

    private function text(mixed $value, string $field, int $min, int $max): string
    {
        $text = trim((string) $value);
        if (mb_strlen($text) < $min || mb_strlen($text) > $max) {
            throw ValidationException::withMessages([$field => 'Nilai tidak memenuhi panjang yang diizinkan.']);
        }

        return $text;
    }

    private function date(mixed $value): CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw ValidationException::withMessages(['pickup_date' => 'Tanggal harus berformat YYYY-MM-DD.']);
        }
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta');
        if ($date === null || $date->format('Y-m-d') !== $value || $date->isBefore(today('Asia/Jakarta'))) {
            throw ValidationException::withMessages(['pickup_date' => 'Tanggal tidak valid.']);
        }

        return $date;
    }

    private function validateIdempotencyKey(string $key): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,190}$/', $key) !== 1) {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key tidak valid.']);
        }
    }

    private function existingIdempotency(User $actor, string $key, string $payloadHash): ?IdempotencyKey
    {
        $existing = IdempotencyKey::query()->where('actor_id', $actor->id)->where('scope', self::SCOPE)->where('key', $key)->lockForUpdate()->first();
        if ($existing === null) {
            return null;
        }
        if ($existing->payload_hash !== $payloadHash || $existing->result_id === null) {
            throw ValidationException::withMessages(['idempotency_key' => 'Permintaan yang sama digunakan dengan payload berbeda atau belum selesai.']);
        }

        return $existing;
    }

    private function createIdempotency(User $actor, string $key, string $payloadHash): IdempotencyKey
    {
        return IdempotencyKey::query()->create(['actor_id' => $actor->id, 'scope' => self::SCOPE, 'key' => $key, 'payload_hash' => $payloadHash, 'status' => 'processing']);
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function notify(WithdrawalRequest $withdrawal, string $title, string $body, string $type): void
    {
        NotificationRequested::dispatch(new NotificationPayload(
            recipientId: $withdrawal->customer_id,
            type: $type,
            title: $title,
            body: $body,
            reference: '/notifikasi',
            dedupeKey: NotificationDedupeKey::for($type.':'.$withdrawal->request_number, $withdrawal->customer_id, 'withdrawal-v1'),
        ));
    }

    private function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.strtoupper(Str::random(8));
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
