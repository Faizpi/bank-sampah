<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Support\GroceryRedemptionScope;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class GroceryDecisionService
{
    public function __construct(
        private PermissionChecker $permissions,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
        private GroceryRedemptionScope $scope,
    ) {}

    public function approve(User $actor, GroceryRedemption $redemption, bool $approved, ?string $availabilityNote = null, ?string $reason = null): GroceryRedemption
    {
        $this->authorize($actor, 'grocery.approve');
        $this->assertScope($actor, $redemption);
        if ($redemption->requested_by_id === $actor->id || $redemption->customer_id === $actor->id) {
            throw new AuthorizationException('Pengaju atau penerima tidak dapat menyetujui penukaran yang sama.');
        }
        $availabilityNote = $approved ? $this->requiredText($availabilityNote, 'availability_note', 3, 1000) : null;
        $reason = $approved ? null : $this->requiredText($reason, 'reason', 10, 1000);

        return DB::transaction(function () use ($actor, $redemption, $approved, $availabilityNote, $reason): GroceryRedemption {
            $locked = $this->lock($redemption);
            $this->assertScope($actor, $locked);
            $next = $approved ? GroceryStatus::Approved : GroceryStatus::Rejected;
            if (! $approved && $locked->status === GroceryStatus::Rejected) {
                return $locked->fresh(['package', 'balanceHold', 'customer', 'approver']);
            }
            $this->assertTransition($locked, $next);
            $old = $locked->status;
            $locked->forceFill([
                'status' => $next,
                'approver_id' => $actor->id,
                'availability_note' => $availabilityNote,
                'rejection_reason' => $reason,
                'approved_at' => $approved ? now() : null,
                'expires_at' => $approved ? now()->addDays((int) config('app.grocery_expiry_days', 7)) : null,
            ])->save();
            if (! $approved) {
                $this->releaseHold($locked);
            }
            $locked->statusHistory()->create(['old_status' => $old->value, 'new_status' => $next->value, 'actor_id' => $actor->id, 'reason' => $reason ?? $availabilityNote, 'occurred_at' => now()]);
            $this->auditLogger->record($actor, $approved ? 'grocery.approved' : 'grocery.rejected', $locked, ['status' => $old->value], ['status' => $next->value, 'reason' => $reason, 'availability_note' => $availabilityNote], $this->correlationId());
            $this->notify($locked, $approved ? 'Penukaran sembako disetujui' : 'Penukaran sembako ditolak', $approved ? 'Pengajuan '.$locked->request_number.' disetujui.' : 'Pengajuan '.$locked->request_number.' ditolak.', $approved ? 'grocery.approved' : 'grocery.rejected');

            return $locked->fresh(['package', 'balanceHold', 'customer', 'approver']);
        });
    }

    public function prepare(User $actor, GroceryRedemption $redemption): GroceryRedemption
    {
        return $this->advanceStaffStatus($actor, $redemption, GroceryStatus::Preparing, 'grocery.prepare', 'grocery.prepared', 'Paket mulai disiapkan.');
    }

    public function ready(User $actor, GroceryRedemption $redemption): GroceryRedemption
    {
        return $this->advanceStaffStatus($actor, $redemption, GroceryStatus::ReadyForPickup, 'grocery.prepare', 'grocery.ready', 'Paket siap diambil.');
    }

    private function advanceStaffStatus(User $actor, GroceryRedemption $redemption, GroceryStatus $next, string $permission, string $event, string $reason): GroceryRedemption
    {
        $this->authorize($actor, $permission);
        $this->assertScope($actor, $redemption);

        return DB::transaction(function () use ($actor, $redemption, $next, $event, $reason): GroceryRedemption {
            $locked = $this->lock($redemption);
            $this->assertScope($actor, $locked);
            $this->assertTransition($locked, $next);
            $old = $locked->status;
            $locked->forceFill([
                'status' => $next,
                'prepared_by_id' => $next === GroceryStatus::Preparing ? $actor->id : $locked->prepared_by_id,
                'prepared_at' => $next === GroceryStatus::Preparing ? now() : $locked->prepared_at,
                'ready_at' => $next === GroceryStatus::ReadyForPickup ? now() : $locked->ready_at,
            ])->save();
            $locked->statusHistory()->create(['old_status' => $old->value, 'new_status' => $next->value, 'actor_id' => $actor->id, 'reason' => $reason, 'occurred_at' => now()]);
            $this->auditLogger->record($actor, $event, $locked, ['status' => $old->value], ['status' => $next->value], $this->correlationId());
            $message = $next === GroceryStatus::ReadyForPickup
                ? 'Penukaran '.$locked->request_number.' siap diambil. Bawa kartu nasabah atau siapkan nomor nasabah Anda, lalu tunggu petugas melakukan serah-terima paket.'
                : 'Penukaran '.$locked->request_number.' '.$reason;
            $this->notify($locked, 'Status penukaran diperbarui', $message, $event);

            return $locked->fresh(['package', 'balanceHold', 'customer', 'preparedBy']);
        });
    }

    private function assertScope(User $actor, GroceryRedemption $redemption): void
    {
        if (! $this->scope->canOperate($actor, $redemption)) {
            throw new AuthorizationException('Penukaran berada di luar scope area snapshot Anda.');
        }
    }

    private function releaseHold(GroceryRedemption $redemption): void
    {
        $hold = $redemption->balanceHold()->first();
        if ($hold instanceof BalanceHold) {
            $this->ledger->releaseHold($hold);
        }
    }

    private function lock(GroceryRedemption $redemption): GroceryRedemption
    {
        return GroceryRedemption::query()->whereKey($redemption->id)->lockForUpdate()->firstOrFail();
    }

    private function assertTransition(GroceryRedemption $redemption, GroceryStatus $next): void
    {
        if (! $redemption->canTransitionTo($next)) {
            throw ValidationException::withMessages(['status' => 'Transisi status penukaran sembako tidak valid.']);
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk keputusan penukaran sembako.');
        }
    }

    private function requiredText(?string $value, string $field, int $min, int $max): string
    {
        $text = trim((string) $value);
        if (mb_strlen($text) < $min || mb_strlen($text) > $max) {
            throw ValidationException::withMessages([$field => 'Nilai wajib memenuhi panjang yang diizinkan.']);
        }

        return $text;
    }

    private function notify(GroceryRedemption $redemption, string $title, string $body, string $type): void
    {
        NotificationRequested::dispatch(new NotificationPayload(recipientId: $redemption->customer_id, type: $type, title: $title, body: $body, reference: '/notifikasi', dedupeKey: NotificationDedupeKey::for($type.':'.$redemption->request_number, $redemption->customer_id, 'grocery-v1')));
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
