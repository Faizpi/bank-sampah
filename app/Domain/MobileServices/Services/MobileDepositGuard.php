<?php

declare(strict_types=1);

namespace App\Domain\MobileServices\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class MobileDepositGuard
{
    public function __construct(private PermissionChecker $permissions, private MobileServiceService $services, private AuditLogger $auditLogger) {}

    /** @param list<WasteType> $types */
    public function attach(User $actor, Deposit $deposit, MobileService $service, array $types): Deposit
    {
        return $this->attachLocked($actor, $deposit, $service, $types, false);
    }

    /** @param list<WasteType> $types */
    public function attachForFinalization(User $actor, Deposit $deposit, MobileService $service, array $types): Deposit
    {
        return $this->attachLocked($actor, $deposit, $service, $types, true);
    }

    /** @param list<WasteType> $types */
    private function attachLocked(User $actor, Deposit $deposit, MobileService $service, array $types, bool $countLinkedDraft): Deposit
    {
        if (! $this->permissions->allows($actor, 'deposit.create') || $deposit->staff_id !== $actor->id || $deposit->method !== 'keliling') {
            throw new AuthorizationException('Setoran keliling berada di luar scope petugas.');
        }

        return DB::transaction(function () use ($actor, $deposit, $service, $types, $countLinkedDraft): Deposit {
            $lockedDeposit = Deposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($lockedDeposit->mobile_service_id !== null && $lockedDeposit->mobile_service_id !== $service->id) {
                throw ValidationException::withMessages(['mobile_service' => 'Setoran sudah terhubung ke layanan keliling lain.']);
            }
            if ($lockedDeposit->mobile_service_id === $service->id && $lockedDeposit->isFinal()) {
                return $lockedDeposit->fresh('mobileService');
            }
            $locked = MobileService::query()->lockForUpdate()->findOrFail($service->id);
            $wasUnlinked = $lockedDeposit->mobile_service_id === null;
            $wasCounted = AuditLog::query()
                ->where('action', 'mobile-service.deposit-linked')
                ->where('auditable_type', Deposit::class)
                ->where('auditable_id', $lockedDeposit->id)
                ->where('new_values->mobile_service_id', $locked->id)
                ->exists();
            foreach ($types as $type) {
                if ((! $wasCounted && ! $this->services->canAcceptDeposit($actor, $locked, $type->id))
                    || ($wasCounted && (! $locked->isOpen() || ! $this->services->canOperate($actor, $locked) || ! $locked->wasteTypes()->whereKey($type->id)->exists()))) {
                    throw ValidationException::withMessages(['mobile_service' => 'Layanan keliling belum dibuka, petugas tidak ditugaskan, jenis tidak diterima, atau kapasitas penuh.']);
                }
            }

            if ($wasUnlinked) {
                $lockedDeposit->forceFill(['mobile_service_id' => $locked->id])->save();
            }
            if (! $wasCounted && ($wasUnlinked || $countLinkedDraft)) {
                $locked->increment('served_count');
                $this->auditLogger->record($actor, 'mobile-service.deposit-linked', $lockedDeposit, [], ['mobile_service_id' => $locked->id], (string) Str::uuid());
            }

            return $lockedDeposit->fresh('mobileService');
        });
    }
}
