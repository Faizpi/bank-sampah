<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\DatabaseSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ChangeUserRole
{
    public function handle(User $actor, User $subject, string $role, string $reason): User
    {
        return app(AdminAvailabilityGuard::class)->run(function () use ($actor, $subject, $role, $reason): User {
            $currentActor = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! app(PermissionChecker::class)->allows($currentActor, 'role.manage')) {
                throw new AuthorizationException('Hanya Admin aktif dapat mengubah peran.');
            }
            $next = UserRole::tryFrom($role);
            if ($next === null || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 1000) {
                throw ValidationException::withMessages(['role' => 'Pilih satu peran yang sah dan tulis alasan 10-1000 karakter.']);
            }
            $locked = User::query()->lockForUpdate()->findOrFail($subject->id);
            $old = $locked->role;
            if ($next === UserRole::Citizen && $locked->customerProfile === null) {
                throw ValidationException::withMessages(['role' => 'Akun warga memerlukan profil domisili. Warga baru mendaftar mandiri.']);
            }
            if ($old === $next) {
                return $locked;
            }
            $locked->forceFill(['role' => $next])->save();
            // A new staff role requires new assignments; old duties never carry over.
            $locked->staffProfile?->serviceAreas()->delete();
            $locked->staffProfile?->forceFill(['service_area_id' => null, 'active_from' => null, 'active_to' => null])->save();
            DatabaseSession::query()->where('user_id', $locked->id)->delete();
            app(AuditLogger::class)->record($currentActor, 'identity.user.role_changed', $locked, ['role' => $old->value], ['role' => $next->value, 'reason' => trim($reason)], (string) Str::uuid());

            return $locked->fresh();
        });
    }
}
