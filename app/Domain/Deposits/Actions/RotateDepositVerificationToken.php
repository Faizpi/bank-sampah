<?php

declare(strict_types=1);

namespace App\Domain\Deposits\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RotateDepositVerificationToken
{
    public function __construct(
        private PermissionChecker $permissions,
        private VisibleUsers $visibleUsers,
        private AuditLogger $auditLogger,
    ) {}

    public function canRotate(User $actor, Deposit $deposit): bool
    {
        return $this->permissions->allows($actor, 'qr-verification.rotate')
            && $this->permissions->allows($actor, 'deposit.view')
            && $deposit->isFinal()
            && $deposit->status !== Deposit::STATUS_REVERSED
            && ($this->permissions->allows($actor, 'user.view.all') || $this->visibleUsers->canView($actor, $deposit->customer, ...UserStatus::cases()));
    }

    public function handle(User $actor, Deposit $deposit, string $reason): Deposit
    {
        if (! $this->permissions->allows($actor, 'qr-verification.rotate')) {
            throw new AuthorizationException('Anda tidak memiliki akses merotasi QR verifikasi.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Alasan rotasi harus 10–1000 karakter.']);
        }

        return DB::transaction(function () use ($actor, $deposit, $reason): Deposit {
            $locked = Deposit::query()->with('customer')->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if (! $this->canRotate($actor, $locked)) {
                throw new AuthorizationException('Setoran berada di luar scope rotasi QR Anda.');
            }
            if ($locked->verificationToken() === null) {
                throw ValidationException::withMessages(['verification' => 'Setoran belum memiliki QR verifikasi aktif.']);
            }

            $locked->rotateVerificationToken(QrToken::generate());
            $this->auditLogger->record(
                $actor,
                'deposit.verification_qr_rotated',
                $locked,
                [],
                ['deposit_id' => $locked->id, 'reason' => $reason],
                $this->correlationId(),
            );

            return $locked->fresh(['customer']);
        });
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
