<x-slot:title>Detail pencairan</x-slot:title>
<x-slot:context>Layanan warga</x-slot:context>

@php
    $withdrawalStages = [
        ['title' => 'Diajukan', 'description' => 'Pengajuan tercatat dan menunggu pemeriksaan.', 'icon' => 'file-check', 'statuses' => ['menunggu_verifikasi']],
        ['title' => 'Disetujui', 'description' => 'Nominal disetujui dan dana yang ditahan tetap tercatat.', 'icon' => 'clipboard-check', 'statuses' => ['disetujui']],
        ['title' => 'Siap diambil', 'description' => 'Pembayaran sudah siap diproses bendahara.', 'icon' => 'calendar-days', 'statuses' => ['siap_diambil']],
        ['title' => 'Sudah dibayar', 'description' => 'Penerimaan diverifikasi dan bukti pembayaran tersimpan.', 'icon' => 'banknote', 'statuses' => ['sudah_dibayar']],
    ];
    $withdrawalStatus = $withdrawal->status->value;
    $withdrawalTerminalStep = in_array($withdrawalStatus, ['ditolak', 'dibatalkan', 'kedaluwarsa'], true)
        ? [
            'status' => $withdrawalStatus,
            'title' => match ($withdrawalStatus) {
                'ditolak' => 'Pengajuan ditolak',
                'dibatalkan' => 'Pengajuan dibatalkan',
                default => 'Pengajuan kedaluwarsa',
            },
            'description' => 'Tahap berikutnya tidak dilanjutkan dan dana yang ditahan mengikuti aturan status ini.',
        ]
        : null;
@endphp

<section aria-labelledby="withdrawal-detail-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Pencairan {{ $withdrawal->request_number }}</p>
            <h1 id="withdrawal-detail-title" class="mt-2 text-h1 font-bold text-deep-green">Status pencairan</h1>
        </div>
        <x-ui.mascot variant="5" bubble="Status saldo terpantau!" bubblePosition="top" class="h-24 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif

    <x-ui.panel title="Ringkasan pencairan" description="Nominal dicatat saat pengajuan dan tidak dapat diubah.">
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

    <x-ui.panel title="Tahapan pencairan" description="Tahap yang belum tercapai tetap terlihat agar status dan langkah berikutnya mudah dipahami.">
        <x-ui.status-stepper
            :steps="$withdrawalStages"
            :current-status="$withdrawalStatus"
            :history="$withdrawal->statusHistory"
            :terminal-step="$withdrawalTerminalStep"
            :completed-statuses="['sudah_dibayar']"
            label="Tahapan pencairan"
        />
    </x-ui.panel>

    @if ($withdrawal->status->value === 'menunggu_verifikasi')
        <button type="button" wire:click="cancel"
            wire:confirm="Batalkan pengajuan pencairan ini? Dana yang ditahan akan dilepas."
            class="inline-flex min-h-touch items-center justify-center rounded-xl border-2 border-terracotta px-5 text-label font-bold text-terracotta transition hover:bg-danger-bg">
            Batalkan pengajuan
        </button>
    @endif
</section>
