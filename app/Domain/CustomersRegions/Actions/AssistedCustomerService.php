<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Contracts\AssistedServiceContract;
use App\Domain\CustomersRegions\Contracts\AssistedServiceRecord;
use App\Domain\CustomersRegions\Models\AssistedCustomerService as AssistedCustomerServiceModel;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Platform\Enums\MediaVisibility;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssistedCustomerService
{
    public function __construct(
        private PermissionChecker $permissions,
        private VisibleUsers $visibleUsers,
    ) {}

    public function record(User $operator, User $owner, AssistedServiceContract $contract): AssistedServiceRecord
    {
        $this->authorizeOperator($operator, $owner, $contract);

        return DB::transaction(function () use ($operator, $owner, $contract): AssistedServiceRecord {
            $lockedOwner = User::query()->lockForUpdate()->with('customerProfile')->findOrFail($owner->getKey());

            if ($lockedOwner->status !== UserStatus::Active || ! $lockedOwner->customerProfile instanceof CustomerProfile) {
                throw ValidationException::withMessages(['owner' => 'Warga harus aktif dan memiliki profil nasabah.']);
            }

            $evidence = Media::query()
                ->whereKey($contract->evidence->mediaId)
                ->where('visibility', MediaVisibility::Private->value)
                ->where('uploader_id', $operator->getKey())
                ->first();

            if (! $evidence instanceof Media) {
                throw ValidationException::withMessages(['evidence' => 'Bukti privat yang diunggah operator wajib tersedia.']);
            }

            $record = AssistedCustomerServiceModel::query()->create([
                'owner_id' => $lockedOwner->getKey(),
                'operator_id' => $operator->getKey(),
                'service_type' => $contract->serviceType,
                'consent_version' => $contract->consent->version,
                'consented_at' => $contract->consent->givenAt,
                'evidence_media_id' => $evidence->getKey(),
            ]);

            return new AssistedServiceRecord(
                $record->getKey(),
                $record->owner_id,
                $record->operator_id,
                $record->service_type,
                $record->consent_version,
                $contract->consent->givenAt,
                $record->evidence_media_id,
            );
        });
    }

    private function authorizeOperator(User $operator, User $owner, AssistedServiceContract $contract): void
    {
        if ($contract->ownerId !== $owner->getKey() || $contract->operatorId !== $operator->getKey()) {
            throw new AuthorizationException('Pemilik dan pelaksana layanan tidak cocok dengan perintah.');
        }

        if ($operator->is($owner)) {
            throw new AuthorizationException('Layanan berbantuan tidak dapat dijalankan untuk diri sendiri.');
        }

        if (! $this->permissions->allows($operator, 'customer.create-assisted') || ! $this->permissions->allows($operator, 'customer.view')) {
            throw new AuthorizationException('Operator tidak memiliki akses layanan berbantuan.');
        }

        if (! ($this->permissions->allows($operator, 'user.view') && $this->permissions->allows($operator, 'user.view.all')) && ! $this->visibleUsers->canView($operator, $owner)) {
            throw new AuthorizationException('Warga berada di luar scope operator.');
        }
    }
}
