<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\CustomersRegions\Queries\SearchCustomers;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Domain\Operations\Queries\OfficerWorkHistory;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Queries\PublicWasteCatalog;
use App\Domain\WasteMaster\Services\ResolveWastePrice;
use App\Models\User;
use App\Support\StatusLabel;
use Illuminate\Validation\ValidationException;

final class OfficerTelegramBot
{
    /** @param array<string, mixed> $p
     * @param  array<string, mixed>  $payload
     */
    public function handle(BotContext $ctx, string $command, array $p, array $payload): void
    {
        $actor = $ctx->actor('petugas');
        if ($command === 'officer.history') {
            $this->history($ctx, $actor, $p);

            return;
        }
        if (str_starts_with($command, 'officer.mobile')) {
            $this->mobile($ctx, $actor, $command, $p);

            return;
        }
        if ($command === 'officer.pickups') {
            $this->pickups($ctx, $actor, $p);

            return;
        }
        if (str_starts_with($command, 'officer.pickup.') && $command !== 'officer.pickup.weigh') {
            $this->pickup($ctx, $actor, $command, $p);

            return;
        }
        if ($command === 'officer.drafts') {
            $rows = Deposit::query()->with('customer')->where('staff_id', $actor->id)->where('status', Deposit::STATUS_DRAFT)->latest('id')->limit(10)->get();
            $ctx->state('menu');
            $ctx->say($rows->isEmpty() ? 'Tidak ada draf setoran.' : 'Pilih draf milik Anda.', $rows->map(fn ($row): array => BotContext::button($row->customer?->name.' · '.$row->deposit_number, 'officer.resume', ['id' => $row->id]))->all());

            return;
        }
        $actor = $ctx->actor('petugas');
        if ($command === 'officer.deposit') {
            $mobileId = isset($p['mobile']) ? (int) $p['mobile'] : null;
            if ($mobileId !== null) {
                $this->ownedMobile($actor, $mobileId);
            }
            $ctx->state('officer.customer', ['mobile_id' => $mobileId, 'items' => [], 'photos' => []]);
            $ctx->say('Ketik nama atau nomor nasabah. Jika hasil lebih dari satu, pilih nama dan nomor yang benar.');

            return;
        }
        $data = $ctx->conversation->data;
        if ($command === 'officer.customer') {
            $customer = User::query()->findOrFail((int) $p['id']);
            $mobile = empty($data['mobile_id']) ? null : $this->ownedMobile($actor, (int) $data['mobile_id']);
            $draft = app(DepositService::class)->createDraft($actor, $customer, $mobile === null ? 'langsung' : 'keliling', mobileService: $mobile);
            $data['draft_id'] = $draft->id;
            $data['customer_id'] = $customer->id;
            $this->types($ctx, $actor, $data);
        } elseif ($command === 'officer.pickup.weigh') {
            $pickup = $this->ownedPickup($actor, (int) $p['id']);
            abort_unless($pickup->assigned_staff_id === $actor->id && $pickup->status === PickupStatus::PickedUp, 403);
            $data = ['pickup_id' => $pickup->id, 'customer_id' => $pickup->customer_id, 'items' => [], 'photos' => []];
            $this->types($ctx, $actor, $data);
        } elseif ($command === 'officer.resume') {
            $draft = Deposit::query()->with('items')->where('staff_id', $actor->id)->where('status', Deposit::STATUS_DRAFT)->findOrFail((int) $p['id']);
            if ($draft->method === 'penjemputan') {
                throw ValidationException::withMessages(['draft' => 'Buka penjemputan terkait dari menu Penjemputan untuk melanjutkan hasil timbang.']);
            }
            $data = ['draft_id' => $draft->id, 'customer_id' => $draft->customer_id, 'mobile_id' => $draft->mobile_service_id, 'photos' => [], 'items' => $draft->items->map(fn ($item): array => ['waste_type_id' => $item->waste_type_id, 'condition_id' => $item->waste_condition_id, 'weight_kg' => $item->weight_kg])->all()];
            empty($data['items']) ? $this->types($ctx, $actor, $data) : $this->review($ctx, $actor, $data);
        } elseif ($command === 'officer.types') {
            $this->types($ctx, $actor, $data, max(1, (int) ($p['page'] ?? 1)));
        } elseif ($command === 'officer.type') {
            $type = app(PublicWasteCatalog::class)->visibleTypes()->with('conditions')->findOrFail((int) $p['id']);
            if (! empty($data['mobile_id']) && ! $this->ownedMobile($actor, (int) $data['mobile_id'])->wasteTypes()->whereKey($type->id)->exists()) {
                abort(403);
            }
            $data['pending_type'] = $type->id;
            $ctx->state('officer.condition', $data);
            $ctx->say('Pilih kondisi '.$type->name.'.', $type->conditions->where('is_active', true)->map(fn ($condition): array => BotContext::button($condition->name, 'officer.condition', ['id' => $condition->id]))->values()->all());
        } elseif ($command === 'officer.condition') {
            $type = app(PublicWasteCatalog::class)->visibleTypes()->findOrFail((int) $data['pending_type']);
            $data['pending_condition'] = $type->conditions()->where('is_active', true)->findOrFail((int) $p['id'])->id;
            $ctx->state('officer.weight', $data);
            $ctx->say('Masukkan berat aktual dalam kg. Contoh: 1,25.');
        } elseif ($command === 'officer.review') {
            $this->review($ctx, $actor, $data);
        } elseif ($command === 'officer.photo') {
            $ctx->state('officer.photo', $data);
            $ctx->say('Kirim satu foto bukti jika ada. Foto tidak wajib.', [BotContext::button('Tinjau setoran', 'officer.review')]);
        } elseif ($command === 'officer.finalize') {
            abort_unless($ctx->conversation->state === 'officer.review', 422);
            $quote = $this->quote($data);
            if (($data['quote'] ?? null) !== $quote) {
                $ctx->say('Harga berubah. Periksa kembali ringkasan terbaru sebelum mencatat.');
                $this->review($ctx, $actor, $data);

                return;
            }
            $photos = app(TelegramFiles::class)->uploads($ctx->connection(), $data['photos']);
            if (! empty($data['pickup_id'])) {
                $pickup = $this->ownedPickup($actor, (int) $data['pickup_id']);
                if ($pickup->status === PickupStatus::Completed) {
                    $deposit = $pickup->deposit;
                } else {
                    $pickup = app(PickupService::class)->complete($actor, $pickup, $data['items'], $ctx->key('pickup_complete'), $photos[0] ?? null);
                    $deposit = $pickup->deposit;
                }
            } else {
                $deposit = Deposit::query()->with('correction')->where('staff_id', $actor->id)->findOrFail((int) $data['draft_id']);
                if ($deposit->status === Deposit::STATUS_DRAFT) {
                    $mobile = empty($data['mobile_id']) ? null : $this->ownedMobile($actor, (int) $data['mobile_id']);
                    $deposit = app(DepositService::class)->finalize($actor, $deposit, $ctx->key('deposit'), $data['items'], $photos[0] ?? null, $mobile);
                }
            }
            abort_unless($deposit instanceof Deposit, 422);
            $ctx->state('menu');
            $ctx->say('Setoran tercatat: '.$deposit->deposit_number."\n".StatusLabel::for($deposit->status).' · '.BotInput::rupiah($deposit->effectiveTotalValue())."\nJangan catat ulang setoran yang sama.", [BotContext::button('Riwayat pekerjaan', 'officer.history', ['kind' => 'deposit']), BotContext::button('Menu', 'menu')]);
        } else {
            $this->input($ctx, $actor, $payload);
        }
    }

