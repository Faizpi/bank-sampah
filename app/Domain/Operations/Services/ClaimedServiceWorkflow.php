<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Platform\Actions\StorePrivateMedia;
use App\Domain\Platform\Models\Media;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use App\Support\Auth\AdminReauthentication;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ClaimedServiceWorkflow
{
    public function __construct(private ServiceTaskGuard $guard, private LedgerService $ledger, private AuditLogger $audit) {}

    /**
     * @template T of WithdrawalRequest|GroceryRedemption
     *
     * @param  T  $request
     * @return T
     */
    public function claim(User $actor, WithdrawalRequest|GroceryRedemption $request): WithdrawalRequest|GroceryRedemption
    {
        return DB::transaction(function () use ($actor, $request) {
            $locked = $request->newQuery()->lockForUpdate()->findOrFail($request->id);
            $this->lockFinancialParties($actor, $locked);
            $this->guard->check($actor, $this->role($locked), $locked->service_area_id);
            $this->ensureNoException($locked);
            $field = $this->claimantField($locked);
            if ($locked->{$field} === $actor->id && $locked->status === $this->processing($locked)) {
                return $locked;
            }
            $this->ensure($locked->status === $this->waiting($locked) && $locked->{$field} === null, 'Tugas sudah diambil atau tidak lagi menunggu.');
            $old = $locked->status->value;
            $locked->forceFill(['status' => $this->processing($locked), $field => $actor->id, 'claimed_at' => now(), 'claim_released_at' => null])->save();
            $this->record($actor, $locked, $old, 'claimed', 'Tugas diambil setelah uang/paket fisik tersedia.');

            return $locked;
        }, 5);
    }

    /**
     * @template T of WithdrawalRequest|GroceryRedemption
     *
     * @param  T  $request
     * @return T
     */
    public function release(User $actor, WithdrawalRequest|GroceryRedemption $request, string $reason): WithdrawalRequest|GroceryRedemption
    {
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($actor, $request, $reason) {
            $locked = $request->newQuery()->lockForUpdate()->findOrFail($request->id);
            $this->assertClaimant($actor, $locked);
            $this->ensureNoException($locked);
            $old = $locked->status->value;
            $oldClaimant = $locked->{$this->claimantField($locked)};
            $this->requeue($locked);
            $this->record($actor, $locked, $old, 'released', $reason.' Uang/paket belum diserahkan; saldo tetap ditahan.', $oldClaimant);

            return $locked;
        }, 5);
    }

    public function ready(User $actor, GroceryRedemption $request): GroceryRedemption
    {
        return DB::transaction(function () use ($actor, $request): GroceryRedemption {
            $locked = GroceryRedemption::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertClaimant($actor, $locked);
            $this->ensureNoException($locked);
            if ($locked->status === GroceryStatus::ReadyForPickup) {
                return $locked;
            }
            $old = $locked->status->value;
            $locked->forceFill(['status' => GroceryStatus::ReadyForPickup, 'ready_at' => now()])->save();
            $this->record($actor, $locked, $old, 'ready', 'Paket siap diambil.');

            return $locked;
        }, 5);
    }

    /**
     * @template T of WithdrawalRequest|GroceryRedemption
     *
     * @param  T  $request
     * @return T
     */
    public function cancel(User $actor, WithdrawalRequest|GroceryRedemption $request, string $reason, bool $unavailable = false): WithdrawalRequest|GroceryRedemption
    {
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($actor, $request, $reason, $unavailable) {
            $locked = $request->newQuery()->lockForUpdate()->findOrFail($request->id);
            $current = User::query()->lockForUpdate()->findOrFail($actor->id);
            User::query()->whereKey($locked->customer_id)->lockForUpdate()->firstOrFail();
            $isOwner = $current->role === UserRole::Citizen && $current->id === $locked->customer_id && $current->status === UserStatus::Active;
            if ($unavailable) {
                $this->ensure($locked instanceof GroceryRedemption, 'Tindakan hanya untuk sembako.');
                $this->guard->check($current, UserRole::Officer, $locked->service_area_id);
                $next = GroceryStatus::Unavailable;
            } else {
                if (! $isOwner) {
                    $this->assertClaimant($current, $locked);
                }
                $next = $locked instanceof WithdrawalRequest ? WithdrawalStatus::Cancelled : GroceryStatus::Cancelled;
            }
            if ($locked->status === $next) {
                return $locked;
            }
            if ($isOwner && $locked->getAttribute('cancellation_requested_at') !== null) {
                return $locked;
            }
            $claimantConfirmsCancellation = ! $isOwner && $locked->getAttribute('cancellation_requested_at') !== null
                && $locked->getAttribute('exception_kind') === 'cancellation_request' && $locked->exception_resolved_at === null;
            if (! $claimantConfirmsCancellation) {
                $this->ensureNoException($locked);
            }
            $this->ensure(! $locked->status->isTerminal(), 'Transaksi sudah selesai dan tidak dapat dibatalkan.');
            if ($unavailable) {
                $this->ensure($locked->status === GroceryStatus::Waiting, 'Paket hanya dapat ditandai tidak tersedia sebelum klaim.');
            }
            $old = $locked->status->value;
            if ($isOwner && $locked->{$this->claimantField($locked)} !== null) {
                $locked->forceFill(['cancellation_requested_at' => now(), 'cancellation_request_reason' => $reason, 'exception_reported_at' => now(), 'exception_kind' => 'cancellation_request', 'exception_reason' => 'Permintaan batal setelah klaim; serah fisik perlu dipastikan.', 'exception_resolved_at' => null, 'exception_resolved_by_id' => null, 'exception_resolution' => null])->save();
                $this->record($current, $locked, $old, 'exception_reported', 'Warga meminta batal; penyerahan dikunci dan saldo tetap ditahan sampai serah fisik dipastikan.');

                return $locked;
            }
            $this->ledger->releaseHold($locked->balanceHold()->firstOrFail());
            $locked->forceFill(['status' => $next, $unavailable ? 'rejection_reason' : 'cancellation_reason' => $reason])->save();
            if ($claimantConfirmsCancellation) {
                $locked->forceFill(['exception_resolved_at' => now(), 'exception_resolution' => 'Claimant memastikan belum diserahkan: '.$reason])->save();
            }
            $this->record($current, $locked, $old, $unavailable ? 'unavailable' : 'cancelled', $reason.' Saldo yang ditahan sudah dilepas.');

            return $locked;
        }, 5);
    }

    /**
     * @template T of WithdrawalRequest|GroceryRedemption
     *
     * @param  T  $request
     * @return T
     */
    public function complete(User $actor, WithdrawalRequest|GroceryRedemption $request, ?UploadedFile $proof, string $key): WithdrawalRequest|GroceryRedemption
    {
        $this->ensure(preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,190}$/', $key) === 1, 'Kunci transaksi tidak valid.');
        $checksum = $proof === null ? null : hash_file('sha256', $proof->getRealPath());
        $this->ensure($proof === null || is_string($checksum), 'Lampiran tidak dapat dibaca.');
        $payloadHash = hash('sha256', json_encode([$request::class, $request->id, $actor->id, $checksum], JSON_THROW_ON_ERROR));
        $media = null;

        try {
            return DB::transaction(function () use ($actor, $request, $proof, $payloadHash, &$media) {
                $locked = $request->newQuery()->lockForUpdate()->findOrFail($request->id);
                $this->lockFinancialParties($actor, $locked);
                $this->guard->check($actor, $this->role($locked), $locked->service_area_id);
                $this->ensure($locked->{$this->claimantField($locked)} === $actor->id, 'Hanya pengambil tugas ini yang boleh mencatat penyerahan.');
                if ($locked->receipt_ledger_entry_id !== null) {
                    $this->ensure($locked->settlement_payload_hash === $payloadHash, 'Transaksi sudah tercatat dengan isi/lampiran berbeda.');

                    return $locked;
                }
                $this->assertClaimant($actor, $locked);
                $this->ensureNoException($locked);
                $this->ensureZeroConversion($locked);
                $media = $proof === null ? null : app(StorePrivateMedia::class)->handlePhoto($proof, $actor);
                if ($media !== null) {
                    $media->forceFill(['attachable_type' => $locked::class, 'attachable_id' => $locked->id])->save();
                }
                $this->settle($actor, $locked, $payloadHash, $media, 'Uang/paket sudah diserahkan oleh pengambil tugas.');

                return $locked;
            }, 5);
        } catch (\Throwable $exception) {
            if ($media instanceof Media && ! $request->newQuery()->whereKey($request->id)->where('proof_media_id', $media->id)->whereNotNull('receipt_ledger_entry_id')->exists()) {
                Storage::disk($media->disk)->delete($media->path);
                $media->delete();
            }
            throw $exception;
        }
    }

    /**
     * @template T of WithdrawalRequest|GroceryRedemption
     *
     * @param  T  $request
     * @return T
     */
    public function reportException(User $actor, WithdrawalRequest|GroceryRedemption $request, string $reason): WithdrawalRequest|GroceryRedemption
    {
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($actor, $request, $reason) {
            $locked = $request->newQuery()->lockForUpdate()->findOrFail($request->id);
            $this->assertClaimant($actor, $locked);
            $old = $locked->status->value;
            $locked->forceFill(['exception_reported_at' => now(), 'exception_kind' => 'physical_uncertainty', 'exception_reason' => $reason, 'exception_resolved_at' => null, 'exception_resolved_by_id' => null, 'exception_resolution' => null])->save();
            $this->record($actor, $locked, $old, 'exception_reported', $reason.' Jangan serahkan uang/paket lagi sebelum keputusan Admin.');

            return $locked;
        }, 5);
    }

    /**
     * @template T of WithdrawalRequest|GroceryRedemption
     *
     * @param  T  $request
     * @return T
     */
    public function resolveException(User $admin, WithdrawalRequest|GroceryRedemption $request, bool $wasHandedOver, string $reason, string $password, ?string $physicalTime = null): WithdrawalRequest|GroceryRedemption
    {
        $reason = $this->reason($reason);
        $knownTime = null;
        if ($physicalTime !== null && trim($physicalTime) !== '') {
            try {
                $knownTime = CarbonImmutable::parse($physicalTime, 'Asia/Jakarta');
            } catch (\Throwable) {
                throw ValidationException::withMessages(['physical_time' => 'Waktu serah fisik tidak valid.']);
            }
            $this->ensure(preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $physicalTime) === 1 && $knownTime->format('Y-m-d') === substr($physicalTime, 0, 10) && ! $knownTime->isFuture(), 'Waktu serah harus valid dan tidak di masa depan.');
        }
        $rateKey = 'exception-reauth:'.$admin->id;
        if (RateLimiter::tooManyAttempts($rateKey, 5) || ! Hash::check($password, $admin->fresh()->password)) {
            RateLimiter::hit($rateKey, 300);
            throw ValidationException::withMessages(['password' => 'Autentikasi ulang gagal atau terlalu banyak percobaan.']);
        }
        RateLimiter::clear($rateKey);

        return DB::transaction(function () use ($admin, $request, $wasHandedOver, $reason, $knownTime) {
            $locked = $request->newQuery()->lockForUpdate()->findOrFail($request->id);
            $current = User::query()->lockForUpdate()->findOrFail($admin->id);
            if ($current->role !== UserRole::Admin || $current->status !== UserStatus::Active || $current->id === $locked->customer_id || $current->id === $locked->{$this->claimantField($locked)}) {
                throw new AuthorizationException('Pengecualian hanya diselesaikan Admin aktif yang bukan pihak transaksi.');
            }
            $resolution = ($wasHandedOver ? 'diserahkan: ' : 'belum_diserahkan: ').$reason.($knownTime === null ? '' : '; waktu fisik: '.$knownTime->toIso8601String());
            if ($locked->exception_resolved_at !== null && $locked->exception_resolution === $resolution) {
                return $locked;
            }
            $this->ensure($locked->exception_reported_at !== null && $locked->exception_resolved_at === null, 'Transaksi tidak berada dalam antrean pengecualian.');
            $this->ensureZeroConversion($locked);
            $old = $locked->status->value;
            $oldClaimant = $locked->{$this->claimantField($locked)};
            if ($wasHandedOver) {
                $this->ensure($locked->{$this->claimantField($locked)} !== null, 'Pelaksana fisik harus tercatat sebelum penyelesaian.');
                $this->settle($current, $locked, hash('sha256', $resolution), null, 'Penyelesaian pengecualian: '.$reason, $knownTime);
            } else {
                if ($locked->getAttribute('cancellation_requested_at') !== null) {
                    $this->ledger->releaseHold($locked->balanceHold()->firstOrFail());
                    $locked->forceFill(['status' => $locked instanceof WithdrawalRequest ? WithdrawalStatus::Cancelled : GroceryStatus::Cancelled, 'cancellation_reason' => $reason])->save();
                } else {
                    $this->requeue($locked);
                }
            }
            $locked->forceFill(['exception_resolved_by_id' => $current->id, 'exception_resolved_at' => now(), 'exception_resolution' => $resolution])->save();
            $this->record($current, $locked, $old, 'exception_resolved', $resolution, $oldClaimant);

            return $locked;
        }, 5);
    }

    private function assertClaimant(User $actor, WithdrawalRequest|GroceryRedemption $request): void
    {
        $this->lockFinancialParties($actor, $request);
        $this->guard->check($actor, $this->role($request), $request->service_area_id);
        $this->ensure($request->{$this->claimantField($request)} === $actor->id, 'Tugas tidak lagi menjadi tanggung jawab Anda.');
        $this->ensure($request->status === $this->processing($request) || ($request instanceof GroceryRedemption && $request->status === GroceryStatus::ReadyForPickup), 'Tugas belum diambil atau sudah selesai.');
    }

    private function lockFinancialParties(User $actor, WithdrawalRequest|GroceryRedemption $request): void
    {
        User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        User::query()->whereKey($request->customer_id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @template T of WithdrawalRequest|GroceryRedemption
     *
     * @param  T  $request
     * @return T
     */
    public function freezeException(User $admin, WithdrawalRequest|GroceryRedemption $request, string $reason, string $password): WithdrawalRequest|GroceryRedemption
    {
        $reason = $this->reason($reason);
        app(AdminReauthentication::class)->confirm($admin, $password);

        return DB::transaction(function () use ($admin, $request, $reason) {
            $locked = $request->newQuery()->lockForUpdate()->findOrFail($request->id);
            $current = User::query()->lockForUpdate()->findOrFail($admin->id);
            if ($current->role !== UserRole::Admin || $current->status !== UserStatus::Active
                || in_array($current->id, [$locked->customer_id, $locked->{$this->claimantField($locked)}], true)) {
                throw new AuthorizationException('Hanya Admin aktif yang bukan pihak transaksi.');
            }
            $this->ensure($locked->{$this->claimantField($locked)} !== null && ! $locked->status->isTerminal(), 'Hanya klaim yang belum selesai dapat dikunci sebagai pengecualian.');
            $locked->forceFill(['exception_reported_at' => now(), 'exception_kind' => 'admin_review', 'exception_reason' => $reason, 'exception_resolved_at' => null, 'exception_resolved_by_id' => null, 'exception_resolution' => null])->save();
            $this->record($current, $locked, $locked->status->value, 'exception_reported', 'Admin mengunci klaim bermasalah: '.$reason);

            return $locked;
        }, 5);
    }

    private function ensureZeroConversion(WithdrawalRequest|GroceryRedemption $request): void
    {
        User::query()->whereKey($request->customer_id)->lockForUpdate()->firstOrFail();
        $this->ensure($request->receipt_ledger_entry_id === null && ! LedgerEntry::query()->where('source_type', $request::class)->where('source_id', $request->id)->where('kind', 'hold_conversion')->lockForUpdate()->exists(), 'Mutasi transaksi sudah tercatat. Jangan lakukan penyerahan ulang.');
        $hold = $request->balanceHold()->firstOrFail();
        $this->ensure($hold->source_type === $request::class && $hold->source_id === $request->id
            && $hold->amount === ($request instanceof WithdrawalRequest ? $request->amount : $request->value_snapshot)
            && $hold->account()->firstOrFail()->user_id === $request->customer_id, 'Sumber dan nilai saldo tertahan tidak sesuai transaksi.');
        $this->ensure($hold->isActive(), 'Saldo tertahan tidak lagi aktif.');
    }

    private function settle(User $actor, WithdrawalRequest|GroceryRedemption $request, string $hash, ?Media $media, string $reason, ?CarbonImmutable $physicalTime = null): void
    {
        $old = $request->status->value;
        $isCash = $request instanceof WithdrawalRequest;
        $entry = $this->ledger->convertHold($request->balanceHold()->firstOrFail(), ($isCash ? 'withdrawal:' : 'grocery:').$request->id.':settlement');
        $attributes = ['status' => $isCash ? WithdrawalStatus::Paid : GroceryStatus::Completed, 'receipt_ledger_entry_id' => $entry->id, 'proof_media_id' => $media?->id, 'settlement_payload_hash' => $hash, 'physical_handed_over_at' => $actor->hasRole('admin') ? $physicalTime : now()];
        $attributes[$isCash ? 'paid_at' : 'handed_over_at'] = now();
        if (! $isCash) {
            $attributes['handover_actor_id'] = $request->claimed_by_id;
        }
        $request->forceFill($attributes)->save();
        $this->record($actor, $request, $old, 'completed', $reason);
    }

    private function requeue(WithdrawalRequest|GroceryRedemption $request): void
    {
        $this->ensure(! $request->status->isTerminal(), 'Transaksi sudah selesai.');
        $request->forceFill(['status' => $this->waiting($request), $this->claimantField($request) => null, 'claimed_at' => null, 'claim_released_at' => now()])->save();
    }

    private function ensureNoException(WithdrawalRequest|GroceryRedemption $request): void
    {
        $this->ensure($request->exception_reported_at === null || $request->exception_resolved_at !== null, 'Transaksi dikunci untuk pemeriksaan Admin. Jangan lakukan penyerahan ulang.');
    }

    private function role(WithdrawalRequest|GroceryRedemption $request): UserRole
    {
        return $request instanceof WithdrawalRequest ? UserRole::Treasurer : UserRole::Officer;
    }

    private function claimantField(WithdrawalRequest|GroceryRedemption $request): string
    {
        return $request instanceof WithdrawalRequest ? 'payer_id' : 'claimed_by_id';
    }

    private function waiting(WithdrawalRequest|GroceryRedemption $request): WithdrawalStatus|GroceryStatus
    {
        return $request instanceof WithdrawalRequest ? WithdrawalStatus::Waiting : GroceryStatus::Waiting;
    }

    private function processing(WithdrawalRequest|GroceryRedemption $request): WithdrawalStatus|GroceryStatus
    {
        return $request instanceof WithdrawalRequest ? WithdrawalStatus::Processing : GroceryStatus::Processing;
    }

    private function reason(string $reason): string
    {
        $this->ensure(mb_strlen(trim($reason)) >= 10 && mb_strlen($reason) <= 1000, 'Alasan harus 10-1000 karakter.');

        return trim($reason);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['workflow' => $message]);
        }
    }

    private function record(User $actor, WithdrawalRequest|GroceryRedemption $request, string $old, string $event, string $reason, ?int $oldClaimant = null): void
    {
        $type = ($request instanceof WithdrawalRequest ? 'withdrawal.' : 'grocery.').$event;
        $request->statusHistory()->create(['old_status' => $old, 'new_status' => $request->status->value, 'actor_id' => $actor->id, 'reason' => $reason, 'occurred_at' => now()]);
        $this->audit->record($actor, $type, $request, ['status' => $old, 'claimant_id' => $oldClaimant], ['status' => $request->status->value, 'reason' => $reason, 'claimant_id' => $request->{$this->claimantField($request)}, 'ledger_entry_id' => $request->receipt_ledger_entry_id], (string) Str::uuid());
        NotificationRequested::dispatch(new NotificationPayload(recipientId: $request->customer_id, type: $type, title: 'Status layanan diperbarui', body: $request->request_number.': '.str_replace('_', ' ', $request->status->value).'. Buka riwayat untuk rincian.', reference: '/notifikasi', dedupeKey: (string) Str::uuid()));
    }
}
