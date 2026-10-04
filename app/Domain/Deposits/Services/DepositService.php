<?php

declare(strict_types=1);

namespace App\Domain\Deposits\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Domain\Shared\Weight;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Services\ResolveWastePrice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class DepositService
{
    public function __construct(
        private PermissionChecker $permissions,
        private ResolveWastePrice $priceResolver,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
    ) {}

    public function createDraft(User $actor, User $customer, string $method = 'langsung', ?string $location = null, ?MobileService $mobileService = null): Deposit
    {
        $this->authorize($actor, 'deposit.create');
        $this->assertCustomerScope($actor, $customer);
        if ($customer->customerProfile === null || $customer->status->value !== 'aktif') {
            throw ValidationException::withMessages(['customer' => 'Nasabah harus aktif dan memiliki profil.']);
        }
        if (! in_array($method, ['langsung', 'penjemputan', 'keliling'], true)) {
            throw ValidationException::withMessages(['method' => 'Metode setoran tidak valid.']);
        }
        if ($method === 'keliling' && $mobileService === null) {
            throw ValidationException::withMessages(['mobile_service_id' => 'Setoran keliling wajib terhubung ke jadwal layanan yang dibuka.']);
        }
        if ($mobileService !== null && (! $mobileService->isOpen() || ! $mobileService->staff()->whereKey($actor->id)->exists())) {
            throw ValidationException::withMessages(['mobile_service_id' => 'Jadwal layanan keliling belum dibuka atau petugas tidak ditugaskan.']);
        }

        return Deposit::query()->create([
            'deposit_number' => $this->number('DEP'),
            'customer_id' => $customer->id,
            'staff_id' => $actor->id,
            'method' => $method,
            'mobile_service_id' => $mobileService?->id,
            'location' => $location,
            'occurred_at' => now(),
            'status' => Deposit::STATUS_DRAFT,
        ]);
    }

    /** @param list<DepositItemInput|array<string, mixed>> $items */
    public function replaceDraftItems(User $actor, Deposit $deposit, array $items): Deposit
    {
        $this->authorize($actor, 'deposit.update-draft');
        $this->assertDraftOwnerScope($actor, $deposit);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Minimal satu detail setoran diperlukan.']);
        }

        return DB::transaction(function () use ($deposit, $items): Deposit {
            $locked = Deposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['deposit' => 'Setoran final tidak dapat diubah.']);
            }

            $locked->items()->delete();
            foreach ($items as $item) {
                $input = $item instanceof DepositItemInput ? $item : DepositItemInput::fromArray($item);
                $type = WasteType::query()->with(['unit', 'category'])->find($input->wasteTypeId);
                $condition = WasteCondition::query()->find($input->conditionId);
                if ($type === null || $condition === null || ! $type->is_active || ! $type->category->is_active || ! $condition->is_active || ! $type->conditions()->whereKey($condition->id)->exists()) {
                    throw ValidationException::withMessages(['items' => 'Jenis atau kondisi sampah tidak aktif atau tidak diterima.']);
                }
                $locked->items()->create([
                    'waste_type_id' => $type->id,
                    'waste_condition_id' => $condition->id,
                    'weight_kg' => Weight::fromDecimal($input->weightKg)->decimal(),
                ]);
            }