    /** @param array<string, mixed> $payload */
    private function input(BotContext $ctx, User $actor, array $payload): void
    {
        $data = $ctx->conversation->data;
        $text = trim((string) ($payload['text'] ?? ''));
        if ($ctx->conversation->state === 'officer.customer') {
            $mobile = empty($data['mobile_id']) ? null : $this->ownedMobile($actor, (int) $data['mobile_id']);
            $matches = app(SearchCustomers::class)->search($actor, $text, 10, $mobile);
            $ctx->say($matches === [] ? 'Warga tidak ditemukan. Periksa nama/nomor dan area tugas.' : 'Pilih warga yang namanya sudah sesuai.', array_map(fn ($match): array => BotContext::button($match->name.' · '.$match->maskedNumber(), 'officer.customer', ['id' => $match->userId]), $matches));
        } elseif ($ctx->conversation->state === 'officer.weight') {
            if (count($data['items']) >= 20) {
                throw ValidationException::withMessages(['items' => 'Maksimal dua puluh baris timbang.']);
            }
            $data['items'][] = ['waste_type_id' => (int) $data['pending_type'], 'condition_id' => (int) $data['pending_condition'], 'weight_kg' => BotInput::weight($text)];
            if (! empty($data['draft_id'])) {
                $draft = Deposit::query()->where('staff_id', $actor->id)->findOrFail((int) $data['draft_id']);
                app(DepositService::class)->replaceDraftItems($actor, $draft, $data['items']);
            }
            $this->review($ctx, $actor, $data);
        } elseif ($ctx->conversation->state === 'officer.photo') {
            if (! isset($payload['upload'])) {
                throw ValidationException::withMessages(['photo' => 'Kirim satu foto atau pilih Tinjau setoran.']);
            }
            $data['photos'] = [$payload['upload']];
            $this->review($ctx, $actor, $data);
        } elseif (in_array($ctx->conversation->state, ['officer.pickup.release', 'officer.pickup.exception'], true)) {
            $pickup = $this->ownedPickup($actor, (int) $data['pickup_id']);
            $reason = BotInput::text($text, 10, 1000);
            $ctx->conversation->state === 'officer.pickup.release'
                ? app(PickupService::class)->release($actor, $pickup, $reason)
                : app(PickupService::class)->reportException($actor, $pickup, $reason);
            $ctx->state('menu');
            $this->pickup($ctx, $actor, 'officer.pickup.show', ['id' => $pickup->id]);
        } else {
            $ctx->say('Gunakan tombol pada pesan terbaru atau /menu.');
        }
    }

