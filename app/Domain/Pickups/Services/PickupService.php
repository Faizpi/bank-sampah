<?php

declare(strict_types=1);

namespace App\Domain\Pickups\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Exceptions\PickupCapacityUnavailable;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Platform\Actions\StorePrivateMedia;
use App\Domain\Platform\Models\Media;
use App\Domain\Shared\Weight;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    /** @var list<string> */
    private const RESERVING_STATUSES = [
        'menunggu_pemeriksaan',
        'diterima',
        'dijadwalkan',
        'menuju_lokasi',
        'dijemput',
    ];

    public function __construct(
        private PermissionChecker $permissions,
        private StorePrivateMedia $mediaStore,
        private AuditLogger $auditLogger,
        private DepositService $deposits,
        private VisibleUsers $visibleUsers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     * @param  list<UploadedFile>  $photos
     */
    public function submit(User $actor, array $data, array $items, array $photos, string $idempotencyKey): PickupRequest
    {
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

                $this->lockAndAssertCapacity($area, $selectedDate, $estimatedWeight, null);
                $pickup = PickupRequest::query()->create([
                    'request_number' => $this->number('PUP'),
                    'customer_id' => $customer->id,
                    'rt_id' => $customer->customerProfile?->rt_id,
                    'service_area_id' => $area->id,
                    'address' => $address,
                    'selected_date' => $selectedDate->toDateString(),
                    'estimated_weight_kg' => $estimatedWeight,
                    'notes' => $notes,
                    'status' => PickupStatus::PendingReview,
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

                $this->recordStatus($pickup, $actor, null, PickupStatus::PendingReview, 'Pengajuan dibuat.');
                $this->auditLogger->record($actor, 'pickup.requested', $pickup, [], [
                    'status' => PickupStatus::PendingReview->value,
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

    public function setCapacity(User $actor, ServiceArea $area, string $date, ?int $maxAddresses, ?string $maxWeightKg, ?string $vehicleLabel = null, ?PickupCapacity $current = null): PickupCapacity
    {
        $this->authorize($actor, 'pickup.capacity.manage');
        if (! $area->is_active) {
            throw ValidationException::withMessages(['service_area_id' => 'Area pelayanan tidak aktif.']);
        }
        if (($maxAddresses ?? 0) < 0 || ($maxWeightKg !== null && $maxWeightKg !== '' && ! $this->isNonNegativeWeight($maxWeightKg))) {
            throw ValidationException::withMessages(['capacity' => 'Batas kapasitas tidak valid.']);
        }
        $serviceDate = $this->businessDate($date);
        if ($maxAddresses === null && ($maxWeightKg === null || $maxWeightKg === '')) {
            throw ValidationException::withMessages(['capacity' => 'Minimal satu batas kapasitas harus diisi.']);
        }

        return DB::transaction(function () use ($actor, $area, $serviceDate, $maxAddresses, $maxWeightKg, $vehicleLabel, $current): PickupCapacity {
            $lockedCurrent = $current === null
                ? null
                : PickupCapacity::query()->whereKey($current->id)->lockForUpdate()->firstOrFail();
            $capacity = PickupCapacity::query()->lockForUpdate()->updateOrCreate(
                ['service_area_id' => $area->id, 'service_date' => $serviceDate->toDateString()],
                [
                    'max_addresses' => $maxAddresses,
                    'max_weight_kg' => $maxWeightKg === '' ? null : $maxWeightKg,
                    'vehicle_label' => $this->optionalText($vehicleLabel, 'vehicle_label', 120),
                    'is_active' => true,
                ],
            );
            if ($lockedCurrent !== null && $lockedCurrent->id !== $capacity->id) {
                $lockedCurrent->forceFill(['is_active' => false])->save();
                $this->auditLogger->record($actor, 'pickup.capacity.replaced', $lockedCurrent, [
                    'service_area_id' => $lockedCurrent->service_area_id,
                    'service_date' => $lockedCurrent->service_date->toDateString(),
                    'is_active' => true,
                ], [
                    'is_active' => false,
                    'replacement_id' => $capacity->id,
                ], $this->correlationId());
            }
            $this->auditLogger->record($actor, 'pickup.capacity.updated', $capacity, [], [
                'service_area_id' => $area->id,
                'service_date' => $serviceDate->toDateString(),
                'max_addresses' => $maxAddresses,
                'max_weight_kg' => $maxWeightKg,
            ], $this->correlationId());

            return $capacity->fresh();
        });
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

    /** @return list<string> */
    public function alternatives(ServiceArea $area, string $date, int $limit = 3, ?string $estimatedWeight = null): array
    {
        return $this->findAlternatives($area, CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Jakarta'), $limit, $estimatedWeight);
    }

    /** @return list<string> */
    private function findAlternatives(ServiceArea $area, CarbonImmutable $start, int $limit = 3, ?string $estimatedWeight = null): array
    {
        $alternatives = [];
        $horizonDays = (int) config('app.pickup_booking_horizon_days', 30);
        for ($offset = 0; $offset <= $horizonDays && count($alternatives) < $limit; $offset++) {
            $candidate = $start->addDays($offset);
            $capacity = PickupCapacity::query()->where('service_area_id', $area->id)->whereDate('service_date', $candidate->toDateString())->where('is_active', true)->first();
            if ($capacity === null) {
                continue;
            }
            $reserved = PickupRequest::query()->where('service_area_id', $area->id)->whereIn('status', self::RESERVING_STATUSES)->where(function (Builder $query) use ($candidate): void {
                $query->whereDate('selected_date', $candidate->toDateString())->whereNull('scheduled_date')->orWhereDate('scheduled_date', $candidate->toDateString());
            })->get();
            if ($capacity->max_addresses !== null && $reserved->count() >= $capacity->max_addresses) {
                continue;
            }
            $weightGrams = $reserved->sum(static fn (PickupRequest $request): int => $request->estimated_weight_kg === null ? 0 : Weight::fromDecimal((string) $request->estimated_weight_kg)->grams());
            if ($capacity->max_weight_kg !== null && $estimatedWeight !== null && ($weightGrams + Weight::fromDecimal($estimatedWeight)->grams()) > Weight::fromDecimal((string) $capacity->max_weight_kg)->grams()) {
                continue;
            }
            $alternatives[] = $candidate->toDateString();
        }

        return $alternatives;
    }

    public function review(User $actor, PickupRequest $pickup, bool $accept, ?string $reason = null): PickupRequest
    {
        $this->authorize($actor, 'pickup.review');
        $this->assertRecordScope($actor, $pickup, false);
        if ($accept) {
            return DB::transaction(function () use ($actor, $pickup): PickupRequest {
                $locked = $this->lockPickup($pickup);
                $this->assertTransition($locked, PickupStatus::Accepted);
                $this->lockAndAssertCapacity($locked->serviceArea()->firstOrFail(), $locked->selected_date, $locked->estimated_weight_kg, $locked->id);
                $oldStatus = $locked->status;
                $locked->forceFill(['status' => PickupStatus::Accepted, 'accepted_at' => now()])->save();
                $this->recordStatus($locked, $actor, $oldStatus, PickupStatus::Accepted, 'Pengajuan diterima.');
                $this->auditLogger->record($actor, 'pickup.accepted', $locked, ['status' => $oldStatus->value], ['status' => PickupStatus::Accepted->value], $this->correlationId());
                $this->notifyStatus($locked, 'Penjemputan diterima', 'Pengajuan penjemputan '.$locked->request_number.' telah diterima.', 'pickup.accepted');

                return $locked->fresh(['items', 'media']);
            });
        }

        $reason = $this->requiredReason($reason, 'rejection_reason');

        return DB::transaction(function () use ($actor, $pickup, $reason): PickupRequest {
            $locked = $this->lockPickup($pickup);
            $this->assertTransition($locked, PickupStatus::Rejected);
            $oldStatus = $locked->status;
            $locked->forceFill(['status' => PickupStatus::Rejected, 'rejection_reason' => $reason])->save();
            $this->recordStatus($locked, $actor, $oldStatus, PickupStatus::Rejected, $reason);
            $this->auditLogger->record($actor, 'pickup.rejected', $locked, ['status' => $oldStatus->value], ['status' => PickupStatus::Rejected->value, 'reason' => $reason], $this->correlationId());
            $this->notifyStatus($locked, 'Pengajuan penjemputan ditolak', 'Pengajuan penjemputan '.$locked->request_number.' ditolak.', 'pickup.rejected');

            return $locked->fresh(['items', 'media']);
        });
    }

    public function schedule(User $actor, PickupRequest $pickup, User $staff, ?string $date = null): PickupRequest
    {
        $this->authorize($actor, 'pickup.schedule');
        $this->assertRecordScope($actor, $pickup, false);
        $this->assertActiveAssignedStaff($staff, $pickup);
        $scheduledDate = $date === null ? CarbonImmutable::parse((string) $pickup->selected_date, 'Asia/Jakarta') : $this->businessDate($date, 'scheduled_date');

        return DB::transaction(function () use ($actor, $pickup, $staff, $scheduledDate): PickupRequest {
            $locked = $this->lockPickup($pickup);
            $this->assertTransition($locked, PickupStatus::Scheduled);
            $oldDate = $locked->scheduled_date ?? $locked->selected_date;
            if ($oldDate->toDateString() !== $scheduledDate->toDateString()) {
                $this->lockAndAssertCapacity($locked->serviceArea()->firstOrFail(), $scheduledDate, $locked->estimated_weight_kg, $locked->id);
            }
            $oldStatus = $locked->status;
            $locked->forceFill([
                'status' => PickupStatus::Scheduled,
                'scheduled_date' => $scheduledDate->toDateString(),
                'assigned_staff_id' => $staff->id,
                'scheduled_at' => now(),
            ])->save();
            $this->recordStatus($locked, $actor, $oldStatus, PickupStatus::Scheduled, 'Penjemputan dijadwalkan.');
            $this->auditLogger->record($actor, 'pickup.scheduled', $locked, ['status' => $oldStatus->value], ['status' => PickupStatus::Scheduled->value, 'scheduled_date' => $scheduledDate->toDateString(), 'assigned_staff_id' => $staff->id], $this->correlationId());
            $this->notifyStatus($locked, 'Penjemputan dijadwalkan', 'Penjemputan '.$locked->request_number.' telah dijadwalkan.', 'pickup.scheduled');

            return $locked->fresh(['items', 'media', 'assignedStaff']);
        });
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
        $this->assertCompletionEvidence($pickup, $evidence);
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
                $existingKey = IdempotencyKey::activeForUpdate($actor->id, self::IDEMPOTENCY_COMPLETE_SCOPE, $idempotencyKey);
                if ($existingKey !== null) {
                    $this->assertSamePayload($existingKey, $payloadHash);

                    return PickupRequest::query()->findOrFail($existingKey->result_id);
                }
                $key = IdempotencyKey::query()->create(['actor_id' => $actor->id, 'scope' => self::IDEMPOTENCY_COMPLETE_SCOPE, 'key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'status' => 'processing']);
                $locked = $this->lockPickup($pickup);
                $this->assertTransition($locked, PickupStatus::Completed);
                if ($locked->status !== PickupStatus::PickedUp) {
                    throw ValidationException::withMessages(['status' => 'Penjemputan harus berstatus dijemput sebelum diselesaikan.']);
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
                $locked->forceFill(['status' => PickupStatus::Completed, 'deposit_id' => $deposit->id, 'completed_at' => now()])->save();
                $this->recordStatus($locked, $actor, $oldStatus, PickupStatus::Completed, 'Penimbangan aktual dan setoran final berhasil.');
                $this->auditLogger->record($actor, 'pickup.completed', $locked, ['status' => $oldStatus->value], ['status' => PickupStatus::Completed->value, 'deposit_id' => $deposit->id, 'actual_weight_kg' => $deposit->total_weight_kg], $this->correlationId());
                $key->forceFill(['status' => 'succeeded', 'result_type' => PickupRequest::class, 'result_id' => $locked->id])->save();
                $this->notifyStatus($locked, 'Penjemputan selesai', 'Penjemputan '.$locked->request_number.' selesai dan setoran telah dibuat.', 'pickup.completed');

                return $locked->fresh(['items', 'media', 'deposit']);
            });
        } catch (Throwable $exception) {
            if ($media instanceof Media) {
                $durablePickup = PickupRequest::query()->with('media')->find($pickup->id);
                $durableMedia = Media::query()->find($media->id);
                $isCommittedEvidence = $durablePickup instanceof PickupRequest
                    && $durablePickup->status === PickupStatus::Completed
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
        $this->authorize($actor, 'pickup.cancel');
        if ($pickup->customer_id !== $actor->id && ! $this->canView($actor, $pickup)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk tindakan ini.');
        }
        $reason = $this->optionalText($reason, 'cancellation_reason', 1000);

        return DB::transaction(function () use ($actor, $pickup, $reason): PickupRequest {
            $locked = $this->lockPickup($pickup);
            if ($locked->status === PickupStatus::EnRoute && $locked->customer_id === $actor->id) {
                throw new AuthorizationException('Penjemputan tidak dapat dibatalkan setelah petugas menuju lokasi.');
            }
            if (! $locked->status->canTransitionTo(PickupStatus::Cancelled)) {
                throw ValidationException::withMessages(['status' => 'Status penjemputan tidak dapat dibatalkan.']);
            }
            $oldStatus = $locked->status;
            $locked->forceFill(['status' => PickupStatus::Cancelled, 'cancellation_reason' => $reason])->save();
            $this->recordStatus($locked, $actor, $oldStatus, PickupStatus::Cancelled, $reason);
            $this->auditLogger->record($actor, 'pickup.cancelled', $locked, ['status' => $oldStatus->value], ['status' => PickupStatus::Cancelled->value, 'reason' => $reason], $this->correlationId());
            $this->notifyStatus($locked, 'Penjemputan dibatalkan', 'Penjemputan '.$locked->request_number.' telah dibatalkan.', 'pickup.cancelled');

            return $locked->fresh(['items', 'media']);
        });
    }

    public function expire(PickupRequest $pickup): PickupRequest
    {
        return DB::transaction(function () use ($pickup): PickupRequest {
            $locked = $this->lockPickup($pickup);
            if (! in_array($locked->status, [PickupStatus::PendingReview, PickupStatus::Accepted, PickupStatus::Scheduled], true)) {
                return $locked;
            }
            $oldStatus = $locked->status;
            $reason = 'Pengajuan kedaluwarsa karena tidak diproses dalam batas layanan.';
            $locked->forceFill(['status' => PickupStatus::Cancelled, 'cancellation_reason' => $reason])->save();
            $locked->statusHistory()->create(['old_status' => $oldStatus->value, 'new_status' => PickupStatus::Cancelled->value, 'actor_id' => null, 'reason' => $reason, 'occurred_at' => now()]);
            $this->auditLogger->record(null, 'pickup.expired', $locked, ['status' => $oldStatus->value], ['status' => PickupStatus::Cancelled->value, 'reason' => $reason], $this->correlationId());
            $this->notifyStatus($locked, 'Penjemputan kedaluwarsa', 'Pengajuan penjemputan '.$locked->request_number.' kedaluwarsa dan tidak dilanjutkan.', 'pickup.expired');

            return $locked->fresh(['items', 'media']);
        });
    }

    public function canView(User $actor, PickupRequest $pickup): bool
    {
        if ($pickup->customer_id === $actor->id) {
            return $this->permissions->allows($actor, 'pickup.view')
                || $this->permissions->allows($actor, 'pickup.request')
                || $this->permissions->allows($actor, 'pickup.cancel');
        }
        if (! $this->permissions->allows($actor, 'pickup.view')) {
            return false;
        }
        if ($this->permissions->allows($actor, 'user.view.all')) {
            return true;
        }
        if ($pickup->assigned_staff_id === $actor->id) {
            return true;
        }

        return $this->isStaffInArea($actor, $pickup);
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
        if ($this->permissions->allows($actor, 'pickup.view') && $this->permissions->allows($actor, 'user.view.all')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($actor): void {
            $scope->where('customer_id', $actor->id)->orWhere('assigned_staff_id', $actor->id);
            if ($this->isStaff($actor)) {
                $scope->orWhere(function (Builder $area) use ($actor): void {
                    $area->whereHas('serviceArea', fn (Builder $serviceArea): Builder => $serviceArea->whereHas('staffProfiles', fn (Builder $staff): Builder => $this->activeStaffQuery($staff, $actor)));
                });
            }
        });
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
        if ($customer->id !== $actor->id && ! $this->visibleUsers->canView($actor, $customer)) {
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
            if ($weight === null && $quantity === null) {
                throw ValidationException::withMessages(["items.{$index}" => 'Isi perkiraan berat atau jumlah.']);
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
        if (count($photos) < 1 || count($photos) > 2) {
            throw ValidationException::withMessages(['photos' => 'Unggah minimal satu dan maksimal dua foto.']);
        }
    }

    private function assertCompletionEvidence(PickupRequest $pickup, ?UploadedFile $evidence): void
    {
        if ($evidence === null && ! $pickup->media()->exists()) {
            throw ValidationException::withMessages(['evidence' => 'Tambahkan bukti foto penjemputan melalui kamera atau galeri sebelum menyelesaikan tugas.']);
        }
    }

    private function lockAndAssertCapacity(ServiceArea $area, CarbonImmutable $date, ?string $estimatedWeight, ?int $excludePickupId): PickupCapacity
    {
        $capacity = PickupCapacity::query()->where('service_area_id', $area->id)->whereDate('service_date', $date->toDateString())->where('is_active', true)->lockForUpdate()->first();
        if ($capacity === null) {
            throw new PickupCapacityUnavailable('Tanggal layanan tidak tersedia.', $this->findAlternatives($area, $date, estimatedWeight: $estimatedWeight));
        }
        $reserved = PickupRequest::query()->where('service_area_id', $area->id)->whereIn('status', self::RESERVING_STATUSES)->where(function (Builder $query) use ($date): void {
            $query->whereDate('selected_date', $date->toDateString())->whereNull('scheduled_date')->orWhereDate('scheduled_date', $date->toDateString());
        })->when($excludePickupId !== null, fn (Builder $query): Builder => $query->where('pickup_requests.id', '<>', $excludePickupId))->get();
        $addressCount = $reserved->count();
        $weightGrams = $reserved->sum(static fn (PickupRequest $request): int => $request->estimated_weight_kg === null ? 0 : Weight::fromDecimal((string) $request->estimated_weight_kg)->grams());
        if ($capacity->max_addresses !== null && $addressCount >= $capacity->max_addresses) {
            throw new PickupCapacityUnavailable('Kapasitas alamat untuk tanggal tersebut penuh.', $this->findAlternatives($area, $date, estimatedWeight: $estimatedWeight));
        }
        if ($capacity->max_weight_kg !== null && $estimatedWeight !== null && ($weightGrams + Weight::fromDecimal($estimatedWeight)->grams()) > Weight::fromDecimal((string) $capacity->max_weight_kg)->grams()) {
            throw new PickupCapacityUnavailable('Kapasitas berat untuk tanggal tersebut penuh.', $this->findAlternatives($area, $date, estimatedWeight: $estimatedWeight));
        }

        return $capacity;
    }

    private function transition(User $actor, PickupRequest $pickup, PickupStatus $next, ?string $reason = null, bool $assignedOnly = false): PickupRequest
    {
        return DB::transaction(function () use ($actor, $pickup, $next, $reason, $assignedOnly): PickupRequest {
            $locked = $this->lockPickup($pickup);
            if ($assignedOnly && $locked->assigned_staff_id !== $actor->id) {
                throw new AuthorizationException('Penjemputan tidak ditugaskan kepada Anda.');
            }
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
                ...($next === PickupStatus::Accepted ? ['accepted_at' => now()] : []),
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

    private function assertActiveAssignedStaff(User $staff, PickupRequest $pickup): void
    {
        if ($staff->status !== UserStatus::Active || ! $this->permissions->allows($staff, 'pickup.execute') || ! $this->isStaffInArea($staff, $pickup)) {
            throw ValidationException::withMessages(['assigned_staff_id' => 'Petugas aktif pada area penjemputan wajib dipilih.']);
        }
    }

    private function isStaff(User $actor): bool
    {
        return $actor->staffProfile()->exists();
    }

    private function isStaffInArea(User $actor, PickupRequest $pickup): bool
    {
        $today = today()->toDateString();

        return StaffServiceArea::query()
            ->where('staff_service_areas.staff_profile_user_id', $actor->id)
            ->where('staff_service_areas.service_area_id', $pickup->service_area_id)
            ->where(static fn (Builder $dates): Builder => $dates->whereNull('staff_service_areas.active_from')->orWhereDate('staff_service_areas.active_from', '<=', $today))
            ->where(static fn (Builder $dates): Builder => $dates->whereNull('staff_service_areas.active_to')->orWhereDate('staff_service_areas.active_to', '>=', $today))
            ->whereHas('serviceArea', static fn (Builder $area): Builder => $area->where('service_areas.is_active', true))
            ->exists();
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function activeStaffQuery(Builder $query, User $actor): Builder
    {
        $today = today()->toDateString();

        return $query->where('user_id', $actor->id)->where(fn (Builder $dates): Builder => $dates->whereNull('active_from')->orWhere('active_from', '<=', $today))->where(fn (Builder $dates): Builder => $dates->whereNull('active_to')->orWhere('active_to', '>=', $today));
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
            reference: '/warga/penjemputan/'.$pickup->id,
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

    private function isNonNegativeWeight(string $value): bool
    {
        return $value === '0' || $this->isPositiveWeight($value);
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
            'photos' => array_map(static fn (UploadedFile $photo): array => [$photo->getClientOriginalName(), $photo->getSize(), $photo->getMimeType()], $photos),
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
