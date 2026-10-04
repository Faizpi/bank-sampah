<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final readonly class ChangePassword
{
    private const SELF_SERVICE_METHOD = 'mandiri_profil';

    private const SELF_SERVICE_REASON = 'perubahan_mandiri';

    /** @var list<string> */
    private const DIRECT_METHODS = ['tatap_muka', 'callback_nomor_terdaftar'];

    public function __construct(private AuditLogger $auditLogger) {}

    public function selfService(
        User $actor,
        string $currentPassword,
        string $password,
        string $passwordConfirmation,
        string $currentSessionId,
        string $correlationId,
    ): User {
        $this->validatePassword($password, $passwordConfirmation);

        return DB::transaction(function () use ($actor, $currentPassword, $password, $currentSessionId, $correlationId): User {
            /** @var User $lockedActor */
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());

            Gate::forUser($actor)->authorize('update', $lockedActor);

            if (! Hash::check($currentPassword, $lockedActor->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Kata sandi saat ini tidak sesuai.',
                ]);
            }

            $lockedActor->forceFill([
                'password' => Hash::make($password),
                'remember_token' => null,
            ])->save();
            $lockedActor->databaseSessions()
                ->when($currentSessionId !== '', fn ($query) => $query->where('id', '!=', $currentSessionId))
                ->delete();

            $this->auditLogger->record(
                $lockedActor,
                'identity.password.changed.self_service',
                $lockedActor,
                [],
                [
                    'verification_method' => self::SELF_SERVICE_METHOD,
                    'reason' => self::SELF_SERVICE_REASON,
                    'other_sessions_revoked' => true,
                    'current_session_preserved' => $currentSessionId !== '',
                ],
                $this->normalizedCorrelationId($correlationId),
            );

            return $lockedActor;
        });
    }

    public function directAdmin(
        User $actor,
        User $subject,
        string $verificationMethod,
        string $reason,
        string $password,
        string $passwordConfirmation,
        string $correlationId,
    ): User {
        $reason = trim($reason);
        $this->validateDirectRequest($verificationMethod, $reason, $password, $passwordConfirmation);

        return DB::transaction(function () use ($actor, $subject, $verificationMethod, $reason, $password, $correlationId): User {
            /** @var User $lockedSubject */
            $lockedSubject = User::query()->lockForUpdate()->findOrFail($subject->getKey());

            Gate::forUser($actor)->authorize('resetPassword', $lockedSubject);
            Gate::forUser($actor)->authorize('revokeSession', $lockedSubject);

            $lockedSubject->forceFill([
                'password' => Hash::make($password),
                'remember_token' => null,
            ])->save();
            $lockedSubject->databaseSessions()->delete();

            $this->auditLogger->record(
                $actor,
                'identity.password.changed.direct_admin',
                $lockedSubject,
                [],
                [
                    'verification_method' => $verificationMethod,
                    'reason' => $this->sanitizedReason($reason),
                    'target_sessions_revoked' => true,
                ],
                $this->normalizedCorrelationId($correlationId),
            );

            return $lockedSubject;
        });
    }

    private function validateDirectRequest(string $verificationMethod, string $reason, string $password, string $passwordConfirmation): void
    {
        Validator::make([
            'verification_method' => $verificationMethod,
            'reason' => $reason,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'verification_method' => ['required', 'string', 'in:'.implode(',', self::DIRECT_METHODS)],
            'reason' => ['required', 'string', 'min:10', 'max:1000', 'regex:/\S/u'],
            'password' => $this->passwordRules(),
        ])->validate();
    }

    private function validatePassword(string $password, string $passwordConfirmation): void
    {
        Validator::make([
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'password' => $this->passwordRules(),
        ])->validate();
    }

    /** @return list<string|Password> */
    private function passwordRules(): array
    {
        return ['required', 'string', Password::min(10)->uncompromised(), 'confirmed'];
    }

    private function sanitizedReason(string $reason): string
    {
        return sprintf('[REDACTED: %d karakter]', mb_strlen($reason));
    }

    private function normalizedCorrelationId(string $correlationId): string
    {
        return Str::isUuid($correlationId) ? strtolower($correlationId) : (string) Str::uuid();
    }
}