    /** @param array<string, mixed> $data */
    private function types(BotContext $ctx, User $actor, array $data, int $page = 1): void
    {
        $types = app(PublicWasteCatalog::class)->types();
        if (! empty($data['mobile_id'])) {
            $allowed = $this->ownedMobile($actor, (int) $data['mobile_id'])->wasteTypes()->pluck('waste_types.id')->all();
            $types = $types->whereIn('id', $allowed);
        }
        $ctx->state('officer.types', $data);
        $buttons = $types->forPage($page, 6)->map(fn ($type): array => BotContext::button($type->name, 'officer.type', ['id' => $type->id]))->values()->all();
        if ($types->count() > $page * 6) {
            $buttons[] = BotContext::button('Jenis berikutnya', 'officer.types', ['page' => $page + 1]);
        }
        if (! empty($data['items'])) {
            $buttons[] = BotContext::button('Tinjau', 'officer.review');
        }
        $ctx->say('Pilih jenis sampah yang ditimbang.', $buttons);
    }

    /** @param array<string, mixed> $data
     * @return array{lines: list<string>, total: int}
     */
    private function quote(array $data): array
    {
        if (empty($data['items'])) {
            throw ValidationException::withMessages(['items' => 'Tambahkan minimal satu hasil timbang.']);
        }
        $at = empty($data['draft_id']) ? now('Asia/Jakarta') : Deposit::findOrFail((int) $data['draft_id'])->occurred_at;
        $total = 0;
        $lines = [];
        foreach ($data['items'] as $item) {
            $type = app(PublicWasteCatalog::class)->visibleTypes()->findOrFail((int) $item['waste_type_id']);
            $condition = $type->conditions()->where('is_active', true)->findOrFail((int) $item['condition_id']);
            $price = app(ResolveWastePrice::class)->resolve($type, $condition->id, $at);
            $snapshot = $price->snapshot()->withWeight((string) $item['weight_kg']);
            $total += $snapshot->subtotal;
            $lines[] = $type->name.' · '.$condition->name.' · '.$item['weight_kg'].' kg × '.BotInput::rupiah($price->price).' = '.BotInput::rupiah($snapshot->subtotal);
        }

        return ['lines' => $lines, 'total' => $total];
    }

    /** @param array<string, mixed> $data */
    private function review(BotContext $ctx, User $actor, array $data): void
    {
        $quote = $this->quote($data);
        $data['quote'] = $quote;
        $customer = User::query()->findOrFail((int) $data['customer_id']);
        $ctx->state('officer.review', $data);
        $ctx->say('Petugas: '.$actor->name."\nWarga: ".$customer->name."\n".implode("\n", $quote['lines'])."\nTotal: ".BotInput::rupiah($quote['total'])."\nPeriksa hasil timbang sebelum mencatat.", [BotContext::button('Tambah jenis', 'officer.types'), BotContext::button('Foto opsional', 'officer.photo'), BotContext::button('Data benar — catat setoran', 'officer.finalize'), BotContext::button('Batal isi', 'cancel-input')]);
    }

