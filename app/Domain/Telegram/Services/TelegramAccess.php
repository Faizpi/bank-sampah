<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Telegram\Models\TelegramLink;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class TelegramAccess
{
    public static function fingerprint(User $user): string
    {
        return hash('sha256', implode('|', [$user->id, $user->role->value, $user->status->value, $user->getAuthPassword()]));
    }

    public function eligible(User $user): void
    {
        if ($user->status !== UserStatus::Active || ! $user->hasRole('warga', 'petugas', 'bendahara')) {
            throw new AuthorizationException('Akun aktif Warga, Petugas, atau Bendahara diperlukan.');
        }
    }

    public function actor(TelegramLink $link, bool $transaction = false): User
    {
        $actor = User::query()->find($link->user_id);
        if ($actor === null) {
            throw new AuthorizationException('Akses Telegram tidak lagi berlaku.');
        }
        $this->eligible($actor);
        if (! hash_equals($link->account_fingerprint, self::fingerprint($actor))) {
            throw new AuthorizationException('Akun berubah. Perbarui penautan melalui website.');
        }
        if ($transaction && $link->authorized_until->isPast()) {
            throw ValidationException::withMessages(['authorization' => 'Otorisasi tindakan berakhir. Buka Profil → Telegram di web untuk memperbaruinya. Notifikasi tetap mengikuti pilihan Anda.']);
        }
        if ($transaction && ! $actor->hasRole('warga') && $link->last_activity_at !== null && $link->last_activity_at->addMinutes((int) config('telegram.staff_idle_minutes'))->isPast()) {
            throw ValidationException::withMessages(['authorization' => 'Otorisasi tindakan berakhir karena tidak ada aktivitas. Buka Profil → Telegram di web untuk memperbaruinya. Notifikasi tetap mengikuti pilihan Anda.']);
        }

        return $actor;
    }

    public static function configured(): bool
    {
        return (bool) config('telegram.enabled')
            && preg_match('/^[A-Za-z][A-Za-z0-9_]{4,31}$/', (string) config('telegram.bot_username')) === 1
            && (string) config('telegram.bot_token') !== ''
            && preg_match('/^[A-Za-z0-9_-]{32,256}$/', (string) config('telegram.webhook_secret')) === 1;
    }
}
