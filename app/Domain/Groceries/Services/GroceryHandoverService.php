<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Domain\Platform\Actions\StorePrivateMedia;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class GroceryHandoverService
{
    private const SCOPE = 'grocery.handover';

    public function __construct(
        private PermissionChecker $permissions,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
        private StorePrivateMedia $mediaStore,
        private VisibleUsers $visibleUsers,
    ) {}

    public function handle(User $actor, GroceryRedemption $redemption, string $recipientVerification, string $recipientReference, UploadedFile $proof, string $idempotencyKey): GroceryRedemption
    {
        $this->authorize($actor);
        $this->validateIdempotencyKey($idempotencyKey);
        $this->assertScope($actor, $redemption);
        if ($redemption->approver_id === $actor->id || $redemption->requested_by_id === $actor->id || $redemption->customer_id === $actor->id) {
            throw new AuthorizationException('Pemisahan tugas melarang pengaju atau approver melakukan handover.');
        }
        $verification = $this->verification($recipientVerification);
        $reference = $this->text($recipientReference, 'recipient_reference', 3, 120);
        $checksum = hash_file('sha256', $proof->getRealPath());
        if (! is_string($checksum)) {
            throw ValidationException::withMessages(['proof' => 'Bukti handover tidak dapat dibaca.']);
        }
        $payloadHash = $this->payloadHash(['redemption_id' => $redemption->id, 'verification' => $verification, 'reference' => $reference, 'proof_checksum' => $checksum]);
        $existing = DB::transaction(fn (): ?IdempotencyKey => $this->existingIdempotency($actor, $idempotencyKey, $payloadHash));
        if ($existing !== null) {
            return GroceryRedemption::query()->findOrFail($existing->result_id);
        }
        $media = null;

        try {
            $media = $this->mediaStore->handleEvidence($proof, $actor);

            $result = DB::transaction(function () use ($actor, $redemption, $verification, $reference, $media, $idempotencyKey, $payloadHash): GroceryRedemption {
                $existing = $this->existingIdempotency($actor, $idempotencyKey, $payloadHash);
                if ($existing !== null) {
                    return GroceryRedemption::query()->findOrFail($existing->result_id);
                }
                $key = IdempotencyKey::query()->create(['actor_id' => $actor->id, 'scope' => self::SCOPE, 'key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'status' => 'processing']);
                $locked = GroceryRedemption::query()->whereKey($redemption->id)->lockForUpdate()->firstOrFail();
                $this->assertScope($actor, $locked);
                if ($locked->approver_id === $actor->id || $locked->requested_by_id === $actor->id || $locked->customer_id === $actor->id) {
                    throw new AuthorizationException('Pemisahan tugas melarang handover oleh aktor ini.');
                }
                if (! $locked->canTransitionTo(GroceryStatus::Completed)) {
                    throw ValidationException::withMessages(['status' => 'Penukaran belum siap diserahkan atau sudah selesai.']);
                }
                $customer = $locked->customer()->with('customerProfile')->firstOrFail();
                if ($customer->customerProfile?->customer_number !== $reference) {
                    throw ValidationException::withMessages(['recipient_reference' => 'Nomor penerima tidak cocok dengan nasabah penukaran.']);
                }
                $old = $locked->status;
                $media->forceFill(['attachable_type' => GroceryRedemption::class, 'attachable_id' => $locked->id])->save();
                $entry = $this->ledger->convertHold($locked->balanceHold()->firstOrFail(), 'grocery:'.$locked->id.':handover');
                $locked->forceFill([
                    'status' => GroceryStatus::Completed,
                    'handover_actor_id' => $actor->id,
                    'handed_over_at' => now(),
                    'recipient_verification' => $verification,
                    'recipient_reference' => $reference,
                    'proof_media_id' => $media->id,
                    'receipt_ledger_entry_id' => $entry->id,
                ])->save();
                $locked->statusHistory()->create(['old_status' => $old->value, 'new_status' => GroceryStatus::Completed->value, 'actor_id' => $actor->id, 'reason' => 'Penerima diverifikasi dan paket diserahkan.', 'occurred_at' => now()]);
                $this->auditLogger->record($actor, 'grocery.handed_over', $locked, ['status' => $old->value], ['status' => GroceryStatus::Completed->value, 'ledger_entry_id' => $entry->id, 'proof_media_id' => $media->id], $this->correlationId());
                $key->forceFill(['status' => 'succeeded', 'result_type' => GroceryRedemption::class, 'result_id' => $locked->id])->save();
                $this->notify($locked);

                return $locked->fresh(['package', 'balanceHold', 'customer', 'handoverActor', 'proofMedia', 'receiptLedgerEntry']);
            });
            if ($media->attachable_id === null) {
                $this->deleteMedia($media);
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($media instanceof Media) {
                $this->deleteMedia($media);
            }
            throw $exception;
        }
    }

    /** @return Builder<GroceryRedemption> */
    public function readyFor(User $actor): Builder
    {
        $this->authorize($actor);

        return GroceryRedemption::query()->with(['customer', 'package', 'preparedBy'])->where('status', GroceryStatus::ReadyForPickup)->whereIn('customer_id', $this->visibleUsers->queryFor($actor)->select('users.id'));
    }

    public function canDownloadProof(User $actor, Media $media): bool
    {
        if ($media->attachable_type !== GroceryRedemption::class || $media->attachable_id === null || $media->getRawOriginal('visibility') !== 'private') {
            return false;
        }
        $redemption = GroceryRedemption::query()->find($media->attachable_id);
        if (! $redemption instanceof GroceryRedemption) {
            return false;
        }

        return ($redemption->customer_id === $actor->id || $redemption->handover_actor_id === $actor->id || ($this->permissions->allows($actor, 'grocery.view') && ($this->permissions->allows($actor, 'user.view.all') || $this->visibleUsers->queryFor($actor)->whereKey($redemption->customer_id)->exists()))) && ($this->permissions->allows($actor, 'grocery.view') || $this->permissions->allows($actor, 'grocery.handover'));
    }

    private function authorize(User $actor): void
    {
        if (! $this->permissions->allows($actor, 'grocery.handover')) {
            throw new AuthorizationException('Penyerahan sembako memerlukan permission khusus.');
        }
    }

    private function assertScope(User $actor, GroceryRedemption $redemption): void
    {
        if (! ($this->permissions->allows($actor, 'user.view.all') || $this->visibleUsers->queryFor($actor)->whereKey($redemption->customer_id)->exists())) {
            throw new AuthorizationException('Penukaran berada di luar scope handover Anda.');
        }
    }

    private function verification(string $value): string
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
            throw ValidationException::withMessages(['idempotency_key' => 'Handover berbeda menggunakan idempotency key yang sama.']);
        }

        return $existing;
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function deleteMedia(Media $media): void
    {
        Storage::disk((string) $media->disk)->delete((string) $media->path);
        $media->delete();
    }

    private function notify(GroceryRedemption $redemption): void
    {
        NotificationRequested::dispatch(new NotificationPayload(recipientId: $redemption->customer_id, type: 'grocery.completed', title: 'Penukaran sembako selesai', body: 'Paket '.$redemption->request_number.' telah diserahkan.', reference: '/warga/sembako/'.$redemption->id.'/bukti', dedupeKey: NotificationDedupeKey::for('grocery.completed:'.$redemption->request_number, $redemption->customer_id, 'grocery-v1')));
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
