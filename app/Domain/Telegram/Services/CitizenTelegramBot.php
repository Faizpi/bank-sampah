<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Queries\PublicWasteCatalog;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class CitizenTelegramBot
{
    /** @param array<string, mixed> $p
     * @param  array<string, mixed>  $payload
     */
    public function handle(BotContext $ctx, string $command, array $p, array $payload): void
    {
        $actor = $ctx->actor('warga');
        if ($command === '' && $ctx->conversation->state === 'citizen.withdrawal.method') {
            $command = 'citizen.withdrawal';
        }
        if ($command === 'citizen.withdrawal' && ($p['method'] ?? null) !== 'cash' && PayoutAccess::configured()) {
            $ctx->state('citizen.withdrawal.method');
            $ctx->say('Pilih metode pencairan.', [
                BotContext::button('Tunai', 'citizen.withdrawal', ['method' => 'cash']),
                BotContext::button('Digital (simulasi)', 'payout.start'),
                BotContext::button('Menu', 'menu'),
            ]);

            return;
        }
        if (in_array($command, ['citizen.balance', 'citizen.history', 'citizen.active', 'citizen.detail', 'citizen.card'], true)) {
            $ctx->state('menu');
            match ($command) {
                'citizen.balance' => $ctx->say($this->balance($actor), [BotContext::button('Riwayat', 'citizen.history'), BotContext::button('Menu', 'menu')]),
                'citizen.history' => $this->history($ctx, $actor, $p),
                'citizen.active' => $this->active($ctx, $actor),
                'citizen.detail' => $this->detail($ctx, $actor, (string) $p['kind'], (int) $p['id']),
                default => $ctx->file(['kind' => 'card', 'caption' => 'Kartu nasabah '.$actor->name.'. Simpan QR ini secara pribadi.']),
            };

            return;
        }
        $actor = $ctx->actor('warga');
        if ($command === 'citizen.cancel' || $command === 'citizen.cancel.confirm') {
            $kind = (string) $p['kind'];
            $record = $this->requests($actor, $kind)->findOrFail((int) $p['id']);
            if ($command === 'citizen.cancel') {
                $ctx->say('Batalkan pengajuan '.$record->request_number.'? Jika sudah diklaim, pembatalan mengikuti pemeriksaan penyerahan.', [BotContext::button('Ya, minta pembatalan', 'citizen.cancel.confirm', ['kind' => $kind, 'id' => $record->id]), BotContext::button('Periksa lagi', 'citizen.detail', ['kind' => $kind, 'id' => $record->id])]);
            } else {
                match (true) {
                    $record instanceof PickupRequest => app(PickupService::class)->cancel($actor, $record, 'Dibatalkan oleh pemilik melalui Telegram.'),
                    $record instanceof WithdrawalRequest => app(WithdrawalService::class)->cancel($actor, $record),
                    $record instanceof GroceryRedemption => app(GroceryService::class)->cancel($actor, $record),
                };
                $ctx->state('menu');
                $this->detail($ctx, $actor, $kind, $record->id);
            }

            return;
        }
        if (in_array($command, ['citizen.pickup', 'citizen.withdrawal', 'citizen.grocery'], true)) {
            $flow = substr($command, 8);
            $data = ['flow' => $flow, 'photos' => [], 'items' => [], 'notes' => null];
            $areas = $this->areas($actor, $flow)->get();
            if ($areas->isEmpty()) {
                throw ValidationException::withMessages(['area' => 'Alamat akun belum termasuk area layanan aktif. Periksa profil atau hubungi Admin.']);
            }
            if ($areas->count() > 1) {
                $ctx->state('citizen.area', $data);
                $buttons = $areas->map(fn ($area): array => BotContext::button($area->name, 'citizen.area', ['id' => $area->id]))->all();
                if ($flow === 'withdrawal' && PayoutAccess::configured()) {
                    $buttons[] = BotContext::button('Ganti metode', 'citizen.withdrawal');
                }
                $ctx->say('Pilih area layanan.', $buttons);
            } else {
                $data['service_area_id'] = $areas->first()->id;
                $this->start($ctx, $actor, $data);
            }

            return;
        }
        $data = $ctx->conversation->data;
        if ($command === 'citizen.area') {
            $data['service_area_id'] = $this->areas($actor, (string) $data['flow'])->findOrFail((int) $p['id'])->id;
            $this->start($ctx, $actor, $data);
        } elseif ($command === 'citizen.package') {
            $package = app(GroceryService::class)->activePackages($actor)->findOrFail((int) $p['id']);
            $data['package_id'] = $package->id;
            $this->review($ctx, $actor, $data);
        } elseif ($command === 'citizen.type') {
            $type = app(PublicWasteCatalog::class)->visibleTypes()->findOrFail((int) $p['id']);
            if (count($data['items']) >= 10 || in_array($type->id, array_column($data['items'], 'waste_type_id'), true)) {
                throw ValidationException::withMessages(['type' => 'Jenis sudah dipilih atau batas sepuluh jenis tercapai.']);
            }
            $data['items'][] = ['waste_type_id' => $type->id, 'estimated_weight_kg' => null, 'estimated_quantity' => null];
            $this->types($ctx, $data);
        } elseif ($command === 'citizen.types') {
            $this->types($ctx, $data, max(1, (int) ($p['page'] ?? 1)));
        } elseif ($command === 'citizen.types.done') {
            if (empty($data['items'])) {
                throw ValidationException::withMessages(['items' => 'Pilih minimal satu jenis sampah.']);
            }
            $this->extras($ctx, $data);
        } elseif ($command === 'citizen.extra') {
            $extra = (string) $p['step'];
            if ($extra === 'review') {
                $this->review($ctx, $actor, $data);
            } elseif ($extra === 'estimate') {
                $data['estimate_index'] = 0;
                $ctx->state('citizen.pickup.weight', $data);
                $ctx->say('Perkiraan berat jenis pertama dalam kg? Contoh: 1,25. Boleh dilewati.', [BotContext::button('Lewati', 'citizen.input', ['value' => 'skip'])]);
            } else {
                abort_unless(in_array($extra, ['note', 'photo'], true), 422);
                $ctx->state('citizen.pickup.'.$extra, $data);
                $ctx->say($extra === 'note' ? 'Tulis catatan akses penjemputan, maksimal 1.000 karakter.' : 'Kirim foto sampah, maksimal dua foto. Tidak wajib.', [BotContext::button('Selesai / lewati', 'citizen.extra', ['step' => 'review'])]);
            }
        } elseif ($command === 'citizen.submit') {
            abort_unless($ctx->conversation->state === 'citizen.review', 422);
            $record = match ($data['flow']) {
                'pickup' => app(PickupService::class)->submit($actor, $data, $data['items'], app(TelegramFiles::class)->uploads($ctx->connection(), $data['photos']), $ctx->key('pickup')),
                'withdrawal' => app(WithdrawalService::class)->request($actor, $data, $ctx->key('withdrawal')),
                'grocery' => app(GroceryService::class)->request($actor, $data, $ctx->key('grocery')),
                default => abort(422),
            };
            $kind = $data['flow'] === 'withdrawal' ? 'cash' : $data['flow'];
            $ctx->state('menu');
            $this->detail($ctx, $actor, $kind, $record->id);
        } else {
            $text = $command === 'citizen.input' ? (string) $p['value'] : trim((string) ($payload['text'] ?? ''));
            $this->input($ctx, $actor, $data, $text, $payload);
        }
    }

    /** @return Builder<ServiceArea> */
    private function areas(User $actor, string $flow): Builder
    {
        return match ($flow) {
            'pickup' => app(PickupService::class)->eligibleAreasFor($actor),
            'withdrawal' => app(WithdrawalService::class)->availableAreasFor($actor),
            'grocery' => app(GroceryService::class)->availableAreasFor($actor),
            default => abort(422),
        };
    }

    /** @param array<string, mixed> $data */
    private function start(BotContext $ctx, User $actor, array $data): void
    {
        if ($data['flow'] === 'grocery') {
            $rows = app(GroceryService::class)->activePackages($actor)->limit(20)->get();
            $ctx->state('citizen.grocery.package', $data);
            $ctx->say('Pilih paket. '.$this->balance($actor), $rows->map(fn ($row): array => BotContext::button($row->name.' · '.BotInput::rupiah($row->value), 'citizen.package', ['id' => $row->id]))->all());
        } elseif ($data['flow'] === 'withdrawal') {
            $ctx->state('citizen.withdrawal.amount', $data);
            $ctx->say('Berapa rupiah yang ingin dicairkan? Contoh: 25000. '.$this->balance($actor), PayoutAccess::configured() ? [BotContext::button('Ganti metode', 'citizen.withdrawal')] : []);
        } else {
            $this->datePrompt($ctx, 'citizen.pickup.date', $data);
        }
    }

    /** @param array<string, mixed> $data */
    private function datePrompt(BotContext $ctx, string $state, array $data): void
    {
        $ctx->state($state, $data);
        $buttons = [BotContext::button('Hari ini', 'citizen.input', ['value' => today()->toDateString()]), BotContext::button('Besok', 'citizen.input', ['value' => today()->addDay()->toDateString()])];
        if ($data['flow'] === 'withdrawal' && PayoutAccess::configured()) {
            $buttons[] = BotContext::button('Ganti metode', 'citizen.withdrawal');
        }
        $ctx->say('Pilih tanggal atau kirim DD/MM/YYYY.', $buttons);
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $payload
     */
    private function input(BotContext $ctx, User $actor, array $data, string $text, array $payload): void
    {
        $state = $ctx->conversation->state;
        if ($state === 'citizen.withdrawal.amount') {
            $data['amount'] = BotInput::money($text);
            $this->datePrompt($ctx, 'citizen.withdrawal.date', $data);
        } elseif (in_array($state, ['citizen.pickup.date', 'citizen.withdrawal.date'], true)) {
            $data[$data['flow'] === 'pickup' ? 'selected_date' : 'pickup_date'] = BotInput::date($text);
            $ctx->state('citizen.'.$data['flow'].'.address', $data);
            $address = (string) $actor->customerProfile?->address;
            $buttons = $address === '' ? [] : [BotContext::button('Gunakan alamat profil', 'citizen.input', ['value' => $address])];
            if ($data['flow'] === 'withdrawal' && PayoutAccess::configured()) {
                $buttons[] = BotContext::button('Ganti metode', 'citizen.withdrawal');
            }
            $ctx->say($data['flow'] === 'pickup' ? 'Kirim alamat penjemputan atau gunakan alamat profil.' : 'Kirim lokasi pengambilan uang tunai atau gunakan alamat profil.', $buttons);
        } elseif (in_array($state, ['citizen.pickup.address', 'citizen.withdrawal.address'], true)) {
            $data[$data['flow'] === 'pickup' ? 'address' : 'pickup_location'] = BotInput::text($text, 5, 500);
            if ($data['flow'] === 'pickup') {
                $this->types($ctx, $data);
            } else {
                $this->review($ctx, $actor, $data);
            }
        } elseif ($state === 'citizen.pickup.note') {
            $data['notes'] = BotInput::text($text, 1, 1000);
            $this->extras($ctx, $data);
        } elseif ($state === 'citizen.pickup.photo') {
            if (! isset($payload['upload']) || count($data['photos']) >= 2) {
                throw ValidationException::withMessages(['photo' => 'Kirim maksimal dua foto atau pilih Selesai.']);
            }
            $data['photos'][] = $payload['upload'];
            $ctx->state($state, $data);
            $ctx->say('Foto ditambahkan. Anda boleh mengirim foto berikutnya jika belum mencapai dua foto.', [BotContext::button('Selesai', 'citizen.extra', ['step' => 'review'])]);
        } elseif ($state === 'citizen.pickup.weight') {
            $index = (int) $data['estimate_index'];
            $data['items'][$index]['estimated_weight_kg'] = $text === 'skip' ? null : BotInput::weight($text);
            $ctx->state('citizen.pickup.quantity', $data);
            $ctx->say('Berapa kantong/karung untuk jenis ini? Boleh dilewati.', [BotContext::button('Lewati', 'citizen.input', ['value' => 'skip'])]);
        } elseif ($state === 'citizen.pickup.quantity') {
            if ($text !== 'skip' && (! ctype_digit($text) || (int) $text < 1 || (int) $text > 10000)) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah harus angka bulat positif atau pilih Lewati.']);
            }
            $index = (int) $data['estimate_index'];
            $data['items'][$index]['estimated_quantity'] = $text === 'skip' ? null : (int) $text;
            $data['estimate_index'] = $index + 1;
            if ($data['estimate_index'] < count($data['items'])) {
                $ctx->state('citizen.pickup.weight', $data);
                $ctx->say('Perkiraan berat jenis berikutnya dalam kg?', [BotContext::button('Lewati', 'citizen.input', ['value' => 'skip'])]);
            } else {
                $this->extras($ctx, $data);
            }
        } else {
            $ctx->say('Gunakan tombol pada pesan terbaru atau /menu.');
        }
    }

    /** @param array<string, mixed> $data */
    private function types(BotContext $ctx, array $data, int $page = 1): void
    {
        $types = app(PublicWasteCatalog::class)->types();
        $ctx->state('citizen.pickup.types', $data);
        $buttons = $types->forPage($page, 6)->map(fn ($row): array => BotContext::button($row->name, 'citizen.type', ['id' => $row->id]))->values()->all();
        if ($types->count() > $page * 6) {
            $buttons[] = BotContext::button('Jenis berikutnya', 'citizen.types', ['page' => $page + 1]);
        }
        if (count($data['items']) > 0) {
            $buttons[] = BotContext::button('Selesai pilih jenis', 'citizen.types.done');
        }
        $ctx->say('Pilih jenis sampah. Sudah dipilih: '.count($data['items']).'.', $buttons);
    }

    /** @param array<string, mixed> $data */
    private function extras(BotContext $ctx, array $data): void
    {
        $ctx->state('citizen.pickup.extra', $data);
        $ctx->say('Rincian tambahan boleh dilewati.', [BotContext::button('Tinjau pengajuan', 'citizen.extra', ['step' => 'review']), BotContext::button('Catatan akses', 'citizen.extra', ['step' => 'note']), BotContext::button('Perkiraan', 'citizen.extra', ['step' => 'estimate']), BotContext::button('Tambahkan foto', 'citizen.extra', ['step' => 'photo'])]);
    }

    /** @param array<string, mixed> $data */
    private function review(BotContext $ctx, User $actor, array $data): void
    {
        $text = 'Akun: '.$actor->name."\n";
        if ($data['flow'] === 'pickup') {
            $names = app(PublicWasteCatalog::class)->visibleTypes()->whereIn('id', array_column($data['items'], 'waste_type_id'))->pluck('name')->all();
            $text .= 'Penjemputan: '.$data['selected_date']."\n".$data['address']."\nJenis: ".implode(', ', $names)."\nSaldo mengikuti timbang aktual. Foto: ".count($data['photos']).'.';
        } elseif ($data['flow'] === 'withdrawal') {
            $text .= 'Pencairan tunai '.BotInput::rupiah((int) $data['amount'])."\n".$data['pickup_date'].' · '.$data['pickup_location']."\nDana disisihkan, belum dibayarkan.";
        } else {
            $package = app(GroceryService::class)->activePackages($actor)->findOrFail((int) $data['package_id']);
            $text .= $package->name."\n".$package->contents."\n".BotInput::rupiah($package->value).' akan disisihkan sampai paket diserahkan.';
        }
        $ctx->state('citizen.review', $data);
        $buttons = [BotContext::button('Ya, kirim pengajuan', 'citizen.submit'), BotContext::button('Batal isi', 'cancel-input')];
        if ($data['flow'] === 'withdrawal' && PayoutAccess::configured()) {
            $buttons[] = BotContext::button('Ganti metode', 'citizen.withdrawal');
        }
        $ctx->say($text, $buttons);
    }

    public function balance(User $actor): string
    {
        $account = LedgerAccount::query()->where('user_id', $actor->id)->first();
        $held = $account === null ? 0 : (int) $account->holds()->where('status', BalanceHold::STATUS_ACTIVE)->sum('amount');
        $in = $account === null ? 0 : (int) $account->entries()->where('direction', LedgerEntry::DIRECTION_IN)->sum('amount');
        $out = $account === null ? 0 : (int) $account->entries()->where('direction', LedgerEntry::DIRECTION_OUT)->sum('amount');

        return 'Saldo tersedia: '.BotInput::rupiah($in - $out - $held)."\nDisisihkan: ".BotInput::rupiah($held)."\nMasuk: ".BotInput::rupiah($in).' · Keluar: '.BotInput::rupiah($out);
    }

    /** @return Builder<Deposit>|Builder<WithdrawalRequest>|Builder<GroceryRedemption>|Builder<PickupRequest> */
    private function records(User $actor, string $kind): Builder
    {
        return match ($kind) {
            'deposit' => Deposit::query()->with('correction')->where('customer_id', $actor->id)->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED, Deposit::STATUS_REVERSED]),
            'cash' => app(WithdrawalService::class)->visibleFor($actor),
            'grocery' => app(GroceryService::class)->visibleFor($actor),
            'pickup' => app(PickupService::class)->visibleFor($actor),
            default => abort(422),
        };
    }

    /** @return Builder<WithdrawalRequest>|Builder<GroceryRedemption>|Builder<PickupRequest> */
    private function requests(User $actor, string $kind): Builder
    {
        return match ($kind) {
            'cash' => app(WithdrawalService::class)->visibleFor($actor),
            'grocery' => app(GroceryService::class)->visibleFor($actor),
            'pickup' => app(PickupService::class)->visibleFor($actor),
            default => abort(422),
        };
    }

    /** @param array<string, mixed> $p */
    private function history(BotContext $ctx, User $actor, array $p): void
    {
        if (! isset($p['kind'])) {
            $ctx->say('Pilih riwayat.', [BotContext::button('Setoran', 'citizen.history', ['kind' => 'deposit']), BotContext::button('Pencairan', 'citizen.history', ['kind' => 'cash']), BotContext::button('Sembako', 'citizen.history', ['kind' => 'grocery']), BotContext::button('Penjemputan', 'citizen.history', ['kind' => 'pickup'])]);

            return;
        }
        $kind = (string) $p['kind'];
        if ($kind === 'cash' && ! isset($p['method']) && PayoutAccess::configured()) {
            $ctx->say('Pilih metode pencairan.', [BotContext::button('Tunai', 'citizen.history', ['kind' => 'cash', 'method' => 'cash']),
                BotContext::button('Digital (simulasi)', 'payout.history'), BotContext::button('Menu', 'menu')]);

            return;
        }
        $page = max(1, (int) ($p['page'] ?? 1));
        $records = $this->records($actor, $kind)->orderByDesc('id')->paginate(5, ['*'], 'page', $page);
        $text = [];
        $buttons = [];
        foreach ($records as $i => $row) {
            $number = $row instanceof Deposit ? $row->deposit_number : $row->request_number;
            $text[] = ($i + 1).'. '.$number.' · '.StatusLabel::for($row->status);
            $buttons[] = BotContext::button('Rincian #'.($i + 1), 'citizen.detail', ['kind' => $kind, 'id' => $row->id]);
        }
        if ($records->hasMorePages()) {
            $buttons[] = BotContext::button('Berikutnya', 'citizen.history', ['kind' => $kind, 'page' => $page + 1, 'method' => $kind === 'cash' ? 'cash' : null]);
        }
        $buttons[] = BotContext::button('Menu', 'menu');
        $ctx->say($text === [] ? 'Belum ada riwayat pada pilihan ini.' : implode("\n", $text), $buttons);
    }

    private function active(BotContext $ctx, User $actor): void
    {
        $buttons = [];
        $lines = [];
        if (config('payouts.enabled')) {
            foreach (app(PayoutAccess::class)->visibleFor($actor)->whereNotIn('status', ['succeeded', 'failed', 'cancelled', 'reversed'])->latest('id')->limit(5)->get() as $row) {
                $lines[] = $row->request_number.' · '.$row->label();
                $buttons[] = BotContext::button('Digital #'.count($lines).' (simulasi)', 'payout.detail', ['id' => $row->id]);
            }
        }
        foreach (['pickup' => ['menunggu_petugas', 'dijadwalkan', 'menuju_lokasi', 'dijemput'], 'cash' => ['menunggu_bendahara', 'diproses_bendahara'], 'grocery' => ['menunggu_petugas', 'diproses_petugas', 'siap_diambil']] as $kind => $statuses) {
            foreach ($this->requests($actor, $kind)->whereIn('status', $statuses)->latest('id')->limit(5)->get() as $row) {
                $lines[] = $row->request_number.' · '.StatusLabel::for($row->status);
                $buttons[] = BotContext::button('Lihat #'.count($lines), 'citizen.detail', ['kind' => $kind, 'id' => $row->id]);
            }
        }
        $buttons[] = BotContext::button('Menu', 'menu');
        $ctx->say($lines === [] ? 'Tidak ada pengajuan aktif.' : implode("\n", $lines), $buttons);
    }

    private function detail(BotContext $ctx, User $actor, string $kind, int $id): void
    {
        $row = $this->records($actor, $kind)->findOrFail($id);
        $text = ($row instanceof Deposit ? $row->deposit_number : $row->request_number)."\n".StatusLabel::for($row->status);
        $buttons = [BotContext::button('Menu', 'menu')];
        if ($row instanceof Deposit) {
            $text .= "\n".BotInput::rupiah($row->effectiveTotalValue()).' · '.$row->total_weight_kg.' kg';
            $buttons[] = ['label' => 'Kuitansi web', 'url' => route('citizen.deposit-receipt', $id)];
        } elseif ($row instanceof PickupRequest) {
            $text .= "\n".$row->selected_date->translatedFormat('d M Y')."\n".$row->address;
            if ($row->status === PickupStatus::Waiting) {
                $buttons[] = BotContext::button('Batalkan pengajuan', 'citizen.cancel', ['kind' => $kind, 'id' => $id]);
            }
        } else {
            $text .= "\n".BotInput::rupiah($row instanceof WithdrawalRequest ? $row->amount : $row->value_snapshot);
            $text .= $row->balanceHold?->isActive() ? "\nDana masih disisihkan, belum menjadi saldo keluar." : "\nDana tidak lagi disisihkan untuk pengajuan ini.";
            if (! $row->status->isTerminal() && ! ($row->exception_reported_at && ! $row->exception_resolved_at)) {
                $buttons[] = BotContext::button('Batal / minta pembatalan', 'citizen.cancel', ['kind' => $kind, 'id' => $id]);
            }
            if ($row->receipt_ledger_entry_id !== null) {
                $buttons[] = ['label' => 'Bukti web', 'url' => route($kind === 'cash' ? 'citizen.withdrawal.receipt' : 'citizen.grocery.receipt', $id)];
            }
        }
        $ctx->say($text, $buttons);
    }
}
