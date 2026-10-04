<?php

declare(strict_types=1);

namespace App\Domain\MobileServices\Services;

use App\Authorization\PermissionChecker;
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
        if (! $this->permissions->allows($actor, 'deposit.create') || $deposit->staff_id !== $actor->id || $deposit->method !== 'keliling') {
            throw new AuthorizationException('Setoran keliling berada di luar scope petugas.');
        }

        return DB::transaction(function () use ($actor, $deposit, $service, $types): Deposit {
            $lockedDeposit = Deposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($lockedDeposit->mobile_service_id !== null && $lockedDeposit->mobile_service_id !== $service->id) {
                throw ValidationException::withMessages(['mobile_service' => 'Setoran sudah terhubung ke layanan keliling lain.']);
            }
            if ($lockedDeposit->mobile_service_id === $service->id && $lockedDeposit->isFinal()) {
                return $lockedDeposit->fresh('mobileService');
            }
            $locked = MobileService::query()->lockForUpdate()->findOrFail($service->id);
            foreach ($types as $type) {
                if (! $this->services->canAcceptDeposit($actor, $locked, $type->id)) {
                    throw ValidationException::withMessages(['mobile_service' => 'Layanan keliling belum dibuka, petugas tidak ditugaskan, jenis tidak diterima, atau kapasitas penuh.']);
                }
            }
            if ($lockedDeposit->mobile_service_id === null) {
                $lockedDeposit->forceFill(['mobile_service_id' => $locked->id])->save();
                $locked->increment('served_count');
                $this->auditLogger->record($actor, 'mobile-service.deposit-linked', $lockedDeposit, [], ['mobile_service_id' => $locked->id], (string) Str::uuid());
            }

            return $lockedDeposit->fresh('mobileService');
        });
    }
}
