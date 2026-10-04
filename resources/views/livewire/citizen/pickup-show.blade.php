<x-slot:title>Detail penjemputan</x-slot:title>
<x-slot:context>Layanan warga</x-slot:context>

<section aria-labelledby="pickup-detail-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Penjemputan {{ $pickup->request_number }}</p>
            <h1 id="pickup-detail-title" class="mt-2 text-h1 font-bold text-deep-green">Status Penjemputan</h1>
        </div>
        <x-ui.mascot variant="7" bubble="Jadwal terpantau!" bubblePosition="top" class="h-24 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif

    <x-ui.panel title="Ringkasan pengajuan" :description="$pickup->address">
        <dl class="grid gap-3 text-body md:grid-cols-2">
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Status</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">{{ ucwords(str_replace('_', ' ', $pickup->status->value)) }}</dd>
            </div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Tanggal pilihan</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">{{ $pickup->selected_date->translatedFormat('d F Y') }}</dd>
            </div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Tanggal jadwal</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">{{ $pickup->scheduled_date?->translatedFormat('d F Y') ?? 'Menunggu pemeriksaan' }}</dd>
            </div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Perkiraan berat</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">
                    {{ $pickup->estimated_weight_kg ?? '—' }} kg
                    <span class="text-caption font-normal text-text-secondary">(bukan saldo)</span>
                </dd>
            </div>
        </dl>
    </x-ui.panel>

    <x-ui.panel title="Perjalanan status" description="Setoran hanya dibuat setelah penimbangan aktual berhasil difinalkan.">
        <x-ui.timeline :steps="$pickup->statusHistory->map(fn ($history): array => [
            'title'  => ucwords(str_replace('_', ' ', $history->new_status)),
            'note'   => $history->reason ?? '',
            'time'   => $history->occurred_at->translatedFormat('d F Y, H:i'),
            'status' => match($history->new_status) {
                'selesai'              => 'success',
                'ditolak', 'dibatalkan' => 'error',
                default                => 'in_progress',
            },
        ])->all()" />
    </x-ui.panel>

    @if (in_array($pickup->status->value, ['menunggu_pemeriksaan', 'diterima', 'dijadwalkan'], true))
        <button type="button" wire:click="cancel"
            wire:confirm="Batalkan pengajuan penjemputan ini?"
            class="inline-flex min-h-touch items-center justify-center rounded-xl border-2 border-terracotta px-5 text-label font-bold text-terracotta transition hover:bg-danger-bg">
            Batalkan Pengajuan
        </button>
    @endif
</section>
