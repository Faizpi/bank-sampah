<?php

declare(strict_types=1);

namespace App\Domain\MobileServices\Services;

use App\Authorization\PermissionChecker;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class MobileDepositGuard
{
    public function __construct(private PermissionChecker $permissions, private MobileServiceService $services) {}

    public function attach(User $actor, Deposit $deposit, MobileService $service, WasteType $type): Deposit
    {
        if (! $this->permissions->allows($actor, 'deposit.create') || $deposit->staff_id !== $actor->id || $deposit->method !== 'keliling') {
            throw new AuthorizationException('Setoran keliling berada di luar scope petugas.');
        }

        return DB::transaction(function () use ($actor, $deposit, $service, $type): Deposit {
            $locked = MobileService::query()->lockForUpdate()->findOrFail($service->id);
            if (! $this->services->canAcceptDeposit($actor, $locked, $type->id)) {
                throw ValidationException::withMessages(['mobile_service' => 'Layanan keliling belum dibuka, petugas tidak ditugaskan, jenis tidak diterima, atau kapasitas penuh.']);
            }
            $deposit->forceFill(['mobile_service_id' => $locked->id])->save();
            $locked->increment('served_count');

            return $deposit->fresh('mobileService');
        });
    }
}
