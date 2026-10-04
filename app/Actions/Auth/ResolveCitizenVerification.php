<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ResolveCitizenVerification
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function verify(User $actor, User $subject, string $correlationId): User
    {
        return $this->resolve($actor, $subject, 'verify', null, $correlationId);
    }

    public function reject(User $actor, User $subject, string $reason, string $correlationId): User
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        return $this->resolve($actor, $subject, 'reject', $reason, $correlationId);
    }

    private function resolve(User $actor, User $subject, string $decision, ?string $reason, string $correlationId): User
    {
        return DB::transaction(function () use ($actor, $subject, $decision, $reason, $correlationId): User {
            /** @var User $lockedSubject */
            $lockedSubject = User::query()->lockForUpdate()->findOrFail($subject->getKey());

            Gate::forUser($actor)->authorize($decision, $lockedSubject);

            if ($lockedSubject->status !== UserStatus::PendingVerification) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengguna yang menunggu verifikasi yang dapat diproses.',
                ]);
            }

            $oldValues = ['status' => $lockedSubject->status->value];
            $resolvedAt = now();
            $attributes = $decision === 'verify'
                ? [
                    'status' => UserStatus::Active,
                    'verified_at' => $resolvedAt,
                    'verified_by' => $actor->getKey(),
                    'rejection_reason' => null,
                ]
                : [
                    'status' => UserStatus::Rejected,
                    'verified_at' => null,
                    'verified_by' => null,
                    'rejection_reason' => $reason,
                ];

            $lockedSubject->forceFill($attributes)->save();

            $this->auditLogger->record(
                $actor,
                $decision === 'verify' ? 'identity.user.verified' : 'identity.user.rejected',
                $lockedSubject,
                $oldValues,
                [
                    'status' => $lockedSubject->status->value,
                    'verified_at' => $lockedSubject->verified_at?->toIso8601String(),
                    'verified_by' => $lockedSubject->verified_by,
                    'rejection_reason' => $lockedSubject->rejection_reason === null ? null : '[REDACTED]',
                ],
                $this->normalizedCorrelationId($correlationId),
            );

            return $lockedSubject;
        });
    }

    private function normalizedCorrelationId(string $correlationId): string
    {
        return Str::isUuid($correlationId) ? strtolower($correlationId) : (string) Str::uuid();
    }
}
