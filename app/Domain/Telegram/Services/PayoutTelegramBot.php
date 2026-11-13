<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutChannels;
use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Withdrawals\Services\WithdrawalMinimum;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;

final class PayoutTelegramBot
{
    /** @param array<string, mixed> $parameters */
    public function handle(BotContext $ctx, string $command, array $parameters, string $text): void
    {
        if (str_starts_with($command, 'payout.monitor')) {
            $actor = $ctx->actor('bendahara');
            $this->listing($ctx, $actor, $parameters, true);

            return;
        }
        $actor = $ctx->actor('warga');
        if (in_array($command, ['payout.history', 'payout.active'], true)) {
            $this->listing($ctx, $actor, [...$parameters, 'active' => $command === 'payout.active'], false);

            return;
        }
        if ($command === 'payout.detail') {
            $this->detail($ctx, app(PayoutAccess::class)->visibleFor($actor)->findOrFail((int) $parameters['id']), false);

            return;
        }
        if ($command === 'payout.cancel') {
            $row = app(PayoutAccess::class)->visibleFor($actor)->findOrFail((int) $parameters['id']);
            $ctx->state('payout.cancel.review', ['id' => $row->id]);
            $ctx->say('Batalkan '.$row->request_number.'? Pembatalan hanya berlaku sebelum permintaan dikirim.', [BotContext::button('Ya, batalkan simulasi', 'payout.cancel.confirm', ['id' => $row->id]), BotContext::button('Periksa lagi', 'payout.detail', ['id' => $row->id])]);

            return;
        }
        if ($command === 'payout.cancel.confirm') {
            abort_unless($ctx->conversation->state === 'payout.cancel.review' && (int) $ctx->conversation->data['id'] === (int) $parameters['id'], 422);
            $row = app(PayoutAccess::class)->visibleFor($actor)->findOrFail((int) $parameters['id']);
            $this->detail($ctx, app(PayoutService::class)->cancel($actor, $row), false);

            return;
        }
        app(PayoutAccess::class)->requireConfigured();
        if ($command === 'payout.start') {
            $areas = app(WithdrawalService::class)->availableAreasFor($actor)->get();
            abort_if($areas->isEmpty(), 422);
            $data = ['key' => (string) str()->uuid()];
            if ($areas->count() > 1) {
                $ctx->state('payout.area', $data);
                $ctx->say('Pilih area layanan.', [...$areas->map(fn ($row): array => BotContext::button($row->name, 'payout.area', ['id' => $row->id]))->all(), BotContext::button('Ganti metode', 'citizen.withdrawal')]);
            } else {
                $this->channels($ctx, [...$data, 'service_area_id' => $areas->first()->id]);
            }

            return;
        }
        $data = $ctx->conversation->data;
        if ($command === 'payout.area') {
            abort_unless($ctx->conversation->state === 'payout.area', 422);
            $area = app(WithdrawalService::class)->availableAreasFor($actor)->findOrFail((int) $parameters['id']);
            $this->channels($ctx, [...$data, 'service_area_id' => $area->id]);
        } elseif ($command === 'payout.channel') {
            abort_unless($ctx->conversation->state === 'payout.channel', 422);
            app(PayoutChannels::class)->get((string) $parameters['channel']);
            $ctx->state('payout.name', [...$data, 'channel' => (string) $parameters['channel']]);
            $ctx->say('Kirim nama pemilik tujuan uji. Contoh: Siti Aminah.', [BotContext::button('Ganti metode', 'citizen.withdrawal')]);
        } elseif ($command === 'payout.submit') {
            abort_unless($ctx->conversation->state === 'payout.review', 422);
            $row = app(PayoutService::class)->request($actor, [...$data, 'confirmed' => true], (string) $data['key']);
            $this->detail($ctx, $row, false);
        } elseif ($command === '') {
            $state = $ctx->conversation->state;
            if ($state === 'payout.name') {
                $data['name'] = BotInput::text($text, 2, 100);
                $ctx->state('payout.number', $data);
                $channel = app(PayoutChannels::class)->get((string) $data['channel']);
                $ctx->say($channel['wallet'] ? 'Kirim nomor dompet digital uji. Contoh: 081234567890.' : 'Kirim nomor rekening uji. Contoh: 0123456789.', [BotContext::button('Ganti metode', 'citizen.withdrawal')]);
            } elseif ($state === 'payout.number') {
                $data['number'] = app(PayoutChannels::class)->normalizeNumber((string) $data['channel'], $text);
                $ctx->state('payout.amount', $data);
                $ctx->say('Berapa rupiah yang ingin dicairkan dalam simulasi? Minimal Rp '.number_format(app(WithdrawalMinimum::class)->amount(), 0, ',', '.').'. Saldo disisihkan saat pengajuan dikonfirmasi.', [BotContext::button('Ganti metode', 'citizen.withdrawal')]);
            } elseif ($state === 'payout.amount') {
                $data['amount'] = BotInput::money($text);
                $quote = app(PayoutService::class)->quote($actor, $data);
                $data = [...$quote, 'key' => $data['key']];
                $ctx->state('payout.review', $data);
                $channel = app(PayoutChannels::class)->get($quote['channel']);
                $ctx->say('Pencairan digital (simulasi)'."\nAkun: ".$actor->name."\nTujuan: ".$channel['name']."\nNama pemilik: ".$quote['name']."\nNomor uji: ".$quote['number']."\nNominal: ".BotInput::rupiah($quote['amount'])."\nNama dan nomor tujuan dikirim ke Xendit untuk simulasi. Tidak ada uang riil yang dikirim.", [BotContext::button('Konfirmasi pencairan simulasi', 'payout.submit'), BotContext::button('Ganti metode', 'citizen.withdrawal'), BotContext::button('Batal isi', 'cancel-input')]);
            } else {
                $ctx->say('Gunakan tombol terbaru atau ketik /menu untuk memulai kembali.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function channels(BotContext $ctx, array $data): void
    {
        $ctx->state('payout.channel', $data);
        $buttons = [];
        foreach (app(PayoutChannels::class)->all() as $id => $channel) {
            if ($channel['ready']) {
                $buttons[] = BotContext::button($channel['name'].' (simulasi)', 'payout.channel', ['channel' => $id]);
            }
        }
        $buttons[] = BotContext::button('Ganti metode', 'citizen.withdrawal');
        $buttons[] = BotContext::button('Menu', 'menu');
        $ctx->say('Pilih tujuan pencairan digital (simulasi). Tidak ada dana riil yang dikirim. Hanya tujuan yang sudah dikonfigurasi yang tersedia.', $buttons);
    }

    /** @param array<string, mixed> $p */
    private function listing(BotContext $ctx, User $actor, array $p, bool $monitor): void
    {
        if (isset($p['id'])) {
            $this->detail($ctx, app(PayoutAccess::class)->visibleFor($actor)->findOrFail((int) $p['id']), $monitor);

            return;
        }
        $query = app(PayoutAccess::class)->visibleFor($actor);
        if ($p['active'] ?? false) {
            $query->whereNotIn('status', ['succeeded', 'failed', 'cancelled', 'reversed']);
        }
        $rows = $query->latest('id')->paginate(5, ['*'], 'page', max(1, (int) ($p['page'] ?? 1)));
        $ctx->state('menu');
        $buttons = [];
        $lines = [];
        foreach ($rows as $i => $row) {
            $lines[] = $row->request_number.' · '.$row->label().' · '.BotInput::rupiah($row->amount);
            $buttons[] = BotContext::button('Rincian #'.($i + 1), $monitor ? 'payout.monitor' : 'payout.detail', ['id' => $row->id]);
        }
        if ($rows->hasMorePages()) {
            $buttons[] = BotContext::button('Berikutnya', $monitor ? 'payout.monitor' : 'payout.history', ['page' => $rows->currentPage() + 1]);
        }
        $buttons[] = BotContext::button('Menu', 'menu');
        $ctx->say('Pencairan digital (simulasi)'."\n".($lines === [] ? 'Belum ada pengajuan.' : implode("\n", $lines)), $buttons);
    }

    private function detail(BotContext $ctx, SandboxPayout $row, bool $monitor): void
    {
        $ctx->state('menu');
        $channel = app(PayoutChannels::class)->all()[$row->channel];
        $buttons = [BotContext::button('Periksa status', $monitor ? 'payout.monitor' : 'payout.detail', ['id' => $row->id]), BotContext::button('Menu', 'menu')];
        if (! $monitor && $row->status === 'queued' && $row->lease === null) {
            $buttons[] = BotContext::button('Batalkan sebelum diproses', 'payout.cancel', ['id' => $row->id]);
        }
        if (! $monitor) {
            $buttons[] = ['label' => 'Rincian dan bukti web', 'url' => route('citizen.payout.show', $row)];
        }
        $ctx->say($row->request_number."\n".$row->label()."\n".$channel['name'].' (simulasi) · '.$row->maskedNumber()."\n".BotInput::rupiah($row->amount)."\nDiproses otomatis oleh sistem (Xendit Sandbox).\nTidak ada dana riil yang dikirim.", $buttons);
    }
}
