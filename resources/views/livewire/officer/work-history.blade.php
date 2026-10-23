<x-slot:title>Riwayat pekerjaan saya</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<section class="grid gap-5" aria-labelledby="work-history-title">
    <x-ui.page-heading id="work-history-title" title="Riwayat pekerjaan saya" description="Setoran dan layanan yang sudah Anda kerjakan." mascot="5" />
    <nav aria-label="Jenis riwayat pekerjaan" class="grid grid-cols-3 gap-2 rounded-lg border border-border bg-surface p-2">
        @foreach (['deposit' => 'Setoran', 'pickup' => 'Penjemputan', 'grocery' => 'Sembako'] as $key => $label)
            <a href="{{ route('officer.history', ['type' => $key]) }}" @if ($type === $key) aria-current="page" @endif
                @class(['inline-flex min-h-touch items-center justify-center rounded-md px-2 text-center text-label font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-focus', 'bg-success-bg text-forest-700' => $type === $key, 'text-text-secondary hover:bg-warm-canvas' => $type !== $key])>{{ $label }}</a>
        @endforeach
    </nav>
    @if ($recordId !== null)
        <a href="{{ route('officer.history', ['type' => $type]) }}" class="inline-flex min-h-touch items-center font-semibold text-forest-700 underline">Lihat semua riwayat jenis ini</a>
    @else
        <x-ui.input name="search" label="Cari nama warga atau nomor transaksi" placeholder="Contoh: nama warga atau nomor transaksi" wire:model.live.debounce.300ms="search" :error="$errors->first('search')" />
    @endif
    <div class="divide-y divide-border rounded-lg border border-border bg-surface px-4 sm:px-5">
        @forelse ($records as $record)
            @php
                $isDeposit = $record instanceof \App\Domain\Deposits\Models\Deposit;
                $isPickup = $record instanceof \App\Domain\Pickups\Models\PickupRequest;
                $status = $isDeposit ? $record->status : $record->status->value;
                $isCancelled = in_array($status, ['dibalik', 'dibatalkan', 'ditolak'], true);
                $time = $isDeposit ? $record->occurred_at : ($isPickup ? ($record->completed_at ?? $record->updated_at) : $record->handed_over_at);
            @endphp
            <article class="grid gap-3 py-5" wire:key="history-{{ $type }}-{{ $record->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="break-all text-title font-bold text-deep-green">{{ $isDeposit ? $record->deposit_number : $record->request_number }}</h2>
                        <p class="mt-1 font-semibold">{{ $record->customer?->name ?? 'Warga' }}</p>
                        <p class="text-body-sm text-text-secondary">{{ $time?->translatedFormat('d F Y, H:i') ?? 'Waktu belum tercatat' }}</p>
                    </div>
                    <x-ui.status-badge :status="$isCancelled ? 'cancelled' : 'success'">{{ \App\Support\StatusLabel::for($status) }}</x-ui.status-badge>
                </div>
                @if ($isDeposit)
                    <p class="amount-tabular text-amount font-bold text-forest-700">Rp {{ number_format($record->effectiveTotalValue(), 0, ',', '.') }}</p>
                    <p class="text-body-sm text-text-secondary">{{ \App\Support\WeightFormatter::format($record->total_weight_kg) }} kg · {{ ['langsung' => 'Setor langsung', 'penjemputan' => 'Penjemputan', 'keliling' => 'Bank Sampah Keliling'][$record->method] ?? 'Setoran' }}</p>
                    @if ($status === 'dibalik')<p class="text-body-sm font-semibold text-terracotta">Setoran sudah dibalik dan tidak lagi menambah saldo warga.</p>@endif
                    <details @if ($recordId !== null) open @endif>
                        <summary class="min-h-touch cursor-pointer py-3 font-semibold text-forest-700">Lihat rincian timbang</summary>
                        <ul class="divide-y divide-border text-body-sm">
                            @foreach ($record->items as $item)
                                <li class="flex justify-between gap-3 py-3"><span>{{ $item->waste_type_name }} · {{ $item->condition_name }}<br>{{ \App\Support\WeightFormatter::format($item->weight_kg) }} kg × Rp {{ number_format($item->price_per_unit, 0, ',', '.') }}</span><span class="shrink-0 amount-tabular font-semibold">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span></li>
                            @endforeach
                        </ul>
                        @if ($record->correction)<p class="mt-2 text-body-sm text-text-secondary">Rincian di atas adalah setoran asli. Nilai akhir mengikuti koreksi resmi.</p>@endif
                    </details>
                @elseif ($isPickup)
                    <p class="text-body-sm text-text-secondary">Jadwal: {{ ($record->scheduled_date ?? $record->selected_date)->translatedFormat('d F Y') }}</p>
                    @if ($record->deposit)
                        <p class="font-semibold">{{ $record->deposit->deposit_number }} · Rp {{ number_format($record->deposit->effectiveTotalValue(), 0, ',', '.') }}</p>
                    @endif
                    <details @if ($recordId !== null) open @endif>
                        <summary class="min-h-touch cursor-pointer py-3 font-semibold text-forest-700">Lihat rincian penjemputan</summary>
                        <p class="text-body-sm">{{ $record->address }}</p>
                        @if ($record->notes)<p class="mt-2 text-body-sm text-text-secondary">{{ $record->notes }}</p>@endif
                    </details>
                @else
                    <p class="amount-tabular text-amount font-bold text-forest-700">Rp {{ number_format($record->value_snapshot, 0, ',', '.') }}</p>
                    <p class="font-semibold">{{ data_get($record->package_snapshot, 'name', 'Paket sembako') }}</p>
                    <details @if ($recordId !== null) open @endif>
                        <summary class="min-h-touch cursor-pointer py-3 font-semibold text-forest-700">Lihat rincian penyerahan</summary>
                        <p class="text-body-sm">{{ data_get($record->package_snapshot, 'contents') }}</p>
                        <p class="mt-2 text-body-sm text-text-secondary">Dicatat oleh {{ $record->handoverActor?->name ?? 'pelaksana layanan' }}. Penyerahan selesai; jangan serahkan paket kedua untuk transaksi ini.</p>
                    </details>
                @endif
            </article>
        @empty
            <div class="py-8 text-center"><h2 class="font-bold text-deep-green">{{ $search !== '' ? 'Riwayat tidak ditemukan' : 'Belum ada riwayat untuk jenis ini' }}</h2><p class="mt-2 text-text-secondary">{{ $search !== '' ? 'Periksa nama atau nomor transaksi yang dicari.' : 'Pekerjaan yang selesai akan muncul di sini.' }}</p></div>
        @endforelse
    </div>
    @if ($records->hasPages()){{ $records->links('components.ui.pagination') }}@endif
</section>