    /** @param array<string, mixed> $p */
    private function pickups(BotContext $ctx, User $actor, array $p): void
    {
        $mine = (bool) ($p['mine'] ?? false);
        $rows = app(PickupService::class)->visibleFor($actor)->with('customer')
            ->when($mine, fn ($q) => $q->where('assigned_staff_id', $actor->id)->whereIn('status', [PickupStatus::Scheduled, PickupStatus::EnRoute, PickupStatus::PickedUp]))
            ->when(! $mine, fn ($q) => $q->where('status', PickupStatus::Waiting))->orderBy('selected_date')->paginate(5, ['*'], 'page', max(1, (int) ($p['page'] ?? 1)));
        $ctx->state('menu');
        $buttons = $rows->getCollection()->map(fn ($row): array => BotContext::button($row->customer->name, 'officer.pickup.show', ['id' => $row->id]))->all();
        $buttons[] = BotContext::button($mine ? 'Menunggu' : 'Tugas saya', 'officer.pickups', ['mine' => ! $mine]);
        if ($rows->hasMorePages()) {
            $buttons[] = BotContext::button('Berikutnya', 'officer.pickups', ['mine' => $mine, 'page' => $rows->currentPage() + 1]);
        }
        $ctx->say($rows->isEmpty() ? 'Tidak ada penjemputan pada pilihan ini.' : $rows->getCollection()->map(fn ($row): string => $row->request_number.' · '.$row->customer->name.' · '.$row->selected_date->format('d/m/Y'))->implode("\n"), $buttons);
    }

    private function ownedPickup(User $actor, int $id): PickupRequest
    {
        return app(PickupService::class)->visibleFor($actor)->with(['customer', 'items', 'deposit'])->findOrFail($id);
    }

    /** @param array<string, mixed> $p */
    private function pickup(BotContext $ctx, User $actor, string $command, array $p): void
    {
        $pickup = $this->ownedPickup($actor, (int) $p['id']);
        if ($command !== 'officer.pickup.show') {
            $ctx->actor('petugas');
            if (in_array($command, ['officer.pickup.release', 'officer.pickup.exception'], true)) {
                $ctx->state($command, ['pickup_id' => $pickup->id]);
                $ctx->say('Jelaskan alasan/kendala minimal 10 karakter.');

                return;
            }
            $pickup = match ($command) {
                'officer.pickup.claim' => app(PickupService::class)->claim($actor, $pickup),
                'officer.pickup.begin' => app(PickupService::class)->begin($actor, $pickup),
                'officer.pickup.collected' => app(PickupService::class)->markPickedUp($actor, $pickup),
                default => abort(422),
            };
        }
        $ctx->state('menu');
        $buttons = [];
        $locked = $pickup->getAttribute('exception_reported_at') && ! $pickup->getAttribute('exception_resolved_at');
        if (! $locked && $pickup->status === PickupStatus::Waiting) {
            $buttons[] = BotContext::button('Ambil tugas', 'officer.pickup.claim', ['id' => $pickup->id]);
        } elseif (! $locked && $pickup->assigned_staff_id === $actor->id && ! $pickup->isTerminal()) {
            $next = match ($pickup->status) {
                PickupStatus::Scheduled => BotContext::button('Mulai perjalanan', 'officer.pickup.begin', ['id' => $pickup->id]),
                PickupStatus::EnRoute => BotContext::button('Sampah sudah dijemput', 'officer.pickup.collected', ['id' => $pickup->id]),
                default => BotContext::button('Catat hasil timbang', 'officer.pickup.weigh', ['id' => $pickup->id]),
            };
            $buttons[] = $next;
            if ($pickup->status !== PickupStatus::PickedUp) {
                $buttons[] = BotContext::button('Lepas tugas', 'officer.pickup.release', ['id' => $pickup->id]);
            }
            $buttons[] = BotContext::button('Laporkan ketidakpastian', 'officer.pickup.exception', ['id' => $pickup->id]);
        }
        $buttons[] = BotContext::button('Menu', 'menu');
        $ctx->say($pickup->request_number."\n".$pickup->customer->name."\n".$pickup->address."\n".$pickup->selected_date->format('d/m/Y').' · '.StatusLabel::for($pickup->status).($locked ? "\nTugas dikunci. Jangan ulangi serah fisik." : ''), $buttons);
    }

