<?php

declare(strict_types=1);

namespace App\Domain\Withdrawals\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class WithdrawalDecisionService
{
    public function __construct(
        private PermissionChecker $permissions,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
        private VisibleUsers $visibleUsers,
    ) {}

    public function approve(User $actor, WithdrawalRequest $withdrawal, bool $approved, ?string $reason = null, ?string $location = null, ?string $date = null): WithdrawalRequest
    {
        $this->authorize($actor, 'withdrawal.approve');
        $this->assertApprovalScope($actor, $withdrawal);
        if ($withdrawal->requested_by_id === $actor->id || $withdrawal->customer_id === $actor->id) {
            throw new AuthorizationException('Pengaju tidak dapat menyetujui pencairannya sendiri.');
        }
        $reason = $approved ? null : $this->requiredReason($reason);

        return DB::transaction(function () use ($actor, $withdrawal, $approved, $reason, $location, $date): WithdrawalRequest {
            $locked = $this->lockWithdrawal($withdrawal);
            $next = $approved ? WithdrawalStatus::Approved : WithdrawalStatus::Rejected;
            $this->assertTransition($locked, $next);
            $old = $locked->status;
            if ($approved) {
                $pickupDate = $date === null ? null : $this->date($date);
                $locked->forceFill([
                    'status' => $next,
                    'approver_id' => $actor->id,
                    'approved_at' => now(),
                    'expires_at' => now()->addDays((int) config('app.withdrawal_expiry_days', 7)),
                    'pickup_location' => $location === null ? $locked->pickup_location : $this->text($location, 'pickup_location', 3, 255),
                    'pickup_date' => $pickupDate?->toDateString() ?? $locked->pickup_date,
                ])->save();
            } else {
                $locked->forceFill(['status' => $next, 'approver_id' => $actor->id, 'rejection_reason' => $reason])->save();
                $this->releaseHold($locked);
            }
            $locked->statusHistory()->create(['old_status' => $old->value, 'new_status' => $next->value, 'actor_id' => $actor->id, 'reason' => $reason ?? 'Pengajuan disetujui.', 'occurred_at' => now()]);
            $this->auditLogger->record($actor, $approved ? 'withdrawal.approved' : 'withdrawal.rejected', $locked, ['status' => $old->value], ['status' => $next->value, 'reason' => $reason], $this->correlationId());
            $this->notify($locked, $approved ? 'Pencairan disetujui' : 'Pencairan ditolak', $approved ? 'Pengajuan '.$locked->request_number.' disetujui.' : 'Pengajuan '.$locked->request_number.' ditolak.', $approved ? 'withdrawal.approved' : 'withdrawal.rejected');

            return $locked->fresh(['balanceHold', 'customer', 'approver']);
        });
    }

    public function assignPayer(User $actor, WithdrawalRequest $withdrawal, User $payer): WithdrawalRequest
    {
        $this->authorize($actor, 'withdrawal.approve');
        $this->assertApprovalScope($actor, $withdrawal);
        if ($withdrawal->status !== WithdrawalStatus::Approved) {
            throw ValidationException::withMessages(['status' => 'Payer hanya dapat ditetapkan pada pencairan yang disetujui.']);
        }
        if ($withdrawal->approver_id === $payer->id) {
            throw new AuthorizationException('Separation of duties memerlukan payer berbeda dari approver.');
        }
        if ($payer->status !== UserStatus::Active || ! $payer->roles()->where('name', 'bendahara')->exists() || ! $this->permissions->allows($payer, 'withdrawal.pay') || ! $this->isStaffInArea($payer, $withdrawal)) {
            throw ValidationException::withMessages(['payer_id' => 'Payer aktif dengan permission dan area yang sesuai wajib dipilih.']);
        }

        return DB::transaction(function () use ($actor, $withdrawal, $payer): WithdrawalRequest {
            $locked = $this->lockWithdrawal($withdrawal);
            $this->assertTransition($locked, WithdrawalStatus::ReadyForPickup);
            $old = $locked->status;
            $locked->forceFill(['status' => WithdrawalStatus::ReadyForPickup, 'payer_id' => $payer->id])->save();
            $locked->statusHistory()->create(['old_status' => $old->value, 'new_status' => WithdrawalStatus::ReadyForPickup->value, 'actor_id' => $actor->id, 'reason' => 'Payer ditetapkan.', 'occurred_at' => now()]);
            $this->auditLogger->record($actor, 'withdrawal.payer.assigned', $locked, ['status' => $old->value], ['status' => WithdrawalStatus::ReadyForPickup->value, 'payer_id' => $payer->id], $this->correlationId());
            $this->notify($locked, 'Pencairan siap diambil', 'Payer telah ditetapkan untuk '.$locked->request_number.'.', 'withdrawal.ready');

            return $locked->fresh(['balanceHold', 'customer', 'payer']);
        });
    }

