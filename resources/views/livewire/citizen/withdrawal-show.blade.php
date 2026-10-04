<x-slot:title>Detail pencairan</x-slot:title>
<x-slot:context>Layanan warga</x-slot:context>

<section aria-labelledby="withdrawal-detail-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Pencairan {{ $withdrawal->request_number }}</p>
            <h1 id="withdrawal-detail-title" class="mt-2 text-h1 font-bold text-deep-green">Status Pencairan</h1>
        </div>
        <x-ui.mascot variant="5" bubble="Status saldo terpantau!" bubblePosition="top" class="h-24 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif

    <x-ui.panel title="Ringkasan pencairan" description="Nominal tersnapshot dan tidak dapat diubah.">
        <dl class="grid gap-3 text-body md:grid-cols-2">
            <div class="rounded-lg bg-success-bg px-3 py-2">
                <dt class="text-caption font-medium text-forest-700">Nominal</dt>
                <dd class="mt-0.5 amount-tabular font-bold text-forest-700">Rp{{ number_format($withdrawal->amount, 0, ',', '.') }}</dd>
            </div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Status</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">{{ ucwords(str_replace('_', ' ', $withdrawal->status->value)) }}</dd>
            </div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Tanggal pengambilan</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">{{ $withdrawal->pickup_date?->translatedFormat('d F Y') ?? 'Belum ditetapkan' }}</dd>
            </div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Petugas pembayar</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">{{ $withdrawal->payer?->name ?? 'Belum ditetapkan' }}</dd>
            </div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2 md:col-span-2">
                <dt class="text-caption font-medium text-text-secondary">Lokasi pengambilan</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">{{ $withdrawal->pickup_location }}</dd>
            </div>
        </dl>
    </x-ui.panel>

    <x-ui.panel title="Perjalanan status" description="Hold dilepas pada penolakan, pembatalan, atau kedaluwarsa; saldo keluar hanya saat dibayar.">
        <x-ui.timeline :steps="$withdrawal->statusHistory->map(fn ($history): array => [
            'title'  => ucwords(str_replace('_', ' ', $history->new_status)),
            'note'   => $history->reason ?? '',
            'time'   => $history->occurred_at->translatedFormat('d F Y, H:i'),
            'status' => match(true) {
                $history->new_status === 'sudah_dibayar'                                       => 'success',
                in_array($history->new_status, ['ditolak', 'dibatalkan', 'kedaluwarsa'], true) => 'error',
                default                                                                        => 'in_progress',
            },
        ])->all()" />
    </x-ui.panel>

    @if (in_array($withdrawal->status->value, ['menunggu_verifikasi', 'disetujui', 'siap_diambil'], true))
        <button type="button" wire:click="cancel"
            wire:confirm="Batalkan pengajuan pencairan ini? Hold akan dilepas."
            class="inline-flex min-h-touch items-center justify-center rounded-xl border-2 border-terracotta px-5 text-label font-bold text-terracotta transition hover:bg-danger-bg">
            Batalkan Pengajuan
        </button>
    @endif
</section>
