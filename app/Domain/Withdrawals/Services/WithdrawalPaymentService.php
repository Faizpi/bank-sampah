<?php

declare(strict_types=1);

namespace App\Domain\Withdrawals\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Domain\Platform\Actions\StorePrivateMedia;
use App\Domain\Platform\Models\Media;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class WithdrawalPaymentService
{
    private const SCOPE = 'withdrawal.pay';

    public function __construct(
        private PermissionChecker $permissions,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
        private StorePrivateMedia $mediaStore,
        private VisibleUsers $visibleUsers,
    ) {}

    public function handle(User $actor, WithdrawalRequest $withdrawal, string $recipientVerification, string $recipientReference, UploadedFile $proof, string $idempotencyKey): WithdrawalRequest
    {
        $this->authorize($actor, 'withdrawal.pay');
        $this->validateIdempotencyKey($idempotencyKey);
        $this->assertPayer($actor, $withdrawal);
        $verification = $this->recipientVerification($recipientVerification);
        $reference = $this->text($recipientReference, 'recipient_reference', 3, 120);
        $checksum = hash_file('sha256', $proof->getRealPath());
        if (! is_string($checksum)) {
            throw ValidationException::withMessages(['proof' => 'Bukti pembayaran tidak dapat dibaca.']);
        }
        $payloadHash = $this->payloadHash(['withdrawal_id' => $withdrawal->id, 'verification' => $verification, 'reference' => $reference, 'proof_checksum' => $checksum]);
        $existing = DB::transaction(fn (): ?IdempotencyKey => $this->existingIdempotency($actor, $idempotencyKey, $payloadHash));
        if ($existing !== null) {
            return WithdrawalRequest::query()->findOrFail($existing->result_id);
        }
        $media = null;

        try {
            $media = $this->mediaStore->handlePhoto($proof, $actor);

            return DB::transaction(function () use ($actor, $withdrawal, $verification, $reference, $media, $idempotencyKey, $payloadHash): WithdrawalRequest {
                $existing = $this->existingIdempotency($actor, $idempotencyKey, $payloadHash);
                if ($existing !== null) {
                    return WithdrawalRequest::query()->findOrFail($existing->result_id);
                }
                $key = IdempotencyKey::query()->create(['actor_id' => $actor->id, 'scope' => self::SCOPE, 'key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'status' => 'processing']);
                $locked = WithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
                $this->assertPayer($actor, $locked);
                if (! $locked->canTransitionTo(WithdrawalStatus::Paid)) {
                    throw ValidationException::withMessages(['status' => 'Pencairan belum siap dibayar atau sudah selesai.']);
                }
                $customer = $locked->customer()->with('customerProfile')->firstOrFail();
                if ($customer->customerProfile?->customer_number !== $reference) {
                    throw ValidationException::withMessages(['recipient_reference' => 'Referensi penerima tidak cocok dengan nasabah pencairan.']);
                }
                $old = $locked->status;
                $media->forceFill(['attachable_type' => WithdrawalRequest::class, 'attachable_id' => $locked->id])->save();
                $entry = $this->ledger->convertHold($locked->balanceHold()->firstOrFail(), 'withdrawal:'.$locked->id.':payment');
                $locked->forceFill([
                    'status' => WithdrawalStatus::Paid,
                    'paid_at' => now(),
                    'recipient_verification' => $verification,
                    'recipient_reference' => $reference,
                    'proof_media_id' => $media->id,
                    'receipt_ledger_entry_id' => $entry->id,
                ])->save();
                $locked->statusHistory()->create(['old_status' => $old->value, 'new_status' => WithdrawalStatus::Paid->value, 'actor_id' => $actor->id, 'reason' => 'Pembayaran diserahkan dan penerima diverifikasi.', 'occurred_at' => now()]);
                $this->auditLogger->record($actor, 'withdrawal.paid', $locked, ['status' => $old->value], ['status' => WithdrawalStatus::Paid->value, 'ledger_entry_id' => $entry->id, 'proof_media_id' => $media->id], $this->correlationId());
                $key->forceFill(['status' => 'succeeded', 'result_type' => WithdrawalRequest::class, 'result_id' => $locked->id])->save();
                $this->notify($locked);

                return $locked->fresh(['balanceHold', 'customer', 'payer', 'proofMedia', 'receiptLedgerEntry']);
            });
        } catch (\Throwable $exception) {
            if ($media instanceof Media) {
                Storage::disk((string) $media->disk)->delete((string) $media->path);
                $media->delete();
            }
            throw $exception;
        }
    }

    /** @return Builder<WithdrawalRequest> */
    public function payableFor(User $actor): Builder
    {
        $this->authorize($actor, 'withdrawal.pay');

        return WithdrawalRequest::query()
            ->with(['customer', 'payer', 'balanceHold'])
            ->where('status', WithdrawalStatus::ReadyForPickup)
            ->where('payer_id', $actor->id);
    }

    public function canDownloadProof(User $actor, Media $media): bool
    {
        if ($media->attachable_type !== WithdrawalRequest::class || $media->attachable_id === null || $media->getRawOriginal('visibility') !== 'private') {
            return false;
        }
        $withdrawal = WithdrawalRequest::query()->find($media->attachable_id);

        return $withdrawal instanceof WithdrawalRequest
            && ($withdrawal->customer_id === $actor->id || $withdrawal->payer_id === $actor->id || ($this->permissions->allows($actor, 'withdrawal.view') && ($this->permissions->allows($actor, 'user.view.all') || $this->visibleUsers->queryFor($actor)->whereKey($withdrawal->customer_id)->exists())))
            && ($this->permissions->allows($actor, 'withdrawal.view') || $this->permissions->allows($actor, 'withdrawal.pay'));
    }

    private function assertPayer(User $actor, WithdrawalRequest $withdrawal): void
    {
        if ($withdrawal->payer_id !== $actor->id) {
            throw new AuthorizationException('Pembayaran hanya dapat dilakukan payer yang ditugaskan.');
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk pembayaran.');
        }
    }

    private function recipientVerification(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (! in_array($normalized, ['kartu_nasabah', 'nomor_nasabah'], true)) {
            throw ValidationException::withMessages(['recipient_verification' => 'Metode verifikasi penerima tidak valid.']);
        }

        return $normalized;
    }

    private function text(string $value, string $field, int $min, int $max): string
    {
        $text = trim($value);
        if (mb_strlen($text) < $min || mb_strlen($text) > $max) {
            throw ValidationException::withMessages([$field => 'Nilai tidak memenuhi panjang yang diizinkan.']);
        }

        if ($field === 'recipient_reference' && preg_match('/^CST-[0-9]{8}$/', $text) !== 1) {
            throw ValidationException::withMessages([$field => 'Nomor nasabah harus berformat CST-########.']);
        }

        return $text;
    }

    private function validateIdempotencyKey(string $key): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,190}$/', $key) !== 1) {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key tidak valid.']);
        }
    }

    private function existingIdempotency(User $actor, string $key, string $payloadHash): ?IdempotencyKey
    {
        $existing = IdempotencyKey::activeForUpdate($actor->id, self::SCOPE, $key);
        if ($existing === null) {
            return null;
        }
        if ($existing->payload_hash !== $payloadHash || $existing->result_id === null) {
            throw ValidationException::withMessages(['idempotency_key' => 'Permintaan pembayaran berbeda atau belum selesai.']);
        }

        return $existing;
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function notify(WithdrawalRequest $withdrawal): void
    {
        NotificationRequested::dispatch(new NotificationPayload(
            recipientId: $withdrawal->customer_id,
            type: 'withdrawal.paid',
            title: 'Pencairan selesai',
            body: 'Pencairan '.$withdrawal->request_number.' telah dibayar.',
            reference: '/warga/pencairan/'.$withdrawal->id.'/bukti',
            dedupeKey: NotificationDedupeKey::for('withdrawal.paid:'.$withdrawal->request_number, $withdrawal->customer_id, 'withdrawal-v1'),
        ));
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
