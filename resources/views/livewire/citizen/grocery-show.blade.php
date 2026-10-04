<x-slot:title>Detail sembako</x-slot:title>
<x-slot:context>Layanan warga</x-slot:context>

<section aria-labelledby="grocery-detail-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Penukaran {{ $redemption->request_number }}</p>
            <h1 id="grocery-detail-title" class="mt-2 text-h1 font-bold text-deep-green">Status Penukaran</h1>
        </div>
        <x-ui.mascot variant="3" bubble="Paket sedang diproses!" bubblePosition="top" class="h-24 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif

    <x-ui.panel title="Snapshot paket" description="Data ini tidak berubah walaupun master paket diperbarui setelah pengajuan.">
        <dl class="grid gap-3 text-body md:grid-cols-2">
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Paket</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">{{ $redemption->package_snapshot['name'] ?? 'Paket sembako' }}</dd>
            </div>
            <div class="rounded-lg bg-warning-bg px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Nilai Snapshot</dt>
                <dd class="mt-0.5 amount-tabular font-bold text-deep-green">Rp{{ number_format($redemption->value_snapshot, 0, ',', '.') }}</dd>
            </div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Sumber</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">{{ $redemption->source_type->value === 'saldo' ? 'Saldo warga' : 'Bantuan gratis' }}</dd>
            </div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Status</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">{{ ucwords(str_replace('_', ' ', $redemption->status->value)) }}</dd>
            </div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2 md:col-span-2">
                <dt class="text-caption font-medium text-text-secondary">Isi Paket</dt>
                <dd class="mt-0.5 whitespace-pre-line font-semibold text-deep-green">{{ $redemption->package_snapshot['contents'] ?? 'Tidak tersedia' }}</dd>
            </div>
        </dl>
    </x-ui.panel>

    <x-ui.panel title="Perjalanan status" description="Approval terpisah dari handover. Hold dilepas pada terminal tanpa saldo keluar.">
        <x-ui.timeline :steps="$redemption->statusHistory->map(fn ($history): array => [
            'title'  => ucwords(str_replace('_', ' ', $history->new_status)),
            'note'   => $history->reason ?? '',
            'time'   => $history->occurred_at->translatedFormat('d F Y, H:i'),
            'status' => match(true) {
                $history->new_status === 'selesai'                                              => 'success',
                in_array($history->new_status, ['ditolak', 'dibatalkan', 'kedaluwarsa'], true) => 'error',
                default                                                                        => 'in_progress',
            },
        ])->all()" />
    </x-ui.panel>

    @if ($redemption->status->value === 'selesai')
        <a href="{{ route('citizen.grocery.receipt', $redemption) }}"
            class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700">
            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            Buka Bukti Penyerahan
        </a>
    @endif

    @if ($redemption->status->value === 'menunggu_verifikasi')
        <button type="button" wire:click="cancel"
            wire:confirm="Batalkan pengajuan sembako ini? Hold akan dilepas bila ada."
            class="inline-flex min-h-touch items-center justify-center rounded-xl border-2 border-terracotta px-5 text-label font-bold text-terracotta transition hover:bg-danger-bg">
            Batalkan Pengajuan
        </button>
    @endif
</section>
