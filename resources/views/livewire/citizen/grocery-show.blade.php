<x-slot:title>Detail sembako</x-slot:title>
<x-slot:context>Layanan warga</x-slot:context>

@php
    $groceryStages = [
        ['title' => 'Diajukan', 'description' => 'Permintaan tercatat dan menunggu verifikasi.', 'icon' => 'file-check', 'statuses' => ['menunggu_verifikasi']],
        ['title' => 'Disetujui', 'description' => 'Permintaan disetujui dan paket masuk tahap persiapan.', 'icon' => 'clipboard-check', 'statuses' => ['disetujui']],
        ['title' => 'Sedang disiapkan', 'description' => 'Paket sedang disiapkan sesuai rekaman saat pengajuan.', 'icon' => 'package-open', 'statuses' => ['sedang_disiapkan']],
        ['title' => 'Siap diambil', 'description' => 'Paket tersedia untuk diserahkan kepada penerima.', 'icon' => 'calendar-days', 'statuses' => ['siap_diambil']],
        ['title' => 'Selesai diserahkan', 'description' => 'Paket diserahkan dan bukti penyerahan tersimpan.', 'icon' => 'package-check', 'statuses' => ['selesai']],
    ];
    $groceryStatus = $redemption->status->value;
    $groceryTerminalStep = in_array($groceryStatus, ['ditolak', 'dibatalkan', 'kedaluwarsa'], true)
        ? [
            'status' => $groceryStatus,
            'title' => match ($groceryStatus) {
                'ditolak' => 'Permintaan ditolak',
                'dibatalkan' => 'Permintaan dibatalkan',
                default => 'Permintaan kedaluwarsa',
            },
            'description' => 'Tahap persiapan dan penyerahan tidak dilanjutkan.',
        ]
        : null;
@endphp

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

    <x-ui.panel title="Rekaman paket" description="Data ini tidak berubah walaupun master paket diperbarui setelah pengajuan.">
        <dl class="grid gap-3 text-body md:grid-cols-2">
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Paket</dt>
                <dd class="mt-0.5 font-semibold text-deep-green">{{ $redemption->package_snapshot['name'] ?? 'Paket sembako' }}</dd>
            </div>
            <div class="rounded-lg bg-warning-bg px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Nilai saat pengajuan</dt>
                <dd class="mt-0.5 amount-tabular font-bold text-deep-green">Rp{{ number_format($redemption->value_snapshot, 0, ',', '.') }}</dd>
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

    <x-ui.panel title="Tahapan penukaran" description="Persetujuan terpisah dari serah-terima. Dana yang ditahan dilepas pada tahap akhir tanpa menjadi saldo keluar.">
        <x-ui.status-stepper
            :steps="$groceryStages"
            :current-status="$groceryStatus"
            :history="$redemption->statusHistory"
            :terminal-step="$groceryTerminalStep"
            :completed-statuses="['selesai']"
            label="Tahapan penukaran sembako"
        />
    </x-ui.panel>

    @if ($redemption->status->value === 'siap_diambil')
        <x-ui.panel title="Langkah selanjutnya" description="Paket Anda sudah siap untuk diserahkan.">
            <p class="text-body text-text-secondary">Bawa kartu nasabah atau siapkan nomor nasabah Anda, lalu tunggu petugas melakukan serah-terima paket.</p>
        </x-ui.panel>
    @endif

    @if ($redemption->status->value === 'selesai')
        <a href="{{ route('citizen.grocery.receipt', $redemption) }}"
            class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700">
            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            Buka Bukti Penyerahan
        </a>
    @endif

    @if ($redemption->status->value === 'menunggu_verifikasi')
        <button type="button" wire:click="cancel"
            wire:confirm="Batalkan pengajuan sembako ini? Dana yang ditahan akan dilepas bila ada."
            class="inline-flex min-h-touch items-center justify-center rounded-xl border-2 border-terracotta px-5 text-label font-bold text-terracotta transition hover:bg-danger-bg">
            Batalkan pengajuan
        </button>
    @endif
</section>