    private function assertApprovalScope(User $actor, WithdrawalRequest $withdrawal): void
    {
        if (! $this->permissions->allows($actor, 'withdrawal.approve') || ! ($this->permissions->allows($actor, 'user.view.all') || $this->visibleUsers->queryFor($actor)->whereKey($withdrawal->customer_id)->exists())) {
            throw new AuthorizationException('Pencairan berada di luar scope persetujuan Anda.');
        }
    }

    private function isStaffInArea(User $payer, WithdrawalRequest $withdrawal): bool
    {
        $profile = $payer->staffProfile;
        $customerProfile = $withdrawal->customerProfile()->with('rt')->first();
        if ($profile === null || $profile->service_area_id === null || $customerProfile === null || $customerProfile->rt === null) {
            return false;
        }
        $today = today()->toDateString();

        return $customerProfile->rt->serviceAreas()->where('is_active', true)->whereKey($profile->service_area_id)->exists()
            && ($profile->active_from === null || $profile->active_from->toDateString() <= $today)
            && ($profile->active_to === null || $profile->active_to->toDateString() >= $today);
    }

    private function releaseHold(WithdrawalRequest $withdrawal): void
    {
        $hold = $withdrawal->balanceHold()->first();
        if ($hold instanceof BalanceHold) {
            $this->ledger->releaseHold($hold);
        }
    }

    private function lockWithdrawal(WithdrawalRequest $withdrawal): WithdrawalRequest
    {
        return WithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
    }

    private function assertTransition(WithdrawalRequest $withdrawal, WithdrawalStatus $next): void
    {
        if (! $withdrawal->canTransitionTo($next)) {
            throw ValidationException::withMessages(['status' => 'Transisi status pencairan tidak valid.']);
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk tindakan ini.');
        }
    }

    private function requiredReason(?string $reason): string
    {
        $value = trim((string) $reason);
        if (mb_strlen($value) < 10 || mb_strlen($value) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Alasan wajib 10–1000 karakter.']);
        }

        return $value;
    }

    private function text(mixed $value, string $field, int $min, int $max): string
    {
        $text = trim((string) $value);
        if (mb_strlen($text) < $min || mb_strlen($text) > $max) {
            throw ValidationException::withMessages([$field => 'Nilai tidak memenuhi panjang yang diizinkan.']);
        }

        return $text;
    }

    private function date(mixed $value): CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw ValidationException::withMessages(['pickup_date' => 'Tanggal harus berformat YYYY-MM-DD.']);
        }
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta');
        if ($date === null || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages(['pickup_date' => 'Tanggal tidak valid.']);
        }

        return $date;
    }

    private function notify(WithdrawalRequest $withdrawal, string $title, string $body, string $type): void
    {
        NotificationRequested::dispatch(new NotificationPayload(
            recipientId: $withdrawal->customer_id,
            type: $type,
            title: $title,
            body: $body,
            reference: '/notifikasi',
            dedupeKey: NotificationDedupeKey::for($type.':'.$withdrawal->request_number, $withdrawal->customer_id, 'withdrawal-v1'),
        ));
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
