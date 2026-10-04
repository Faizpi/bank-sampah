<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Groceries\Enums\GrocerySource;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class GroceryRequestService
{
    private const string SCOPE = 'grocery.request';

    public function __construct(
        private PermissionChecker $permissions,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
        private VisibleUsers $visibleUsers,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data, string $idempotencyKey): GroceryRedemption
    {
        $this->authorize($actor, 'grocery.request');
        $this->validateIdempotencyKey($idempotencyKey);
        $customer = $this->customer($data['customer_id'] ?? $actor->id);
        if ($customer->id !== $actor->id && ! $this->visibleUsers->canView($actor, $customer)) {
            throw new AuthorizationException('Nasabah berada di luar scope tugas Anda.');
        }
        $source = $this->source($data['source_type'] ?? GrocerySource::Balance->value);
        if ($source === GrocerySource::FreeAid && $customer->id === $actor->id) {
            throw new AuthorizationException('Bantuan gratis harus diajukan oleh petugas untuk warga.');
        }
        $packageId = $this->packageId($data['package_id'] ?? null);
        $payloadHash = $this->payloadHash(['customer_id' => $customer->id, 'package_id' => $packageId, 'source_type' => $source->value]);

        return DB::transaction(function () use ($actor, $customer, $packageId, $source, $idempotencyKey, $payloadHash): GroceryRedemption {
            $existing = $this->existingIdempotency($actor, $idempotencyKey, $payloadHash);
            if ($existing !== null) {
                return GroceryRedemption::query()->findOrFail($existing->result_id);
            }
            $key = $this->createIdempotency($actor, $idempotencyKey, $payloadHash);
            $package = GroceryPackage::query()->whereKey($packageId)->lockForUpdate()->first();
            if ($package === null || ! $package->isAvailableOn(now('Asia/Jakarta')->toImmutable())) {
                throw ValidationException::withMessages(['package_id' => 'Paket sembako tidak aktif atau tidak tersedia.']);
            }
            if ($package->value <= 0) {
                throw ValidationException::withMessages(['package_id' => 'Nilai paket tidak valid.']);
            }
            $redemption = GroceryRedemption::query()->create([
                'request_number' => $this->number('GRC'),
                'customer_id' => $customer->id,
                'requested_by_id' => $actor->id,
                'grocery_package_id' => $package->id,
                'value_snapshot' => $package->value,
                'package_snapshot' => ['code' => $package->code, 'name' => $package->name, 'contents' => $package->contents, 'value' => $package->value],
                'source_type' => $source,
                'status' => GroceryStatus::PendingVerification,
            ]);
            $hold = $source->usesBalance() ? $this->ledger->createHold($customer, $redemption, $package->value, 'grocery:'.$redemption->id.':hold') : null;
            if ($hold !== null) {
                $redemption->forceFill(['balance_hold_id' => $hold->id])->save();
            }
            $redemption->statusHistory()->create(['old_status' => null, 'new_status' => GroceryStatus::PendingVerification->value, 'actor_id' => $actor->id, 'reason' => 'Pengajuan penukaran sembako dibuat.', 'occurred_at' => now()]);
            $this->auditLogger->record($actor, 'grocery.requested', $redemption, [], ['status' => GroceryStatus::PendingVerification->value, 'value_snapshot' => $package->value, 'source_type' => $source->value, 'hold_id' => $hold?->id], $this->correlationId());
            $key->forceFill(['status' => 'succeeded', 'result_type' => GroceryRedemption::class, 'result_id' => $redemption->id])->save();
            $this->notify($redemption, 'Pengajuan sembako diterima', 'Pengajuan '.$redemption->request_number.' telah diterima.', 'grocery.requested');

            return $redemption->fresh(['package', 'balanceHold', 'customer']);
        });
    }

    /** @return Builder<GroceryRedemption> */
    public function visibleFor(User $actor): Builder
    {
        $this->authorize($actor, 'grocery.view');
        $query = GroceryRedemption::query()->with(['customer', 'package', 'balanceHold', 'approver', 'preparedBy', 'handoverActor']);
        if ($this->permissions->allows($actor, 'grocery.view') && $this->permissions->allows($actor, 'user.view.all')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($actor): void {
            $scope->where('customer_id', $actor->id)->orWhere('requested_by_id', $actor->id)->orWhere('handover_actor_id', $actor->id);
            if ($this->permissions->allows($actor, 'grocery.view') && $this->permissions->allows($actor, 'user.view.area')) {
                $scope->orWhereIn('customer_id', $this->visibleUsers->queryFor($actor)->select('users.id'));
            }
        });
    }

    public function canView(User $actor, GroceryRedemption $redemption): bool
    {
        if (in_array($actor->id, [$redemption->customer_id, $redemption->requested_by_id, $redemption->handover_actor_id], true)) {
            return $this->permissions->allows($actor, 'grocery.view') || $this->permissions->allows($actor, 'grocery.request') || $this->permissions->allows($actor, 'grocery.handover');
        }

        return $this->permissions->allows($actor, 'grocery.view') && ($this->permissions->allows($actor, 'user.view.all') || $this->visibleUsers->queryFor($actor)->whereKey($redemption->customer_id)->exists());
    }

    private function customer(mixed $id): User
    {
        if (! is_numeric($id) || (int) $id < 1) {
            throw ValidationException::withMessages(['customer_id' => 'Nasabah tidak valid.']);
        }
        $customer = User::query()->with('customerProfile')->whereKey((int) $id)->where('status', 'aktif')->first();
        if ($customer === null || $customer->customerProfile === null) {
            throw ValidationException::withMessages(['customer_id' => 'Nasabah aktif tidak ditemukan.']);
        }

        return $customer;
    }

    private function source(mixed $value): GrocerySource
    {
        try {
            return GrocerySource::from((string) $value);
        } catch (\ValueError) {
            throw ValidationException::withMessages(['source_type' => 'Jenis sumber bantuan tidak valid.']);
        }
    }

    private function packageId(mixed $value): int
    {
        if (! is_numeric($value) || (int) $value < 1) {
            throw ValidationException::withMessages(['package_id' => 'Paket sembako tidak valid.']);
        }

        return (int) $value;
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk mengajukan penukaran sembako.');
        }
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
            throw ValidationException::withMessages(['idempotency_key' => 'Permintaan berbeda menggunakan idempotency key yang sama.']);
        }

        return $existing;
    }

    private function createIdempotency(User $actor, string $key, string $hash): IdempotencyKey
    {
        return IdempotencyKey::query()->create(['actor_id' => $actor->id, 'scope' => self::SCOPE, 'key' => $key, 'payload_hash' => $hash, 'status' => 'processing']);
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function notify(GroceryRedemption $redemption, string $title, string $body, string $type): void
    {
        NotificationRequested::dispatch(new NotificationPayload(recipientId: $redemption->customer_id, type: $type, title: $title, body: $body, reference: '/notifikasi', dedupeKey: NotificationDedupeKey::for($type.':'.$redemption->request_number, $redemption->customer_id, 'grocery-v1')));
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
