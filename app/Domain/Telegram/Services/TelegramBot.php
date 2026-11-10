<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Payouts\Services\PayoutAccess;
use Illuminate\Validation\ValidationException;

final class TelegramBot
{
    /** @param array<string, mixed> $parameters
     * @param  array<string, mixed>  $payload
     */
    public function handle(BotContext $ctx, string $command, array $parameters, array $payload): void
    {
        $text = trim((string) ($payload['text'] ?? ''));
        if ($command === '' && str_starts_with($text, '/')) {
            $command = match (strtolower(explode('@', explode(' ', $text)[0])[0])) {
                '/start', '/menu' => 'menu', '/help', '/panduan' => 'help', '/batal' => 'cancel-input', '/keluar' => 'logout', default => 'help',
            };
        }
        if (in_array($command, ['menu', 'cancel-input', 'help', 'logout'], true)) {
            $ctx->state('menu');
            if ($command === 'cancel-input') {
                $ctx->say('Pengisian dihentikan. Pengajuan yang sudah dikirim dan draf setoran tersimpan tidak dibatalkan.', [BotContext::button('Menu', 'menu')]);
            } elseif ($command === 'help') {
                $ctx->say('Gunakan tombol menu. /menu membuka menu; /batal menghentikan pengisian, bukan membatalkan transaksi. Akun dapat dipakai selama Telegram tetap tertaut dan hak aksesnya masih sah. /keluar menampilkan cara memutus tautan melalui website. Jangan kirim kata sandi di chat.', [['label' => 'Profil Telegram', 'url' => route('telegram.settings')], BotContext::button('Menu', 'menu')]);
            } elseif ($command === 'logout' && $ctx->link !== null) {
                $ctx->say('Untuk keluar dari akun ini, pilih Putuskan Telegram melalui Profil → Telegram di website. Sampai tautan diputus, akun tetap terhubung dan notifikasi mengikuti pilihan Anda. Pengajuan dan saldo tidak dibatalkan.', [['label' => 'Pengaturan Telegram', 'url' => route('telegram.settings')], BotContext::button('Menu', 'menu')]);
            } else {
                $this->menu($ctx);
            }

            return;
        }
        if (str_starts_with($command, 'payout.') || ($command === '' && str_starts_with($ctx->conversation->state, 'payout.'))) {
            app(PayoutTelegramBot::class)->handle($ctx, $command, $parameters, $text);
        } elseif (str_starts_with($command, 'pref.')) {
            $this->preferences($ctx, $command, $parameters);
        } elseif (str_starts_with($command, 'public.') || str_starts_with($command, 'estimate.') || ($command === '' && str_starts_with($ctx->conversation->state, 'estimate.'))) {
            app(PublicTelegramBot::class)->handle($ctx, $command, $parameters, $text);
        } elseif (str_starts_with($command, 'citizen.') || ($command === '' && str_starts_with($ctx->conversation->state, 'citizen.'))) {
            app(CitizenTelegramBot::class)->handle($ctx, $command, $parameters, $payload);
        } elseif (str_starts_with($command, 'officer.') || ($command === '' && str_starts_with($ctx->conversation->state, 'officer.'))) {
            app(OfficerTelegramBot::class)->handle($ctx, $command, $parameters, $payload);
        } elseif (str_starts_with($command, 'service.') || ($command === '' && str_starts_with($ctx->conversation->state, 'service.'))) {
            app(ServiceTelegramBot::class)->handle($ctx, $command, $parameters, $payload);
        } else {
            $ctx->say('Pilih tombol menu agar isian masuk ke layanan yang benar.', [BotContext::button('Menu', 'menu')]);
        }
    }

