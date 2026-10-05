<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\TermsAcceptanceHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class RegisterCitizen
{
    /**
     * @param  array{name: string, username: string, phone?: string|null, password: string, rt_id: int, address: string, terms_accepted: bool}  $attributes
     */
    public function handle(array $attributes): User
    {
        $this->ensureActiveHierarchy($attributes['rt_id']);

        $acceptedAt = now();
        $termsVersion = (string) config('app.terms_version');
        $phone = trim((string) ($attributes['phone'] ?? ''));

        return DB::transaction(function () use ($acceptedAt, $attributes, $phone, $termsVersion): User {
            $user = new User;
            $user->forceFill([
                'name' => $attributes['name'],
                'username' => $attributes['username'],
                'phone' => $phone === '' ? null : $phone,
                'password' => Hash::make($attributes['password']),
                'status' => UserStatus::PendingVerification,
                'role' => UserRole::Citizen,
                'terms_version' => $termsVersion,
                'terms_accepted_at' => $acceptedAt,
            ]);
            $user->save();

            CustomerProfile::query()->create([
                'user_id' => $user->id,
                'rt_id' => $attributes['rt_id'],
                'address' => $attributes['address'],
            ]);

            TermsAcceptanceHistory::query()->create([
                'user_id' => $user->id,
                'accepted_version' => $termsVersion,
                'accepted_at' => $acceptedAt,
            ]);

            return $user->fresh();
        });
    }

    private function ensureActiveHierarchy(int $rtId): void
    {
        $isActiveHierarchy = Rt::query()
            ->whereKey($rtId)
            ->where('is_active', true)
            ->whereHas('rw', fn ($query) => $query->where('is_active', true)->whereHas('dusun', fn ($query) => $query->where('is_active', true)))
            ->exists();

        if (! $isActiveHierarchy) {
            throw ValidationException::withMessages([
                'rt_id' => 'RT harus aktif dan berada dalam hierarki wilayah yang aktif.',
            ]);
        }
    }
}
