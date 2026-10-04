<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Contracts\AssistedServiceContract;
use App\Domain\CustomersRegions\Contracts\AssistedServiceHandoff;
use App\Domain\CustomersRegions\Contracts\AssistedServiceRecord;
use App\Domain\CustomersRegions\Models\AssistedCustomerService as AssistedCustomerServiceModel;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositPublicPresenter;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Platform\Enums\MediaVisibility;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AssistedCustomerService
{
    public function __construct(
        private PermissionChecker $permissions,
        private VisibleUsers $visibleUsers,
        private AuditLogger $auditLogger,
        private DepositPublicPresenter $receipts,
        private LedgerService $ledger,
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
            $this->auditLogger->record($operator, 'assisted-service.recorded', $record, [], ['owner_id' => $record->owner_id, 'operator_id' => $record->operator_id, 'service_type' => $record->service_type], $this->correlationId());

            return new AssistedServiceRecord(
                $record->getKey(),
                $record->owner_id,
                $record->operator_id,
                $record->service_type,
                $record->consent_version,
                $contract->consent->givenAt,
                $record->evidence_media_id,
                $record->deposit_id === null ? null : (int) $record->deposit_id,
            );
        });
    }

    public function linkDeposit(User $operator, int $serviceId, Deposit $deposit): AssistedServiceRecord
    {
        return DB::transaction(function () use ($operator, $serviceId, $deposit): AssistedServiceRecord {
            $record = $this->lockForDepositLink($operator, $serviceId);

            return $this->linkDepositInTransaction($operator, $record, $deposit);
        });
    }

    public function lockForDepositLink(User $operator, int $serviceId): AssistedCustomerServiceModel
    {
        $record = AssistedCustomerServiceModel::query()->with('owner')->lockForUpdate()->findOrFail($serviceId);
        $this->assertCanLinkDeposit($operator, $record);

        return $record;
    }

    public function linkDepositInTransaction(User $operator, AssistedCustomerServiceModel $record, Deposit $deposit): AssistedServiceRecord
    {
        $this->assertCanLinkDeposit($operator, $record);
        $lockedDeposit = Deposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
        if ($lockedDeposit->customer_id !== $record->owner_id || $lockedDeposit->staff_id !== $operator->id || ! $lockedDeposit->isFinal()) {
            throw ValidationException::withMessages(['deposit' => 'Bukti final harus milik warga dan dicatat oleh operator.']);
        }
        $linkedDepositId = $record->deposit_id === null ? null : (int) $record->deposit_id;
        if ($linkedDepositId !== null && $linkedDepositId !== $lockedDeposit->id) {
            throw ValidationException::withMessages(['deposit' => 'Layanan berbantuan sudah memiliki bukti transaksi.']);
        }
        if ($linkedDepositId === $lockedDeposit->id) {
            return $this->toRecord($record);
        }

        $record->forceFill(['deposit_id' => $lockedDeposit->id])->save();
        $this->auditLogger->record($operator, 'assisted-service.deposit-linked', $record, [], ['deposit_id' => $lockedDeposit->id], $this->correlationId());

        return $this->toRecord($record);
    }

    public function handoff(User $operator, int $serviceId): AssistedServiceHandoff
    {
        $this->authorizeOperatorForRecord($operator, $serviceId);
        $record = AssistedCustomerServiceModel::query()->with('deposit', 'owner')->findOrFail($serviceId);
        if (! $record->deposit instanceof Deposit) {
            throw ValidationException::withMessages(['deposit' => 'Layanan berbantuan belum memiliki bukti setoran final.']);
        }

        return new AssistedServiceHandoff($record->owner_id, $record->operator_id, $record->deposit->id, $this->receipts->present($record->deposit), $this->ledger->balanceFor($record->owner));
    }

    private function authorizeOperatorForRecord(User $operator, int $serviceId): void
    {
        $record = AssistedCustomerServiceModel::query()->with('owner')->findOrFail($serviceId);
        $this->assertCanLinkDeposit($operator, $record);
    }

    private function assertCanLinkDeposit(User $operator, AssistedCustomerServiceModel $record): void
    {
        if ($record->operator_id !== $operator->id || ! $this->permissions->allows($operator, 'customer.create-assisted') || ! $this->permissions->allows($operator, 'customer.view')) {
            throw new AuthorizationException('Layanan berbantuan berada di luar scope operator.');
        }
        if (! $this->permissions->allows($operator, 'user.view.all') && ! $this->visibleUsers->canView($operator, $record->owner)) {
            throw new AuthorizationException('Warga berada di luar scope operator.');
        }
    }

    private function toRecord(AssistedCustomerServiceModel $record): AssistedServiceRecord
    {
        return new AssistedServiceRecord($record->id, $record->owner_id, $record->operator_id, $record->service_type, $record->consent_version, new \DateTimeImmutable((string) $record->consented_at), $record->evidence_media_id, $record->deposit_id === null ? null : (int) $record->deposit_id);
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
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
