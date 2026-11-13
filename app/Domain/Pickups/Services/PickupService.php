<?php

declare(strict_types=1);

namespace App\Domain\Pickups\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Domain\Operations\Services\ServiceTaskGuard;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Platform\Actions\StorePrivateMedia;
use App\Domain\Platform\Models\Media;
use App\Domain\Shared\Weight;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use App\Support\Auth\AdminReauthentication;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class PickupService
{
    private const IDEMPOTENCY_REQUEST_SCOPE = 'pickup.request';

    private const IDEMPOTENCY_COMPLETE_SCOPE = 'pickup.complete';

    public function __construct(
        private PermissionChecker $permissions,
        private StorePrivateMedia $mediaStore,
        private AuditLogger $auditLogger,
        private DepositService $deposits,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     * @param  list<UploadedFile>  $photos
     */
    public function submit(User $actor, array $data, array $items, array $photos, string $idempotencyKey): PickupRequest
    {
        $actor = User::query()->whereKey($actor->id)->first();
        if ($actor === null) {
            throw new AuthorizationException('Akun tidak lagi aktif.');
        }
        $this->authorize($actor, 'pickup.request');
        $this->validateIdempotencyKey($idempotencyKey);

        $customer = $this->customerForSubmission($actor, $data);
        $area = $this->resolveActiveArea($customer, $data['service_area_id'] ?? null);
        $address = $this->text($data['address'] ?? null, 'address', 5, 500);
        $selectedDate = $this->businessDate($data['selected_date'] ?? null);
        $notes = $this->optionalText($data['notes'] ?? null, 'notes', 1000);
        $normalizedItems = $this->normalizeItems($items);
        $estimatedWeight = $this->estimatedWeight($normalizedItems);
        $this->validatePhotos($photos);
        $payloadHash = $this->payloadHash($data, $normalizedItems, $photos, $customer->id, $area->id, $selectedDate->toDateString());
        $storedMedia = [];

        try {
            return DB::transaction(function () use ($actor, $customer, $area, $address, $selectedDate, $notes, $normalizedItems, $estimatedWeight, $photos, $idempotencyKey, $payloadHash, &$storedMedia): PickupRequest {
                $actor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $this->authorize($actor, 'pickup.request');
                $existingKey = IdempotencyKey::activeForUpdate($actor->id, self::IDEMPOTENCY_REQUEST_SCOPE, $idempotencyKey);

                if ($existingKey !== null) {
                    $this->assertSamePayload($existingKey, $payloadHash);

                    return PickupRequest::query()->findOrFail($existingKey->result_id);
                }

                $key = IdempotencyKey::query()->create([
                    'actor_id' => $actor->id,
                    'scope' => self::IDEMPOTENCY_REQUEST_SCOPE,
                    'key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'status' => 'processing',
                ]);

                // No capacity check: citizens can submit for any valid date.
                $pickup = PickupRequest::query()->create([
                    'request_number' => $this->number('PUP'),
                    'customer_id' => $customer->id,
                    'rt_id' => $customer->customerProfile?->rt_id,
                    'service_area_id' => $area->id,
                    'address' => $address,
                    'selected_date' => $selectedDate->toDateString(),
                    'estimated_weight_kg' => $estimatedWeight,
                    'notes' => $notes,
                    'status' => PickupStatus::Waiting,
                ]);

                foreach ($normalizedItems as $item) {
                    $pickup->items()->create($item);
                }

                foreach ($photos as $photo) {
                    $media = $this->mediaStore->handlePhoto($photo, $actor);
                    $storedMedia[] = $media;
                    if ($media->mime_type !== 'image/jpeg' || $media->size > 1 * 1024 * 1024) {
                        throw ValidationException::withMessages(['photos' => 'Setiap foto penjemputan harus berupa JPEG maksimal 1 MB.']);
                    }
                    $media->forceFill([
                        'attachable_type' => PickupRequest::class,
                        'attachable_id' => $pickup->id,
                    ])->save();
                }

                $this->recordStatus($pickup, $actor, null, PickupStatus::Waiting, 'Pengajuan dibuat.');
                $this->auditLogger->record($actor, 'pickup.requested', $pickup, [], [
                    'status' => PickupStatus::Waiting->value,
                    'service_area_id' => $area->id,
                    'selected_date' => $selectedDate->toDateString(),
                    'item_count' => count($normalizedItems),
                    'photo_count' => count($photos),
                ], $this->correlationId());
                $key->forceFill(['status' => 'succeeded', 'result_type' => PickupRequest::class, 'result_id' => $pickup->id])->save();

                return $pickup->fresh(['items', 'media']);
            });
        } catch (Throwable $exception) {
            foreach ($storedMedia as $media) {
                $this->deleteStoredMedia($media);
            }

            throw $exception;
        }
    }

    /** @return Builder<ServiceArea> */
    public function eligibleAreasFor(User $customer): Builder
    {
        $rtId = $customer->customerProfile?->rt_id;

        return ServiceArea::query()
            ->where('is_active', true)
            ->whereHas('rts', fn (Builder $query): Builder => $query
                ->whereKey($rtId)
                ->where('is_active', true)
                ->whereHas('rw', fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->whereHas('dusun', fn (Builder $query): Builder => $query->where('is_active', true))));
    }

    public function activeAreaFor(User $customer, mixed $serviceAreaId): ?ServiceArea
    {
        return is_numeric($serviceAreaId)
            ? $this->eligibleAreasFor($customer)->whereKey((int) $serviceAreaId)->first()
            : null;
    }

    public function claim(User $actor, PickupRequest $pickup): PickupRequest
    {
        return DB::transaction(function () use ($actor, $pickup): PickupRequest {
            $locked = $this->lockPickup($pickup);
            app(ServiceTaskGuard::class)->check($actor, UserRole::Officer, $locked->service_area_id);
            if ($locked->status === PickupStatus::Scheduled && $locked->assigned_staff_id === $actor->id) {
                return $locked;
            }
            $this->assertTransition($locked, PickupStatus::Scheduled);
            if ($locked->assigned_staff_id !== null) {
                throw ValidationException::withMessages(['status' => 'Tugas sudah diambil petugas lain.']);
            }
            $old = $locked->status;
            $locked->forceFill(['status' => PickupStatus::Scheduled, 'assigned_staff_id' => $actor->id, 'claimed_at' => now(), 'scheduled_at' => now(), 'scheduled_date' => $locked->selected_date])->save();
            $this->recordStatus($locked, $actor, $old, PickupStatus::Scheduled, 'Petugas mengambil tugas.');
            $this->auditLogger->record($actor, 'pickup.claimed', $locked, ['status' => $old->value], ['status' => $locked->status->value], $this->correlationId());

            return $locked;
        }, 5);
    }

    public function release(User $actor, PickupRequest $pickup, string $reason): PickupRequest
    {
        $reason = $this->requiredReason($reason, 'reason');

        return DB::transaction(function () use ($actor, $pickup, $reason): PickupRequest {
            $locked = $this->lockPickup($pickup);
            app(ServiceTaskGuard::class)->check($actor, UserRole::Officer, $locked->service_area_id);
            $this->assertRecordScope($actor, $locked, true);
            $this->assertNoPickupException($locked);
            if (! in_array($locked->status, [PickupStatus::Scheduled, PickupStatus::EnRoute], true)) {
                throw ValidationException::withMessages(['status' => 'Tugas tidak dapat dilepas setelah sampah dijemput.']);
            }
            $old = $locked->status;
            $locked->forceFill(['status' => PickupStatus::Waiting, 'assigned_staff_id' => null, 'claimed_at' => null, 'claim_released_at' => now(), 'scheduled_date' => null, 'scheduled_at' => null, 'en_route_at' => null])->save();
            $this->recordStatus($locked, $actor, $old, PickupStatus::Waiting, $reason);
            $this->auditLogger->record($actor, 'pickup.released', $locked, ['status' => $old->value], ['status' => $locked->status->value, 'reason' => $reason], $this->correlationId());

            return $locked;
        }, 5);
    }

    public function begin(User $actor, PickupRequest $pickup): PickupRequest
    {
        $this->authorize($actor, 'pickup.execute');
        $this->assertRecordScope($actor, $pickup, true);

        return $this->transition($actor, $pickup, PickupStatus::EnRoute, null, true);
    }

    public function markPickedUp(User $actor, PickupRequest $pickup): PickupRequest
    {
        $this->authorize($actor, 'pickup.execute');
        $this->assertRecordScope($actor, $pickup, true);

        return $this->transition($actor, $pickup, PickupStatus::PickedUp, null, true);
    }

    /**
     * @param  list<array<string, mixed>|DepositItemInput>  $actualItems
     */
    public function complete(User $actor, PickupRequest $pickup, array $actualItems, string $idempotencyKey, ?UploadedFile $evidence = null): PickupRequest
    {
        $this->authorize($actor, 'pickup.complete');
        $this->assertRecordScope($actor, $pickup, true);
        $this->validateIdempotencyKey($idempotencyKey);
        $normalized = array_map(static fn (array|DepositItemInput $item): DepositItemInput => $item instanceof DepositItemInput ? $item : DepositItemInput::fromArray($item), $actualItems);
        if ($normalized === []) {
            throw ValidationException::withMessages(['items' => 'Minimal satu hasil timbang diperlukan.']);
        }
        $evidenceChecksum = $evidence === null ? null : hash_file('sha256', $evidence->getRealPath());
        if ($evidence !== null && ! is_string($evidenceChecksum)) {
            throw ValidationException::withMessages(['evidence' => 'Bukti foto penjemputan tidak dapat dibaca.']);
        }
        $payloadHash = hash('sha256', json_encode([
            'pickup' => $pickup->id,
            'items' => array_map(static fn (DepositItemInput $item): array => [$item->wasteTypeId, $item->conditionId, $item->weightKg], $normalized),
            'evidence_checksum' => $evidenceChecksum,
        ], JSON_THROW_ON_ERROR));
        $media = null;

        try {
            return DB::transaction(function () use ($actor, $pickup, $normalized, $idempotencyKey, $payloadHash, $evidence, &$media): PickupRequest {
                $locked = $this->lockPickup($pickup);
                app(ServiceTaskGuard::class)->check($actor, UserRole::Officer, $locked->service_area_id);
                $this->assertRecordScope($actor, $locked, true);
                if ($locked->status === PickupStatus::Completed) {
                    if ($locked->getAttribute('completion_payload_hash') !== $payloadHash) {
                        throw ValidationException::withMessages(['idempotency_key' => 'Pickup sudah selesai dengan hasil timbang/lampiran berbeda.']);
                    }

                    return $locked;
                }
                $this->assertNoPickupException($locked);
                $existingKey = IdempotencyKey::activeForUpdate($actor->id, self::IDEMPOTENCY_COMPLETE_SCOPE, $idempotencyKey);
                if ($existingKey !== null) {
                    $this->assertSamePayload($existingKey, $payloadHash);

                    return PickupRequest::query()->findOrFail($existingKey->result_id);
                }
                $key = IdempotencyKey::query()->create(['actor_id' => $actor->id, 'scope' => self::IDEMPOTENCY_COMPLETE_SCOPE, 'key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'status' => 'processing']);
                $locked = $this->lockPickup($pickup);
                app(ServiceTaskGuard::class)->check($actor, UserRole::Officer, $locked->service_area_id);
                $this->assertRecordScope($actor, $locked, true);
                $this->assertNoPickupException($locked);
                $this->assertTransition($locked, PickupStatus::Completed);
                if ($locked->status !== PickupStatus::PickedUp) {
                    throw ValidationException::withMessages(['status' => 'Penjemputan harus berstatus dijemput sebelum diselesaikan.']);
                }
                if ($locked->customer_id !== $actor->id) {
                    app(ServiceTaskGuard::class)->check($actor, UserRole::Officer, $locked->service_area_id);
                    $this->assertRecordScope($actor, $locked, true);
                }
                if ($locked->deposit_id !== null) {
                    throw ValidationException::withMessages(['status' => 'Hasil timbang sudah dikirim. Tunggu keputusan pemeriksaan setoran.']);
                }
                if ($evidence !== null) {
                    $media = $this->mediaStore->handlePhoto($evidence, $actor);
                    $media->forceFill([
                        'attachable_type' => PickupRequest::class,
                        'attachable_id' => $locked->id,
                    ])->save();
                }
                $deposit = $this->deposits->createPickupDraft($actor, $locked);
                $deposit->forceFill(['pickup_request_id' => $locked->id])->save();
                $deposit = $this->deposits->finalize($actor, $deposit, 'pickup-'.$locked->id.'-'.$idempotencyKey, array_map(static fn (DepositItemInput $item): array => ['waste_type_id' => $item->wasteTypeId, 'condition_id' => $item->conditionId, 'weight_kg' => $item->weightKg], $normalized));
                $oldStatus = $locked->status;
                $next = $deposit->isFinal() ? PickupStatus::Completed : PickupStatus::PickedUp;
                $locked->forceFill(['status' => $next, 'completion_payload_hash' => $payloadHash, 'deposit_id' => $deposit->id, 'completed_at' => $deposit->isFinal() ? now() : null])->save();
                $reason = $deposit->isFinal() ? 'Penimbangan aktual dan setoran final berhasil.' : 'Hasil timbang menunggu persetujuan setoran; saldo belum bertambah.';
                $this->recordStatus($locked, $actor, $oldStatus, $next, $reason);
                $this->auditLogger->record($actor, $deposit->isFinal() ? 'pickup.completed' : 'pickup.deposit_review_requested', $locked, ['status' => $oldStatus->value], ['status' => $next->value, 'deposit_id' => $deposit->id, 'actual_weight_kg' => $deposit->total_weight_kg], $this->correlationId());
                $key->forceFill(['status' => 'succeeded', 'result_type' => PickupRequest::class, 'result_id' => $locked->id])->save();
                if ($deposit->isFinal()) {
                    $this->notifyStatus($locked, 'Penjemputan selesai', 'Penjemputan '.$locked->request_number.' selesai dan setoran telah dibuat.', 'pickup.completed');
                }

                return $locked->fresh(['items', 'media', 'deposit']);
            });
        } catch (Throwable $exception) {
            if ($media instanceof Media) {
                $durablePickup = PickupRequest::query()->with('media')->find($pickup->id);
                $durableMedia = Media::query()->find($media->id);
                $isCommittedEvidence = $durablePickup instanceof PickupRequest
                    && in_array($durablePickup->status, [PickupStatus::Completed, PickupStatus::PickedUp], true)
                    && $durablePickup->deposit_id !== null
                    && $durableMedia instanceof Media
                    && $durableMedia->attachable_type === PickupRequest::class
                    && $durableMedia->attachable_id === $durablePickup->id;

                if (! $isCommittedEvidence) {
                    $this->deleteStoredMedia($media);
                }
            }

            throw $exception;
        }
    }

    public function cancel(User $actor, PickupRequest $pickup, ?string $reason = null): PickupRequest
    {
        $reason = $this->optionalText($reason, 'cancellation_reason', 1000);

        return DB::transaction(function () use ($actor, $pickup, $reason): PickupRequest {
            $locked = $this->lockPickup($pickup);
            $current = User::query()->lockForUpdate()->findOrFail($actor->id);
            $this->authorize($current, 'pickup.cancel');
            if ($locked->status === PickupStatus::Cancelled && $locked->customer_id === $current->id) {
                return $locked;
            }
            if ($locked->customer_id === $current->id) {
                if (! $current->hasRole('warga') || $locked->status !== PickupStatus::Waiting) {
                    throw new AuthorizationException('Warga membatalkan hanya sebelum tugas diambil.');
                }
            } else {
                app(ServiceTaskGuard::class)->check($current, UserRole::Officer, $locked->service_area_id);
                $this->assertRecordScope($current, $locked, true);
                if (! in_array($locked->status, [PickupStatus::Scheduled, PickupStatus::EnRoute], true)) {
                    throw ValidationException::withMessages(['status' => 'Sampah sudah dijemput; laporkan pengecualian, jangan batalkan.']);
                }
                $this->requiredReason($reason, 'cancellation_reason');
            }
            $this->assertNoPickupException($locked);
            $this->assertTransition($locked, PickupStatus::Cancelled);
            $old = $locked->status;
            $locked->forceFill(['status' => PickupStatus::Cancelled, 'cancellation_reason' => $reason])->save();
            $this->recordStatus($locked, $current, $old, PickupStatus::Cancelled, $reason);
            $this->auditLogger->record($current, 'pickup.cancelled', $locked, ['status' => $old->value], ['status' => $locked->status->value, 'reason' => $reason], $this->correlationId());

            return $locked;
        }, 5);
    }

    public function canView(User $actor, PickupRequest $pickup): bool
    {
        return $this->visibleFor($actor)->whereKey($pickup->id)->exists();
    }

    public function canDownloadMedia(User $actor, Media $media): bool
    {
        if ($media->attachable_type !== PickupRequest::class || ! is_int($media->attachable_id)) {
            return false;
        }
        $pickup = PickupRequest::query()->find($media->attachable_id);

        return $pickup instanceof PickupRequest && $this->canView($actor, $pickup);
    }

    /** @return Builder<PickupRequest> */
    public function visibleFor(User $actor): Builder
    {
        $query = PickupRequest::query();
        if (! $this->permissions->allows($actor, 'pickup.view')) {
            return $query->whereKey([]);
        }
        if ($actor->hasRole('admin')) {
            return $query;
        }
        if ($actor->hasRole('warga')) {
            return $query->where('customer_id', $actor->id);
        }
        if (! $actor->hasRole('petugas')) {
            return $query->whereKey([]);
        }

        return $query->whereHas('serviceArea', fn (Builder $area): Builder => $area->where('is_active', true))
            ->whereIn('service_area_id', StaffServiceArea::query()->select('service_area_id')->where('staff_profile_user_id', $actor->id)
                ->where(fn (Builder $q): Builder => $q->whereNull('active_from')->orWhereDate('active_from', '<=', today()))
                ->where(fn (Builder $q): Builder => $q->whereNull('active_to')->orWhereDate('active_to', '>=', today())));
    }

    /** @param array<string, mixed> $data */
    private function customerForSubmission(User $actor, array $data): User
    {
        $customerId = $data['customer_id'] ?? $actor->id;
        if (! is_numeric($customerId) || (int) $customerId < 1) {
            throw ValidationException::withMessages(['customer_id' => 'Nasabah tidak valid.']);
        }
        $customer = User::query()->with('customerProfile')->whereKey((int) $customerId)->where('status', UserStatus::Active->value)->first();
        if ($customer === null || $customer->customerProfile === null) {
            throw ValidationException::withMessages(['customer_id' => 'Nasabah aktif tidak ditemukan.']);
        }
        if ($customer->id !== $actor->id) {
            throw new AuthorizationException('Nasabah berada di luar scope tugas Anda.');
        }

        return $customer;
    }

    private function resolveActiveArea(User $customer, mixed $serviceAreaId): ServiceArea
    {
        if (! is_numeric($serviceAreaId)) {
            throw ValidationException::withMessages(['service_area_id' => 'Area pelayanan wajib dipilih.']);
        }
        if (! $customer->customerProfile instanceof CustomerProfile) {
            throw ValidationException::withMessages(['customer_id' => 'Profil nasabah tidak tersedia.']);
        }

        $area = $this->activeAreaFor($customer, $serviceAreaId);
        if ($area === null) {
            throw ValidationException::withMessages(['service_area_id' => 'Alamat berada di luar area pelayanan aktif.']);
        }

        return $area;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{waste_type_id: int, estimated_weight_kg: string|null, estimated_quantity: int|null}>
     */
    private function normalizeItems(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Minimal satu jenis/perkiraan diperlukan.']);
        }
        $normalized = [];
        $seen = [];
        foreach ($items as $index => $item) {
            $typeId = $item['waste_type_id'] ?? null;
            $weight = $item['estimated_weight_kg'] ?? ($item['weight_kg'] ?? null);
            $quantity = $item['estimated_quantity'] ?? ($item['quantity'] ?? null);
            $weight = $weight === '' ? null : $weight;
            $quantity = $quantity === '' ? null : $quantity;
            if (! is_numeric($typeId) || (int) $typeId < 1 || (isset($weight) && (! is_string($weight) || ! $this->isPositiveWeight($weight))) || (isset($quantity) && (! is_numeric($quantity) || (int) $quantity < 1))) {
                throw ValidationException::withMessages(["items.{$index}" => 'Jenis dan perkiraan harus valid.']);
            }
            $typeId = (int) $typeId;
            if (isset($seen[$typeId])) {
                throw ValidationException::withMessages(["items.{$index}" => 'Jenis sampah tidak boleh diulang.']);
            }
            $type = WasteType::query()->with('category')->whereKey($typeId)->first();
            if ($type === null || ! $type->is_active || $type->category?->is_active !== true) {
                throw ValidationException::withMessages(["items.{$index}" => 'Jenis sampah tidak aktif.']);
            }
            $seen[$typeId] = true;
            $normalized[] = [
                'waste_type_id' => $typeId,
                'estimated_weight_kg' => $weight,
                'estimated_quantity' => $quantity === null ? null : (int) $quantity,
            ];
        }

        return $normalized;
    }

    /** @param list<array{estimated_weight_kg: ?string}> $items */
    private function estimatedWeight(array $items): ?string
    {
        $grams = 0;
        foreach ($items as $item) {
            if ($item['estimated_weight_kg'] !== null) {
                $grams += Weight::fromDecimal($item['estimated_weight_kg'])->grams();
            }
        }

        return $grams === 0 ? null : Weight::fromGrams($grams)->decimal();
    }

    /** @param list<UploadedFile> $photos */
    private function validatePhotos(array $photos): void
    {
        if (count($photos) > 2) {
            throw ValidationException::withMessages(['photos' => 'Maksimal dua foto opsional.']);
        }
    }

    private function transition(User $actor, PickupRequest $pickup, PickupStatus $next, ?string $reason = null, bool $assignedOnly = false): PickupRequest
    {
        return DB::transaction(function () use ($actor, $pickup, $next, $reason, $assignedOnly): PickupRequest {
            $locked = $this->lockPickup($pickup);
            app(ServiceTaskGuard::class)->check($actor, UserRole::Officer, $locked->service_area_id);
            if ($assignedOnly && $locked->assigned_staff_id !== $actor->id) {
                throw new AuthorizationException('Penjemputan tidak ditugaskan kepada Anda.');
            }
            $this->assertNoPickupException($locked);
            $this->assertTransition($locked, $next);
            $oldStatus = $locked->status;
            $timestamps = match ($next) {
                PickupStatus::EnRoute => ['en_route_at' => now()],
                PickupStatus::PickedUp => ['picked_up_at' => now()],
                default => [],
            };
            $locked->forceFill([
                'status' => $next,
                ...$timestamps,
            ])->save();
            $this->recordStatus($locked, $actor, $oldStatus, $next, $reason);
            $this->auditLogger->record($actor, 'pickup.status.changed', $locked, ['status' => $oldStatus->value], ['status' => $next->value], $this->correlationId());
            $this->notifyStatus($locked, 'Status penjemputan diperbarui', 'Status penjemputan '.$locked->request_number.' telah diperbarui.', 'pickup.status.changed');

            return $locked->fresh(['items', 'media', 'assignedStaff']);
        });
    }

    private function assertTransition(PickupRequest $pickup, PickupStatus $next): void
    {
        if (! $pickup->canTransitionTo($next)) {
            throw ValidationException::withMessages(['status' => 'Status penjemputan tidak dapat diubah ke tahap tersebut.']);
        }
    }

    private function lockPickup(PickupRequest $pickup): PickupRequest
    {
        return PickupRequest::query()->whereKey($pickup->id)->lockForUpdate()->firstOrFail();
    }

    private function assertRecordScope(User $actor, PickupRequest $pickup, bool $assignedOnly): void
    {
        if (! $this->canView($actor, $pickup)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk tindakan ini.');
        }
        if ($assignedOnly && $pickup->assigned_staff_id !== $actor->id) {
            throw new AuthorizationException('Penjemputan tidak ditugaskan kepada Anda.');
        }
    }

    private function assertNoPickupException(PickupRequest $pickup): void
    {
        if ($pickup->exception_reported_at !== null && $pickup->exception_resolved_at === null) {
            throw ValidationException::withMessages(['status' => 'Pickup dikunci untuk pemeriksaan Admin.']);
        }
    }

    public function reportException(User $actor, PickupRequest $pickup, string $reason): PickupRequest
    {
        $reason = $this->requiredReason($reason, 'reason');

        return DB::transaction(function () use ($actor, $pickup, $reason): PickupRequest {
            $locked = $this->lockPickup($pickup);
            app(ServiceTaskGuard::class)->check($actor, UserRole::Officer, $locked->service_area_id);
            $this->assertRecordScope($actor, $locked, true);
            if ($locked->isTerminal()) {
                throw ValidationException::withMessages(['status' => 'Pickup sudah selesai.']);
            }
            $locked->forceFill(['exception_reported_at' => now(), 'exception_reason' => $reason, 'exception_resolved_at' => null, 'exception_resolved_by_id' => null, 'exception_resolution' => null])->save();
            $this->recordStatus($locked, $actor, $locked->status, $locked->status, 'Pengecualian: '.$reason);
            $this->auditLogger->record($actor, 'pickup.exception_reported', $locked, [], ['reason' => $reason, 'claimant_id' => $locked->assigned_staff_id], $this->correlationId());

            return $locked;
        }, 5);
    }

    public function freezeException(User $admin, PickupRequest $pickup, string $reason, string $password): PickupRequest
    {
        $reason = $this->requiredReason($reason, 'reason');
        app(AdminReauthentication::class)->confirm($admin, $password);

        return DB::transaction(function () use ($admin, $pickup, $reason): PickupRequest {
            $locked = $this->lockPickup($pickup);
            $admin = User::query()->lockForUpdate()->findOrFail($admin->id);
            $this->authorize($admin, 'transaction.exception');
            if ($locked->assigned_staff_id === null || $locked->isTerminal() || in_array($admin->id, [$locked->customer_id, $locked->assigned_staff_id], true)) {
                throw ValidationException::withMessages(['status' => 'Hanya klaim bermasalah pihak lain yang dapat dikunci.']);
            }
            $locked->forceFill(['exception_reported_at' => now(), 'exception_reason' => $reason, 'exception_resolved_at' => null, 'exception_resolved_by_id' => null, 'exception_resolution' => null])->save();
            $this->auditLogger->record($admin, 'pickup.exception_frozen', $locked, [], ['reason' => $reason, 'claimant_id' => $locked->assigned_staff_id], $this->correlationId());

            return $locked;
        }, 5);
    }

    public function reassignException(User $admin, PickupRequest $pickup, User $staff, string $reason, string $password): PickupRequest
    {
        $reason = $this->requiredReason($reason, 'reason');
        app(AdminReauthentication::class)->confirm($admin, $password);

        return DB::transaction(function () use ($admin, $pickup, $staff, $reason): PickupRequest {
            $locked = $this->lockPickup($pickup);
            $admin = User::query()->lockForUpdate()->findOrFail($admin->id);
            $this->authorize($admin, 'transaction.exception');
            app(ServiceTaskGuard::class)->check($staff, UserRole::Officer, $locked->service_area_id);
            if ($locked->exception_reported_at === null || $locked->exception_resolved_at !== null || $locked->isTerminal()
                || $locked->assigned_staff_id === null || $locked->deposit_id !== null || in_array($admin->id, [$locked->customer_id, $locked->assigned_staff_id], true)) {
                throw ValidationException::withMessages(['status' => 'Pengalihan hanya melalui pengecualian pickup yang belum selesai.']);
            }
            $oldClaimant = $locked->assigned_staff_id;
            $locked->forceFill(['assigned_staff_id' => $staff->id, 'claimed_at' => now(), 'exception_resolved_by_id' => $admin->id, 'exception_resolved_at' => now(), 'exception_resolution' => $reason])->save();
            $this->recordStatus($locked, $admin, $locked->status, $locked->status, 'Pengalihan pengecualian: '.$reason);
            $this->auditLogger->record($admin, 'pickup.exception_reassigned', $locked, ['claimant_id' => $oldClaimant], ['claimant_id' => $staff->id, 'reason' => $reason], $this->correlationId());

            return $locked;
        }, 5);
    }

    private function recordStatus(PickupRequest $pickup, ?User $actor, ?PickupStatus $old, PickupStatus $new, ?string $reason): void
    {
        $pickup->statusHistory()->create(['old_status' => $old?->value, 'new_status' => $new->value, 'actor_id' => $actor?->id, 'reason' => $reason, 'occurred_at' => now()]);
    }

    private function notifyStatus(PickupRequest $pickup, string $title, string $body, string $type): void
    {
        NotificationRequested::dispatch(new NotificationPayload(
            recipientId: $pickup->customer_id,
            type: $type,
            title: $title,
            body: $body,
            reference: '/warga/penjemputan/'.$pickup->request_number,
            dedupeKey: NotificationDedupeKey::for($type.':'.$pickup->request_number, $pickup->customer_id, 'pickup-v1'),
        ));
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk tindakan ini.');
        }
    }

    private function businessDate(mixed $value, string $field = 'selected_date'): CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw ValidationException::withMessages([$field => 'Tanggal layanan harus berformat YYYY-MM-DD.']);
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta');
        } catch (Throwable) {
            $date = false;
        }
        $today = CarbonImmutable::today('Asia/Jakarta');
        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value || $date->isBefore($today) || $date->isAfter($today->addDays((int) config('app.pickup_booking_horizon_days', 30)))) {
            throw ValidationException::withMessages([$field => 'Tanggal layanan tidak tersedia: pilih hari ini hingga batas horizon pemesanan.']);
        }

        return $date;
    }

    private function text(mixed $value, string $field, int $min, int $max): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => 'Kolom ini wajib diisi.']);
        }
        $value = trim($value);
        if (mb_strlen($value) < $min || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
            throw ValidationException::withMessages([$field => 'Panjang atau format kolom tidak valid.']);
        }

        return $value;
    }

    private function optionalText(mixed $value, string $field, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->text($value, $field, 1, $max);
    }

    private function requiredReason(?string $reason, string $field): string
    {
        return $this->text($reason, $field, 10, 1000);
    }

    private function isPositiveWeight(string $value): bool
    {
        try {
            Weight::fromDecimal($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function validateIdempotencyKey(string $key): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,190}$/', $key) !== 1) {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key tidak valid.']);
        }
    }

    private function assertSamePayload(IdempotencyKey $key, string $payloadHash): void
    {
        if ($key->payload_hash !== $payloadHash) {
            throw ValidationException::withMessages(['idempotency_key' => 'Permintaan yang sama sudah digunakan untuk data berbeda.']);
        }
        if ($key->result_id === null) {
            throw ValidationException::withMessages(['idempotency_key' => 'Permintaan sebelumnya belum selesai.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     * @param  list<UploadedFile>  $photos
     */
    private function payloadHash(array $data, array $items, array $photos, int $customerId, int $areaId, string $date): string
    {
        return hash('sha256', json_encode([
            'customer_id' => $customerId,
            'service_area_id' => $areaId,
            'address' => trim((string) ($data['address'] ?? '')),
            'selected_date' => $date,
            'notes' => trim((string) ($data['notes'] ?? '')),
            'items' => $items,
            'photos' => array_map(static fn (UploadedFile $photo): array => [hash_file('sha256', $photo->getRealPath())], $photos),
        ], JSON_THROW_ON_ERROR));
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

    private function deleteStoredMedia(Media $media): void
    {
        Storage::disk((string) $media->disk)->delete((string) $media->path);
        if ($media->exists) {
            $media->delete();
        }
    }
}
