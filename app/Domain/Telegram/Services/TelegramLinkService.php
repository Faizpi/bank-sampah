<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Telegram\Models\TelegramChallenge;
use App\Domain\Telegram\Models\TelegramConversation;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Models\TelegramOutbox;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class TelegramLinkService
{
    public function __construct(private TelegramAccess $access, private AuditLogger $audit) {}

    /** @return array{id: string, url: string} */
    public function begin(User $actor, string $password): array
    {
        abort_unless(TelegramAccess::configured(), 404);
        $this->access->eligible($actor);

        return DB::transaction(function () use ($actor, $password): array {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $this->verifyPassword($actor, $password);
            $this->access->eligible($actor);
            TelegramChallenge::query()->where('user_id', $actor->id)->whereNull('consumed_at')->update(['cancelled_at' => now()]);
            $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $challenge = TelegramChallenge::query()->create([
                'id' => (string) Str::uuid(), 'user_id' => $actor->id, 'token_hash' => hash('sha256', $raw),
                'account_fingerprint' => TelegramAccess::fingerprint($actor),
                'link_id' => TelegramLink::query()->where('user_id', $actor->id)->value('id'),
                'expires_at' => now()->addMinutes((int) config('telegram.challenge_minutes')),
            ]);

            return ['id' => $challenge->id, 'url' => 'https://t.me/'.config('telegram.bot_username').'?start=link_'.$raw];
        });
    }

    public function claim(string $hash, string $telegramId, string $name): void
    {
        $key = 'telegram-claim:'.$telegramId;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['link' => 'Terlalu banyak tautan tidak sah dari Telegram ini. Coba lagi sekitar 15 menit lagi.']);
        }
        try {
            DB::transaction(function () use ($hash, $telegramId, $name): void {
                $challenge = TelegramChallenge::query()->where('token_hash', $hash)->lockForUpdate()->first();
                if ($challenge === null || $challenge->expires_at->isPast() || $challenge->consumed_at || $challenge->cancelled_at) {
                    throw ValidationException::withMessages(['link' => 'Tautan sudah tidak berlaku. Buat ulang melalui Profil → Telegram di website.']);
                }
                if ($challenge->telegram_id !== null && $challenge->telegram_id !== $telegramId) {
                    throw ValidationException::withMessages(['link' => 'Tautan ini sudah dibuka oleh Telegram lain. Buat ulang melalui website.']);
                }
                if ($challenge->link_id !== null && TelegramLink::query()->whereKey($challenge->link_id)->where('telegram_id', $telegramId)->doesntExist()) {
                    throw ValidationException::withMessages(['link' => 'Gunakan Telegram yang sudah tertaut, atau putuskan tautan lama terlebih dahulu.']);
                }
                $challenge->forceFill(['telegram_id' => $telegramId, 'display_name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '', 0, 120)])->save();
            });
        } catch (ValidationException $exception) {
            RateLimiter::hit($key, 900);
            throw $exception;
        }
        RateLimiter::clear($key);
    }

    /** @param array<string, bool|string> $preferences */
    public function approve(User $actor, string $challengeId, array $preferences): TelegramLink
    {
        try {
            return DB::transaction(function () use ($actor, $challengeId, $preferences): TelegramLink {
                $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
                $this->access->eligible($actor);
                $challenge = TelegramChallenge::query()->whereKey($challengeId)->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
                if ($challenge->expires_at->isPast() || $challenge->consumed_at || $challenge->cancelled_at || $challenge->telegram_id === null || ! hash_equals($challenge->account_fingerprint, TelegramAccess::fingerprint($actor))) {
                    throw ValidationException::withMessages(['link' => 'Penautan belum siap atau sudah kedaluwarsa. Buat ulang dan konfirmasi Telegram yang benar.']);
                }
                $existing = TelegramLink::query()->where('user_id', $actor->id)->lockForUpdate()->first();
                if (($existing !== null && ($challenge->link_id !== $existing->id || $existing->telegram_id !== $challenge->telegram_id))
                    || TelegramLink::query()->where('telegram_id', $challenge->telegram_id)->where('user_id', '!=', $actor->id)->exists()) {
                    throw ValidationException::withMessages(['link' => 'Akun atau Telegram sudah tertaut. Putuskan tautan sebelumnya melalui akun pemiliknya.']);
                }
                $values = [
                    'telegram_id' => $challenge->telegram_id, 'chat_id' => $challenge->telegram_id,
                    'account_fingerprint' => TelegramAccess::fingerprint($actor), 'generation' => (string) Str::uuid(),
                    'authorized_until' => now()->addHours((int) config($actor->hasRole('warga') ? 'telegram.citizen_authorization_hours' : 'telegram.staff_authorization_hours')),
                    'last_activity_at' => now(),
                    ...$this->preferences($preferences),
                ];
                $link = $existing ?? new TelegramLink(['id' => (string) Str::uuid(), 'user_id' => $actor->id]);
                $link->forceFill($values)->save();
                $challenge->forceFill(['consumed_at' => now()])->save();
                TelegramConversation::query()->where('telegram_id', $link->telegram_id)->delete();
                $this->audit->record($actor, 'telegram.link.authorized', $actor, [], ['connection_id' => $link->id], (string) Str::uuid());
                app(TelegramUi::class)->reply($link->telegram_id, $link, 'linked:'.$challenge->id, 'Penautan berhasil. Akun: '.$actor->name.' ('.$actor->role->value.'). Ketik /menu untuk memulai.', [BotContext::button('Buka menu', 'menu')]);

                return $link;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['link' => 'Akun atau Telegram baru saja ditautkan. Muat ulang dan periksa koneksi Anda.']);
        }
    }

    /** @param array<string, bool|string> $preferences */
    public function updatePreferences(User $actor, array $preferences): void
    {
        DB::transaction(function () use ($actor, $preferences): void {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $link = TelegramLink::query()->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            $actor = $this->access->actor($link);
            $values = $this->preferences($preferences);
            $before = $link->only(array_keys($values));
            $link->forceFill($values)->save();
            $this->audit->record($actor, 'telegram.preferences.updated', $actor, $before, $values, (string) Str::uuid());
        }, 5);
    }

    public function revoke(User $actor, User $owner, string $password, string $reason = 'Diputus oleh pemilik akun.'): void
    {
        $reason = BotInput::text($reason, 10, 1000);
        DB::transaction(function () use ($actor, $owner, $password, $reason): void {
            $actor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $this->verifyPassword($actor, $password);
            if ($actor->id !== $owner->id && (! $actor->hasRole('admin') || ! app(PermissionChecker::class)->allows($actor, 'session.revoke'))) {
                throw new AuthorizationException('Tidak berhak memutus tautan akun ini.');
            }
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $link = TelegramLink::query()->where('user_id', $owner->id)->lockForUpdate()->first();
            if ($link !== null) {
                TelegramOutbox::query()->where('link_id', $link->id)->whereIn('status', ['pending', 'leased'])->update(['status' => 'cancelled']);
                TelegramConversation::query()->where('link_id', $link->id)->delete();
                $this->audit->record($actor, 'telegram.link.revoked', $owner, [], ['connection_id' => $link->id, 'reason' => $reason], (string) Str::uuid());
                $link->delete();
            }
            TelegramChallenge::query()->where('user_id', $owner->id)->whereNull('consumed_at')->update(['cancelled_at' => now()]);
        }, 5);
    }

    private function verifyPassword(User $actor, string $password): void
    {
        $key = 'telegram-link-password:'.$actor->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['password' => 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.']);
        }
        $current = User::query()->findOrFail($actor->id);
        if (! Hash::check($password, $current->getAuthPassword())) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages(['password' => 'Kata sandi saat ini tidak sesuai.']);
        }
        RateLimiter::clear($key);
    }

    /** @param array<string, bool|string> $values
     * @return array<string, bool|string>
     */
    private function preferences(array $values): array
    {
        $validated = validator($values, [
            'transaction_notifications' => ['required', 'boolean'], 'reminders' => ['required', 'boolean'],
            'summaries' => ['required', 'boolean'], 'announcements' => ['required', 'boolean'],
            'summary_frequency' => ['required', 'in:daily,weekly,monthly'],
        ])->validate();

        return $validated;
    }
}