    private function menu(BotContext $ctx): void
    {
        $buttons = [];
        $title = 'Bank Sampah Sindangheula';
        if ($ctx->link !== null) {
            $actor = $ctx->actor();
            $title .= "\nAkun: ".$actor->name.' · '.$actor->role->value;
            $buttons = match ($actor->role->value) {
                'warga' => [BotContext::button('Saldo', 'citizen.balance'), BotContext::button('Riwayat', 'citizen.history'), BotContext::button('Pengajuan aktif', 'citizen.active'), BotContext::button('Jemput sampah', 'citizen.pickup'), BotContext::button('Cairkan saldo', 'citizen.withdrawal'), BotContext::button('Tukar sembako', 'citizen.grocery'), BotContext::button('Kartu nasabah', 'citizen.card')],
                'petugas' => [BotContext::button('Catat setoran', 'officer.deposit'), BotContext::button('Draf setoran', 'officer.drafts'), BotContext::button('Penjemputan', 'officer.pickups'), BotContext::button('Sembako', 'service.queue', ['kind' => 'grocery']), BotContext::button('Layanan keliling', 'officer.mobile'), BotContext::button('Riwayat pekerjaan', 'officer.history')],
                default => [BotContext::button('Antrean pembayaran', 'service.queue', ['kind' => 'cash']), BotContext::button('Tugas saya', 'service.queue', ['kind' => 'cash', 'mine' => true]), BotContext::button('Laporan pembayaran', 'service.report')],
            };
            $buttons[] = BotContext::button('Notifikasi', 'pref.show');
            if (PayoutAccess::configured() && $actor->hasRole('warga')) {
                $buttons[] = BotContext::button('Digital (simulasi)', 'payout.start');
            }
            if (config('payouts.enabled') && $actor->hasRole('bendahara')) {
                $buttons[] = BotContext::button('Pantau digital (simulasi)', 'payout.monitor');
            }
        } else {
            $buttons[] = ['label' => 'Hubungkan akun', 'url' => route('telegram.settings')];
        }
        $buttons[] = BotContext::button('Informasi umum', 'public.menu');
        $ctx->say($title, $buttons);
    }

    /** @param array<string, mixed> $parameters */
    private function preferences(BotContext $ctx, string $command, array $parameters): void
    {
        $ctx->actor();
        $link = $ctx->link;
        if ($command === 'pref.toggle') {
            $field = (string) ($parameters['field'] ?? '');
            if (! in_array($field, ['transaction_notifications', 'reminders', 'summaries', 'announcements'], true)) {
                throw ValidationException::withMessages(['setting' => 'Pilihan tidak berlaku.']);
            }
            abort_unless(is_bool($parameters['value'] ?? null), 422);
            $link->setAttribute($field, $parameters['value']);
        } elseif ($command === 'pref.frequency') {
            $value = (string) ($parameters['value'] ?? '');
            abort_unless(in_array($value, ['daily', 'weekly', 'monthly'], true), 422);
            $link->forceFill(['summary_frequency' => $value]);
        }
        if ($command !== 'pref.show') {
            app(TelegramLinkService::class)->updatePreferences($ctx->actor(), ['transaction_notifications' => $link->transaction_notifications, 'reminders' => $link->reminders, 'summaries' => $link->summaries, 'announcements' => $link->announcements, 'summary_frequency' => $link->summary_frequency]);
            $link->refresh();
        }
        $buttons = [];
        foreach (['transaction_notifications' => 'Info transaksi', 'reminders' => 'Pengingat', 'summaries' => 'Ringkasan tabungan', 'announcements' => 'Langganan pengumuman'] as $field => $label) {
            $buttons[] = BotContext::button(((bool) $link->getAttribute($field) ? 'Matikan ' : 'Aktifkan ').$label, 'pref.toggle', ['field' => $field, 'value' => ! (bool) $link->getAttribute($field)]);
        }
        foreach (['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'] as $value => $label) {
            $buttons[] = BotContext::button($label, 'pref.frequency', ['value' => $value]);
        }
        $buttons[] = BotContext::button('Menu', 'menu');
        $ctx->say('Pilih notifikasi yang Anda izinkan. Pengumuman hanya dikirim setelah langganan diaktifkan. Pengingat dibatasi satu ringkasan per hari. Frekuensi ringkasan saat ini: '.$link->summary_frequency.'.', $buttons);
    }
}
