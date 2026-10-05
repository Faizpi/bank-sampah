<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminAvailabilityGuard
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public function run(Closure $operation): mixed
    {
        return DB::transaction(function () use ($operation): mixed {
            DB::table('access_role_locks')->where('name', 'admin')->lockForUpdate()->sole();

            return $operation();
        }, 5);
    }

    public function ensureMutation(User $user, bool $deleting = false): void
    {
        if (! $user->exists) {
            return;
        }
        $original = User::withTrashed()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
        if ($original->role !== UserRole::Admin || $original->status !== UserStatus::Active || $original->trashed()) {
            return;
        }
        if (! $deleting && $user->role === UserRole::Admin && $user->status === UserStatus::Active && ! $user->trashed()) {
            return;
        }
        if (User::query()->whereKeyNot($user->getKey())->where('role', UserRole::Admin)->where('status', UserStatus::Active)->lockForUpdate()->first() === null) {
            throw ValidationException::withMessages(['role' => 'Minimal satu Admin aktif harus tetap tersedia.']);
        }
    }
}
