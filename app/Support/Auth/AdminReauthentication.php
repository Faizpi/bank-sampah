<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class AdminReauthentication
{
    public function confirm(User $actor, string $password): void
    {
        $current = $actor->fresh();
        if ($current->role !== UserRole::Admin || $current->status !== UserStatus::Active) {
            throw new AuthorizationException('Autentikasi ulang hanya untuk Admin aktif.');
        }
        $key = 'admin-reauth:'.$current->id;
        if (RateLimiter::tooManyAttempts($key, 5) || ! Hash::check($password, $current->password)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['password' => 'Kata sandi tidak sesuai atau terlalu banyak percobaan.']);
        }
        RateLimiter::clear($key);
        session()->put('auth.admin_confirmation', ['user_id' => $current->id, 'at' => now()->timestamp]);
    }

    public function assertRecent(User $actor): void
    {
        $current = $actor->fresh();
        $confirmation = session('auth.admin_confirmation');
        if ($current->role !== UserRole::Admin || $current->status !== UserStatus::Active
            || ! is_array($confirmation) || ($confirmation['user_id'] ?? null) !== $current->id
            || ! is_int($confirmation['at'] ?? null) || $confirmation['at'] < now()->subMinutes(5)->timestamp) {
            throw new AuthorizationException('Konfirmasi ulang kata sandi Admin untuk tindakan finansial ini.');
        }
    }
}
