<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\Reports\Services\ReportQueryService;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use App\Support\StatusLabel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ServiceTelegramBot
{
    /** @param array<string, mixed> $p
     * @param  array<string, mixed>  $payload
     */
    public function handle(BotContext $ctx, string $command, array $p, array $payload): void
    {
        if (str_starts_with($command, 'service.report') || ($command === '' && $ctx->conversation->state === 'service.report.period')) {
            $this->report($ctx, $command, $p, (string) ($payload['text'] ?? ''));

            return;
        }
        $data = $ctx->conversation->data;
        $kind = (string) ($p['kind'] ?? $data['kind'] ?? '');
        abort_unless(in_array($kind, ['cash', 'grocery'], true), 422);
        $actor = $ctx->actor($kind === 'cash' ? 'bendahara' : 'petugas');
        if ($command === 'service.queue') {
            $this->queue($ctx, $actor, $kind, $p);

            return;
        }
        if ($command === 'service.search') {
            $ctx->state('service.search', ['kind' => $kind, 'mine' => (bool) ($p['mine'] ?? false)]);
            $ctx->say('Ketik nama warga atau nomor pengajuan.');

            return;
        }
        if ($command === '' && $ctx->conversation->state === 'service.search') {
            $this->queue($ctx, $actor, $kind, ['search' => BotInput::text((string) $payload['text'], 1, 100), 'mine' => $data['mine']]);

            return;
        }
        $id = (int) ($p['id'] ?? $data['id'] ?? 0);
        $record = $this->record($actor, $kind, $id);
        if ($command === 'service.show') {
            $this->detail($ctx, $actor, $kind, $record);

            return;
        }
        $actor = $ctx->actor($kind === 'cash' ? 'bendahara' : 'petugas');
        $workflow = app(ClaimedServiceWorkflow::class);
        if ($command === 'service.claim') {
            $record = $workflow->claim($actor, $record);
            $this->detail($ctx, $actor, $kind, $record);
        } elseif ($command === 'service.ready') {
            abort_unless($record instanceof GroceryRedemption, 403);
            $this->detail($ctx, $actor, $kind, $workflow->ready($actor, $record));
        } elseif ($command === 'service.photo') {
            $this->assertClaimant($actor, $record);
            $ctx->state('service.photo', ['kind' => $kind, 'id' => $id, 'photos' => []]);
            $ctx->say('Kirim satu foto penyerahan jika ada. Foto tidak wajib.', [BotContext::button('Lewati foto', 'service.show', ['kind' => $kind, 'id' => $id])]);
        } elseif ($command === '' && $ctx->conversation->state === 'service.photo') {
            $this->assertClaimant($actor, $record);
            if (! isset($payload['upload'])) {
                throw ValidationException::withMessages(['photo' => 'Kirim satu foto atau pilih Lewati.']);
            }
            $this->review($ctx, $actor, $kind, $record, [$payload['upload']]);
        } elseif ($command === 'service.review') {
            $this->review($ctx, $actor, $kind, $record, $data['photos'] ?? []);
        } elseif ($command === 'service.complete') {
            abort_unless($ctx->conversation->state === 'service.review' && (int) $data['id'] === $id && $data['kind'] === $kind, 422);
            $this->assertClaimant($actor, $record);
            $photos = app(TelegramFiles::class)->uploads($ctx->connection(), $data['photos']);
            $record = $workflow->complete($actor, $record, $photos[0] ?? null, $ctx->key('handover'));
            $ctx->state('menu');
            $ctx->say('Penyerahan tercatat: '.$record->request_number."\n".BotInput::rupiah($kind === 'cash' ? $record->amount : $record->value_snapshot)."\n".StatusLabel::for($record->status)."\nJangan serahkan uang/paket lagi untuk transaksi ini.", [BotContext::button('Menu', 'menu')]);
        } elseif ($command === 'service.reason') {
            $operation = (string) $p['operation'];
            abort_unless(in_array($operation, ['release', 'exception', 'unavailable'], true), 422);
            $ctx->state('service.reason', ['kind' => $kind, 'id' => $id, 'operation' => $operation]);
            $ctx->say('Jelaskan alasan/kendala minimal 10 karakter. Lepas tugas hanya bila manfaat belum diberikan.');
        } elseif ($command === '' && $ctx->conversation->state === 'service.reason') {
            $reason = BotInput::text((string) $payload['text'], 10, 1000);
            $record = match ($data['operation']) {
                'release' => $workflow->release($actor, $record, $reason),
                'exception' => $workflow->reportException($actor, $record, $reason),
                'unavailable' => $workflow->cancel($actor, $record, $reason, true),
                default => abort(422),
            };
            $ctx->state('menu');
            $ctx->say($record->request_number.' · '.StatusLabel::for($record->status)."\nPeriksa status sebelum melakukan penyerahan lagi.", [BotContext::button('Antrean', 'service.queue', ['kind' => $kind]), BotContext::button('Menu', 'menu')]);
        } elseif ($command === 'service.cancel.review') {
            $this->assertClaimant($actor, $record);
            $ctx->say('Konfirmasi hanya jika uang/paket dipastikan BELUM diserahkan. Jika sudah atau tidak pasti, tunggu pemeriksaan Admin.', [BotContext::button('Belum diserahkan — batalkan', 'service.cancel.confirm', ['kind' => $kind, 'id' => $id]), BotContext::button('Periksa lagi', 'service.show', ['kind' => $kind, 'id' => $id])]);
        } elseif ($command === 'service.cancel.confirm') {
            $this->assertClaimant($actor, $record);
            abort_unless($record->getAttribute('cancellation_requested_at') && $record->getAttribute('exception_kind') === 'cancellation_request', 422);
            $record = $workflow->cancel($actor, $record, 'Warga meminta batal dan pelaksana memastikan manfaat belum diserahkan.');
            $ctx->state('menu');
            $ctx->say($record->request_number.' dibatalkan dan dana dilepas.', [BotContext::button('Menu', 'menu')]);
        } else {
            $ctx->say('Gunakan tombol pada pesan terbaru atau /menu.');
        }
    }

    /** @return Builder<WithdrawalRequest>|Builder<GroceryRedemption> */
    private function query(User $actor, string $kind, bool $payable = false): Builder
    {
        return $kind === 'cash'
            ? ($payable ? app(WithdrawalService::class)->payableFor($actor) : app(WithdrawalService::class)->visibleFor($actor))
            : ($payable ? app(GroceryService::class)->readyForHandover($actor) : app(GroceryService::class)->visibleFor($actor));
    }

    private function record(User $actor, string $kind, int $id): WithdrawalRequest|GroceryRedemption
    {
        $record = $this->query($actor, $kind)->with(['customer.customerProfile', 'balanceHold'])->findOrFail($id);
        if ($record->status->isTerminal()) {
            $this->assertClaimant($actor, $record);
        }

        return $record;
    }

    private function assertClaimant(User $actor, WithdrawalRequest|GroceryRedemption $record): void
    {
        abort_unless(($record instanceof WithdrawalRequest ? $record->payer_id : $record->claimed_by_id) === $actor->id, 403);
    }

    /** @param array<string, mixed> $p */
    private function queue(BotContext $ctx, User $actor, string $kind, array $p): void
    {
        $mine = (bool) ($p['mine'] ?? false);
        $field = $kind === 'cash' ? 'payer_id' : 'claimed_by_id';
        $search = mb_substr((string) ($p['search'] ?? ''), 0, 100);
        $query = $this->query($actor, $kind, true)->with('customer')->when($mine, fn ($q) => $q->where($field, $actor->id))->when(! $mine, fn ($q) => $q->whereNull($field));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('request_number', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$search.'%')));
        }
        $rows = $query->orderBy('created_at')->paginate(5, ['*'], 'page', max(1, (int) ($p['page'] ?? 1)));
        $ctx->state('menu');
        $buttons = $rows->getCollection()->map(fn ($row): array => BotContext::button($row->customer->name.' · '.BotInput::rupiah($kind === 'cash' ? $row->amount : $row->value_snapshot), 'service.show', ['kind' => $kind, 'id' => $row->id]))->all();
        $buttons[] = BotContext::button($mine ? 'Menunggu' : 'Tugas saya', 'service.queue', ['kind' => $kind, 'mine' => ! $mine]);
        $buttons[] = BotContext::button('Cari warga/nomor', 'service.search', ['kind' => $kind, 'mine' => $mine]);
        if ($rows->hasMorePages()) {
            $buttons[] = BotContext::button('Berikutnya', 'service.queue', ['kind' => $kind, 'mine' => $mine, 'search' => $search, 'page' => $rows->currentPage() + 1]);
        }
        $ctx->say($rows->isEmpty() ? 'Belum ada tugas pada pilihan ini.' : 'Pilih pengajuan yang ingin diperiksa.', $buttons);
    }

    private function detail(BotContext $ctx, User $actor, string $kind, WithdrawalRequest|GroceryRedemption $record): void
    {
        $claimant = $kind === 'cash' ? $record->payer_id : $record->claimed_by_id;
        $locked = $record->exception_reported_at && ! $record->exception_resolved_at;
        $ctx->state('service.detail', ['kind' => $kind, 'id' => $record->id, 'photos' => []]);
        $buttons = [];
        if ($locked) {
            if ($claimant === $actor->id && $record->getAttribute('exception_kind') === 'cancellation_request') {
                $buttons[] = BotContext::button('Konfirmasi belum diserahkan', 'service.cancel.review', ['kind' => $kind, 'id' => $record->id]);
            }
        } elseif (! $record->status->isTerminal()) {
            if ($claimant === null) {
                $buttons[] = BotContext::button($kind === 'cash' ? 'Tunai tersedia — ambil pembayaran' : 'Paket tersedia — ambil tugas', 'service.claim', ['kind' => $kind, 'id' => $record->id]);
                if ($kind === 'grocery') {
                    $buttons[] = BotContext::button('Paket tidak tersedia', 'service.reason', ['kind' => $kind, 'id' => $record->id, 'operation' => 'unavailable']);
                }
            } elseif ($claimant === $actor->id) {
                $buttons[] = BotContext::button($kind === 'cash' ? 'Uang sudah diserahkan' : 'Paket sudah diserahkan', 'service.review', ['kind' => $kind, 'id' => $record->id]);
                $buttons[] = BotContext::button('Foto opsional', 'service.photo', ['kind' => $kind, 'id' => $record->id]);
                if ($kind === 'grocery' && $record->status->value !== 'siap_diambil') {
                    $buttons[] = BotContext::button('Paket siap diambil (opsional)', 'service.ready', ['kind' => $kind, 'id' => $record->id]);
                }
                $buttons[] = BotContext::button('Belum diserahkan — lepas tugas', 'service.reason', ['kind' => $kind, 'id' => $record->id, 'operation' => 'release']);
                $buttons[] = BotContext::button('Penyerahan tidak pasti', 'service.reason', ['kind' => $kind, 'id' => $record->id, 'operation' => 'exception']);
            }
        }
        $buttons[] = BotContext::button('Menu', 'menu');
        $ctx->say($record->request_number."\n".$record->customer->name.' · '.$record->customer->customerProfile?->customer_number."\n".BotInput::rupiah($kind === 'cash' ? $record->amount : $record->value_snapshot)."\n".StatusLabel::for($record->status)."\n".($kind === 'cash' ? $record->pickup_location : (string) data_get($record->package_snapshot, 'name')).($locked ? "\nPenyerahan dikunci. Jangan serahkan ulang." : ''), $buttons);
    }

    /** @param list<array<string, string>> $photos */
    private function review(BotContext $ctx, User $actor, string $kind, WithdrawalRequest|GroceryRedemption $record, array $photos): void
    {
        $this->assertClaimant($actor, $record);
        $ctx->state('service.review', ['kind' => $kind, 'id' => $record->id, 'photos' => $photos]);
        $ctx->say('Pelaksana: '.$actor->name."\nPenerima: ".$record->customer->name."\n".$record->request_number."\n".BotInput::rupiah($kind === 'cash' ? $record->amount : $record->value_snapshot)."\nKonfirmasi hanya jika ".($kind === 'cash' ? 'uang tunai' : 'paket').' sudah diterima warga. Nilai akan dicatat sebagai saldo keluar.', [BotContext::button($kind === 'cash' ? 'Ya, catat pembayaran' : 'Ya, catat penyerahan', 'service.complete', ['kind' => $kind, 'id' => $record->id]), BotContext::button('Periksa lagi', 'service.show', ['kind' => $kind, 'id' => $record->id])]);
    }

    /** @param array<string, mixed> $p */
    private function report(BotContext $ctx, string $command, array $p, string $text): void
    {
        $actor = $ctx->actor('bendahara');
        if ($command === 'service.report') {
            $ctx->state('menu');
            $ctx->say('Pilih periode pembayaran sendiri yang sudah dicatat.', [BotContext::button('Tujuh hari terakhir', 'service.report.period', ['start' => today()->subDays(6)->toDateString(), 'end' => today()->addDay()->toDateString()]), BotContext::button('Bulan ini', 'service.report.period', ['start' => today()->startOfMonth()->toDateString(), 'end' => today()->addDay()->toDateString()]), BotContext::button('Rentang tanggal', 'service.report.custom')]);

            return;
        }
        if ($command === 'service.report.custom') {
            $ctx->state('service.report.period');
            $ctx->say('Kirim tanggal mulai dan akhir: DD/MM/YYYY DD/MM/YYYY. Maksimal 366 hari.');

            return;
        }
        if ($command === '') {
            $parts = preg_split('/\s+/', trim($text));
            if (! is_array($parts) || count($parts) !== 2) {
                throw ValidationException::withMessages(['period' => 'Kirim dua tanggal, contoh 01/10/2026 31/10/2026.']);
            }
            $start = BotInput::date($parts[0]);
            $end = CarbonImmutable::parse(BotInput::date($parts[1]))->addDay()->toDateString();
        } else {
            $start = BotInput::date((string) $p['start']);
            $end = BotInput::date((string) $p['end']);
        }
        if ($start >= $end || CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) > 366) {
            throw ValidationException::withMessages(['period' => 'Rentang tanggal tidak valid atau melebihi 366 hari.']);
        }
        $ctx->state('menu');
        if ($command === 'service.report.file') {
            $ctx->file(['kind' => 'report', 'start' => $start, 'end' => $end, 'caption' => 'Laporan pembayaran tunai sendiri. Simpan berkas secara pribadi.']);

            return;
        }
        $query = app(ReportQueryService::class)->query($actor, ['start' => $start, 'end' => $end], 'withdrawals');
        $ctx->say('Pembayaran sendiri: '.(clone $query)->count().' transaksi · '.BotInput::rupiah((int) $query->sum('amount')), [BotContext::button('Kirim Excel', 'service.report.file', ['start' => $start, 'end' => $end]), BotContext::button('Menu', 'menu')]);
    }
}