            return $locked->load('items');
        });
    }

    /**
     * @param  list<DepositItemInput|array<string, mixed>>|null  $items
     */
    public function finalize(User $actor, Deposit $deposit, string $idempotencyKey, ?array $items = null): Deposit
    {
        $this->authorize($actor, 'deposit.finalize');
        $this->assertDraftOwnerScope($actor, $deposit);
        $this->validateIdempotencyKey($idempotencyKey);
        $payloadHash = hash('sha256', json_encode(['deposit' => $deposit->id, 'items' => $items], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $deposit, $idempotencyKey, $payloadHash, $items): Deposit {
            $existingKey = IdempotencyKey::query()
                ->where('actor_id', $actor->id)
                ->where('scope', 'deposit.finalize')
                ->where('key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existingKey !== null) {
                if ($existingKey->payload_hash !== $payloadHash) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Permintaan yang sama sudah digunakan untuk data berbeda.']);
                }
                if ($existingKey->result_id === null) {
                    throw new RuntimeException('Finalisasi sebelumnya belum memiliki hasil.');
                }

                return Deposit::query()->findOrFail($existingKey->result_id);
            }

            $key = IdempotencyKey::query()->create([
                'actor_id' => $actor->id,
                'scope' => 'deposit.finalize',
                'key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'status' => 'processing',
            ]);
            $locked = Deposit::query()->with('items')->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['deposit' => 'Setoran sudah difinalkan atau tidak lagi berupa draf.']);
            }
            if ($items !== null) {
                $this->replaceDraftItems($actor, $locked, $items);
                $locked->load('items');
            }
            if ($locked->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Minimal satu detail setoran diperlukan.']);
            }

            $totalWeight = 0;
            $total = 0;
            foreach ($locked->items as $item) {
                $type = WasteType::query()->with(['unit', 'category'])->findOrFail($item->waste_type_id);
                $condition = WasteCondition::query()->findOrFail($item->waste_condition_id);
                if (! $type->is_active || ! $type->category->is_active || ! $condition->is_active || ! $type->conditions()->whereKey($condition->id)->exists()) {
                    throw ValidationException::withMessages(['items' => 'Master sampah tidak aktif pada saat finalisasi.']);
                }
                $price = $this->priceResolver->resolve($type, $condition->id, CarbonImmutable::parse((string) $locked->occurred_at, 'Asia/Jakarta'));
                $snapshot = $price->snapshot()->withWeight((string) $item->weight_kg);
                $totalWeight += Weight::fromDecimal($snapshot->weightKg)->grams();
                $total += $snapshot->subtotal;
                $item->forceFill([
                    'waste_type_code' => $snapshot->wasteTypeCode,
                    'waste_type_name' => $snapshot->wasteTypeName,
                    'unit_code' => $snapshot->unitCode,
                    'unit_name' => $snapshot->unitName,
                    'unit_symbol' => $snapshot->unitSymbol,
                    'condition_code' => $snapshot->conditionCode,
                    'condition_name' => $snapshot->conditionName,
                    'weight_kg' => $snapshot->weightKg,
                    'price_per_unit' => $snapshot->pricePerUnit,
                    'subtotal' => $snapshot->subtotal,
                    'rounding_version' => $snapshot->roundingVersion,
                    'price_snapshot' => $snapshot->toArray(),
                ])->save();
            }

            $token = QrToken::generate();
            $locked->forceFill([
                'status' => Deposit::STATUS_FINAL,
                'total_weight_kg' => Weight::fromGrams($totalWeight)->decimal(),
                'total_value' => $total,
                'finalized_at' => now(),
                'idempotency_key' => $idempotencyKey,
                'verification_token_hash' => $token->hash(),
                'verification_token_encrypted' => $token->value(),
            ])->save();
            $ledger = $this->ledger->postDeposit($locked, $total, 'deposit:'.$locked->id.':deposit');
            $this->auditLogger->record($actor, 'deposit.finalized', $locked, ['status' => Deposit::STATUS_DRAFT], ['status' => Deposit::STATUS_FINAL, 'total_value' => $total, 'ledger_entry_id' => $ledger['entry']->id], $this->correlationId());
            $key->forceFill(['status' => 'succeeded', 'result_type' => Deposit::class, 'result_id' => $locked->id])->save();
            NotificationRequested::dispatch(new NotificationPayload(
                recipientId: $locked->customer_id,
                type: 'deposit.finalized',
                title: 'Setoran selesai',
                body: 'Setoran '.$locked->deposit_number.' telah selesai diproses.',
                reference: '/setoran/'.$locked->deposit_number,
                dedupeKey: NotificationDedupeKey::for('deposit.finalized:'.$locked->deposit_number, $locked->customer_id, 'deposit-finalized-v1'),
            ));

            return $locked->fresh(['items']);
        });
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk memproses setoran.');
        }
    }

    private function assertCustomerScope(User $actor, User $customer): void
    {
        if (! app(VisibleUsers::class)->canView($actor, $customer)) {
            throw new AuthorizationException('Nasabah berada di luar scope tugas Anda.');
        }
    }

    private function assertDraftOwnerScope(User $actor, Deposit $deposit): void
    {
        if ($deposit->staff_id !== $actor->id) {
            throw new AuthorizationException('Draf setoran hanya dapat diproses oleh petugas pembuat.');
        }
    }

    private function validateIdempotencyKey(string $key): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,190}$/', $key) !== 1) {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key tidak valid.']);
        }
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