    private function ownedMobile(User $actor, int $id): MobileService
    {
        return MobileService::query()->whereHas('staff', fn ($q) => $q->whereKey($actor->id))->findOrFail($id);
    }

    /** @param array<string, mixed> $p */
    private function mobile(BotContext $ctx, User $actor, string $command, array $p): void
    {
        $service = isset($p['id']) ? $this->ownedMobile($actor, (int) $p['id']) : null;
        if ($command === 'officer.mobile') {
            $rows = MobileService::query()->whereHas('staff', fn ($q) => $q->whereKey($actor->id))->orderByDesc('starts_at')->paginate(5, ['*'], 'page', max(1, (int) ($p['page'] ?? 1)));
            $ctx->state('menu');
            $buttons = $rows->getCollection()->map(fn ($row): array => BotContext::button($row->point, 'officer.mobile.show', ['id' => $row->id]))->all();
            if ($rows->hasMorePages()) {
                $buttons[] = BotContext::button('Berikutnya', 'officer.mobile', ['page' => $rows->currentPage() + 1]);
            }
            $ctx->say($rows->isEmpty() ? 'Belum ada jadwal yang menugaskan Anda.' : 'Pilih layanan keliling.', $buttons);

            return;
        }
        abort_unless($service instanceof MobileService, 404);
        $operations = app(MobileServiceService::class);
        if (in_array($command, ['officer.mobile.open', 'officer.mobile.close.confirm'], true)) {
            $ctx->actor('petugas');
            $service = $operations->transition($actor, $service, $command === 'officer.mobile.open' ? MobileServiceStatus::Open : MobileServiceStatus::Closed);
        }
        $ctx->state('menu');
        $recap = $operations->recap($actor, $service);
        $buttons = [BotContext::button('Menu', 'menu')];
        if ($command === 'officer.mobile.close') {
            $buttons[] = BotContext::button('Ya, tutup layanan', 'officer.mobile.close.confirm', ['id' => $service->id]);
        } elseif ($service->status === MobileServiceStatus::Published) {
            $buttons[] = BotContext::button('Buka layanan', 'officer.mobile.open', ['id' => $service->id]);
        } elseif ($service->status === MobileServiceStatus::Open) {
            if ($service->isOpen() && ! $service->isFull()) {
                $buttons[] = BotContext::button('Catat setoran keliling', 'officer.deposit', ['mobile' => $service->id]);
            }
            $buttons[] = BotContext::button('Tinjau dan tutup', 'officer.mobile.close', ['id' => $service->id]);
        }
        $ctx->say($service->point.' · '.StatusLabel::for($service->status)."\n".$service->starts_at->format('d/m/Y H:i').'–'.$service->ends_at->format('H:i')."\nSisa kapasitas: ".$service->remainingCapacity()."\nRekap: ".$recap['transaction_count'].' setoran · '.$recap['total_weight_kg'].' kg · '.BotInput::rupiah($recap['total_value']), $buttons);
    }

    /** @param array<string, mixed> $p */
    private function history(BotContext $ctx, User $actor, array $p): void
    {
        $ctx->state('menu');
        if (! isset($p['kind'])) {
            $ctx->say('Pilih riwayat pekerjaan sendiri.', [BotContext::button('Setoran', 'officer.history', ['kind' => 'deposit']), BotContext::button('Penjemputan', 'officer.history', ['kind' => 'pickup']), BotContext::button('Sembako', 'officer.history', ['kind' => 'grocery'])]);

            return;
        }
        $history = app(OfficerWorkHistory::class);
        $kind = (string) $p['kind'];
        $rows = match ($kind) {
            'deposit' => $history->deposits($actor), 'pickup' => $history->pickups($actor), 'grocery' => $history->groceries($actor), default => abort(422)
        };
        $rows = $rows->latest('id')->paginate(5, ['*'], 'page', max(1, (int) ($p['page'] ?? 1)));
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = ($kind === 'deposit' ? $row->deposit_number.' · '.BotInput::rupiah($row->effectiveTotalValue()) : $row->request_number).' · '.StatusLabel::for($row->status);
        }
        $buttons = [BotContext::button('Menu', 'menu')];
        if ($rows->hasMorePages()) {
            $buttons[] = BotContext::button('Berikutnya', 'officer.history', ['kind' => $kind, 'page' => $rows->currentPage() + 1]);
        }
        $ctx->say($lines === [] ? 'Belum ada riwayat pekerjaan pada pilihan ini.' : implode("\n", $lines), $buttons);
    }
}
