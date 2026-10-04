<?php

declare(strict_types=1);

namespace App\Domain\Deposits\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Actions\AssistedCustomerService as AssistedCustomerServiceAction;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\MobileServices\Services\MobileDepositGuard;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Domain\Platform\Actions\StorePrivateMedia;
use App\Domain\Platform\Models\Media;
use App\Domain\Shared\Weight;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Services\ResolveWastePrice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class DepositService
{
    public function __construct(
        private PermissionChecker $permissions,
        private ResolveWastePrice $priceResolver,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
        private StorePrivateMedia $mediaStore,
        private MobileDepositGuard $mobileDepositGuard,
        private AssistedCustomerServiceAction $assistedServices,
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

            return $this->replaceDraftItemsInTransaction($locked, $items);
        });
    }

    /** @param list<DepositItemInput|array<string, mixed>> $items */
    private function replaceDraftItemsInTransaction(Deposit $locked, array $items): Deposit
    {
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
    }

    /**
     * @param  list<DepositItemInput|array<string, mixed>>|null  $items
     */
    public function finalize(User $actor, Deposit $deposit, string $idempotencyKey, ?array $items = null, ?UploadedFile $evidence = null, ?MobileService $mobileService = null): Deposit
    {
        return $this->finalizeInternal($actor, $deposit, $idempotencyKey, $items, $evidence, $mobileService);
    }

    /**
     * @param  list<DepositItemInput|array<string, mixed>>|null  $items
     */
    public function finalizeAndLinkAssisted(User $actor, Deposit $deposit, string $idempotencyKey, int $assistedServiceId, ?array $items = null, ?UploadedFile $evidence = null, ?MobileService $mobileService = null): Deposit
    {
        return $this->finalizeInternal($actor, $deposit, $idempotencyKey, $items, $evidence, $mobileService, $assistedServiceId);
    }

    /**
     * @param  list<DepositItemInput|array<string, mixed>>|null  $items
     */
    private function finalizeInternal(User $actor, Deposit $deposit, string $idempotencyKey, ?array $items, ?UploadedFile $evidence, ?MobileService $mobileService, ?int $assistedServiceId = null): Deposit
    {
        $this->authorize($actor, 'deposit.finalize');
        $this->assertDraftOwnerScope($actor, $deposit);
        if ($mobileService !== null && $deposit->mobile_service_id !== null && $deposit->mobile_service_id !== $mobileService->id) {
            throw ValidationException::withMessages(['mobile_service_id' => 'Jadwal layanan setoran tidak cocok dengan draf.']);
        }
        $this->validateIdempotencyKey($idempotencyKey);
        $this->assertEvidenceRequired($deposit, $evidence);
        if ($items !== null) {
            $this->authorize($actor, 'deposit.update-draft');
        }
        $checksum = $evidence === null ? null : hash_file('sha256', $evidence->getRealPath());
        if ($evidence !== null && ! is_string($checksum)) {
            throw ValidationException::withMessages(['evidence' => 'Bukti setoran tidak dapat dibaca.']);
        }
        $payload = ['deposit' => $deposit->id, 'items' => $items, 'evidence_checksum' => $checksum];
        if ($assistedServiceId !== null) {
            $payload['assisted_service_id'] = $assistedServiceId;
        }
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $media = null;

        try {
            $existing = DB::transaction(function () use ($actor, $idempotencyKey, $payloadHash, $assistedServiceId): ?IdempotencyKey {
                if ($assistedServiceId !== null) {
                    $this->assistedServices->lockForDepositLink($actor, $assistedServiceId);
                }

                return $this->existingIdempotency($actor, $idempotencyKey, $payloadHash);
            });
            if ($existing !== null) {
                if ($assistedServiceId === null) {
                    return Deposit::query()->findOrFail($existing->result_id);
                }

                return DB::transaction(function () use ($actor, $assistedServiceId, $existing): Deposit {
                    $record = $this->assistedServices->lockForDepositLink($actor, $assistedServiceId);
                    $lockedDeposit = Deposit::query()->whereKey($existing->result_id)->lockForUpdate()->firstOrFail();
                    $this->assistedServices->linkDepositInTransaction($actor, $record, $lockedDeposit);

                    return $lockedDeposit->fresh(['items', 'media']);
                });
            }
            $media = $evidence === null ? null : $this->mediaStore->handle($evidence, $actor);

            $result = DB::transaction(function () use ($actor, $deposit, $idempotencyKey, $payloadHash, $items, $media, $assistedServiceId): Deposit {
                $record = $assistedServiceId === null ? null : $this->assistedServices->lockForDepositLink($actor, $assistedServiceId);
                $existingKey = $this->existingIdempotency($actor, $idempotencyKey, $payloadHash);
                if ($existingKey !== null) {
                    if ($record !== null) {
                        $existingDeposit = Deposit::query()->whereKey($existingKey->result_id)->lockForUpdate()->firstOrFail();
                        $this->assistedServices->linkDepositInTransaction($actor, $record, $existingDeposit);

                        return $existingDeposit->fresh(['items', 'media']);
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
                    $this->replaceDraftItemsInTransaction($locked, $items);
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

                if ($media instanceof Media) {
                    $media->forceFill(['attachable_type' => Deposit::class, 'attachable_id' => $locked->id])->save();
                }
                $token = QrToken::generate();
                if ($locked->mobile_service_id !== null) {
                    $this->mobileDepositGuard->attach($actor, $locked, MobileService::query()->findOrFail($locked->mobile_service_id), WasteType::query()->findOrFail($locked->items->first()->waste_type_id));
                }
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
                if ($record !== null) {
                    $this->assistedServices->linkDepositInTransaction($actor, $record, $locked);
                }

                return $locked->fresh(['items', 'media']);
            });
            if ($media instanceof Media && $media->attachable_id === null) {
                $this->deleteMedia($media);
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($media instanceof Media) {
                $this->deleteMedia($media);
            }

            throw $exception;
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk memproses setoran.');
        }
    }

    private function assertEvidenceRequired(Deposit $deposit, ?UploadedFile $evidence): void
    {
        if ($deposit->method === 'langsung' && $evidence === null) {
            throw ValidationException::withMessages(['evidence' => 'Bukti setoran wajib diunggah.']);
        }
    }

    private function existingIdempotency(User $actor, string $key, string $payloadHash): ?IdempotencyKey
    {
        $existing = IdempotencyKey::query()
            ->where('actor_id', $actor->id)
            ->where('scope', 'deposit.finalize')
            ->where('key', $key)
            ->lockForUpdate()
            ->first();
        if ($existing === null) {
            return null;
        }
        if ($existing->payload_hash !== $payloadHash || $existing->result_id === null) {
            throw ValidationException::withMessages(['idempotency_key' => 'Permintaan yang sama sudah digunakan untuk data berbeda.']);
        }

        return $existing;
    }

    private function deleteMedia(Media $media): void
    {
        Storage::disk((string) $media->disk)->delete((string) $media->path);
        $media->delete();
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
