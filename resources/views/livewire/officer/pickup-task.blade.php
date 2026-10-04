<x-slot:title>Tugas penjemputan</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

@php
    $pickupStages = [
        ['title' => 'Diajukan', 'description' => 'Permintaan tercatat dan menunggu pemeriksaan petugas.', 'icon' => 'file-check', 'statuses' => ['menunggu_pemeriksaan']],
        ['title' => 'Diterima', 'description' => 'Permintaan diterima untuk dijadwalkan.', 'icon' => 'clipboard-check', 'statuses' => ['diterima']],
        ['title' => 'Dijadwalkan', 'description' => 'Tanggal layanan sudah ditetapkan.', 'icon' => 'calendar-days', 'statuses' => ['dijadwalkan']],
        ['title' => 'Menuju lokasi', 'description' => 'Petugas sedang menuju alamat penjemputan.', 'icon' => 'truck', 'statuses' => ['menuju_lokasi']],
        ['title' => 'Dijemput', 'description' => 'Sampah sedang diperiksa dan ditimbang di lokasi.', 'icon' => 'map-pin', 'statuses' => ['dijemput']],
        ['title' => 'Selesai', 'description' => 'Penjemputan selesai dan setoran aktual dicatat.', 'icon' => 'circle-check', 'statuses' => ['selesai']],
    ];
    $pickupStatus = $pickup->status->value;
    $pickupTerminalStep = in_array($pickupStatus, ['ditolak', 'dibatalkan'], true)
        ? [
            'status' => $pickupStatus,
            'title' => $pickupStatus === 'ditolak' ? 'Permintaan ditolak' : 'Permintaan dibatalkan',
            'description' => 'Tahap penjemputan berikutnya tidak dilanjutkan.',
        ]
        : null;
@endphp

<section aria-labelledby="pickup-task-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Tugas {{ $pickup->request_number }}</p>
            <h1 id="pickup-task-title" class="mt-2 text-h1 font-bold text-deep-green">Penjemputan Lapangan</h1>
            <p class="mt-3 text-body text-text-secondary">
                <svg viewBox="0 0 24 24" class="mr-1 inline size-4 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $pickup->address }}
            </p>
        </div>
        <x-ui.mascot variant="8" bubble="Menuju lokasi!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if ($errors->any())
        <div id="pickup-task-errors" tabindex="-1" role="alert" class="rounded-lg border border-terracotta bg-danger-bg p-4">
            <p class="text-title font-bold text-deep-green">Periksa data tugas</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-body-sm text-text-primary">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    <x-ui.panel title="Permintaan warga" description="Rincian awal menjadi acuan penimbangan; nilai akhir selalu berdasarkan timbang aktual.">
        <dl class="grid gap-3 text-body sm:grid-cols-3">
            <div class="rounded-lg bg-warm-canvas px-3 py-2"><dt class="text-caption text-text-secondary">Warga</dt><dd class="mt-0.5 font-semibold text-deep-green">{{ $pickup->customer?->name ?? 'Warga' }}</dd></div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2"><dt class="text-caption text-text-secondary">Jadwal</dt><dd class="mt-0.5 font-semibold text-deep-green">{{ $pickup->scheduled_date?->translatedFormat('d F Y') ?? 'Belum dijadwalkan' }}</dd></div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2"><dt class="text-caption text-text-secondary">Estimasi berat</dt><dd class="mt-0.5 font-semibold text-deep-green">{{ \App\Support\WeightFormatter::format($pickup->estimated_weight_kg) }} kg</dd></div>
        </dl>
        @if ($pickup->items->isNotEmpty())
            <ul class="mt-4 divide-y divide-border border-y border-border text-body-sm">
                @foreach ($pickup->items as $item)
                    <li class="flex justify-between gap-3 py-2"><span class="font-semibold text-deep-green">{{ $item->wasteType?->name ?? 'Jenis sampah' }}</span><span class="text-text-secondary">{{ \App\Support\WeightFormatter::format($item->estimated_weight_kg) }} kg</span></li>
                @endforeach
            </ul>
        @endif
        <p class="mt-4 text-body-sm text-text-secondary">Media pengajuan tersimpan privat dan tersedia melalui akses rekam penjemputan yang terotorisasi. {{ $pickup->media->count() }} media tercatat.</p>
    </x-ui.panel>

    {{-- Status Panel --}}
    <x-ui.panel title="Status tugas" description="Urutan status harus diikuti. Estimasi tidak digunakan untuk saldo.">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-info-bg bg-info-bg px-4 py-1.5 text-label font-bold text-sky-blue">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                {{ \App\Support\StatusLabel::for($pickup->status) }}
            </span>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            @if ($pickup->status->value === 'dijadwalkan')
                <button type="button" wire:click="begin" wire:loading.attr="disabled"
                    class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-sky-blue px-5 text-label font-bold text-white transition hover:opacity-90 disabled:cursor-wait disabled:opacity-60">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l19-9-9 19-2-8-8-2Z"/></svg>
                    Menuju Lokasi
                </button>
            @endif
            @if ($pickup->status->value === 'menuju_lokasi')
                <button type="button" wire:click="markPickedUp" wire:loading.attr="disabled"
                    class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700 disabled:cursor-wait disabled:opacity-60">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    Tandai Dijemput
                </button>
            @endif
            @if (in_array($pickup->status->value, ['dijadwalkan', 'menuju_lokasi', 'dijemput'], true))
                <button type="button" wire:click="openFailureReport"
                    class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl border-2 border-terracotta px-5 text-label font-bold text-terracotta transition hover:bg-danger-bg">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                    Laporkan Kendala
                </button>
            @endif
        </div>
    </x-ui.panel>

    {{-- Actual Weighing --}}
    @if ($canComplete)
        <x-ui.panel title="Timbang aktual" description="Berat aktual ini menjadi sumber setoran dan saldo." state="success">
            <div class="grid gap-4">
                @foreach ($actualItems as $index => $item)
                    <div wire:key="actual-item-{{ $index }}" class="grid gap-3 rounded-xl border border-border bg-warm-canvas p-4 md:grid-cols-[1fr_1fr_1fr_auto] md:items-end">
                        <x-ui.select wire:model="actualItems.{{ $index }}.waste_type_id"
                            label="Jenis sampah" name="actualItems.{{ $index }}.waste_type_id"
                            placeholder="Pilih jenis sampah"
                            :error="$errors->first('actualItems.'.$index.'.waste_type_id')"
                            :options="$types->pluck('name', 'id')->all()" />
                        <x-ui.select wire:model="actualItems.{{ $index }}.condition_id"
                            label="Kondisi" name="actualItems.{{ $index }}.condition_id"
                            placeholder="Pilih kondisi"
                            :error="$errors->first('actualItems.'.$index.'.condition_id')"
                            :options="$conditions->pluck('name', 'id')->all()" />
                        <x-ui.input wire:model="actualItems.{{ $index }}.weight_kg"
                            label="Berat aktual (kg)" name="actualItems.{{ $index }}.weight_kg"
                            inputmode="decimal" :error="$errors->first('actualItems.'.$index.'.weight_kg')" />
                        @if (count($actualItems) > 1)
                            <button type="button" wire:click="removeActualItem({{ $index }})" class="inline-flex min-h-touch items-center justify-center rounded-xl border-2 border-terracotta px-4 text-label font-bold text-terracotta transition hover:bg-danger-bg">Hapus</button>
                        @endif
                    </div>
                @endforeach

                <x-ui.media-picker
                    id="pickup-evidence"
                    property="evidence"
                    label="Bukti foto penjemputan"
                    hint="Ambil satu foto di lokasi atau pilih dari galeri. Foto dikompres menjadi JPEG maksimal 1 MB; foto pengajuan warga tetap dipakai bila bukti baru tidak ditambahkan."
                    remove-method="clearEvidence"
                    confirm-method="confirmEvidenceUpload"
                    initial-status="Belum ada foto baru dipilih."
                />
                @error('evidence')<p class="mt-2 text-body-sm font-semibold text-terracotta">{{ $message }}</p>@enderror

                <div class="rounded-md border border-border bg-surface p-4" aria-live="polite">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                        <div>
                            <h3 class="text-label font-bold text-deep-green">Review nilai setoran</h3>
                            <p class="text-body-sm text-text-secondary">Harga aktif dan subtotal akan dihitung ulang server saat finalisasi.</p>
                        </div>
                        @if ($pricePreview['complete'])
                            <strong class="amount-tabular text-title text-forest-700">Rp {{ number_format($pricePreview['total'], 0, ',', '.') }}</strong>
                        @else
                            <span class="text-body-sm font-semibold text-harvest-gold">Lengkapi item untuk melihat nilai</span>
                        @endif
                    </div>
                    @if ($pricePreview['lines'] !== [])
                        <dl class="mt-3 divide-y divide-border border-t border-border text-body-sm">
                            @foreach ($pricePreview['lines'] as $line)
                                <div class="flex items-center justify-between gap-3 py-2">
                                    <dt class="min-w-0"><span class="block font-semibold text-deep-green">{{ $line['name'] }}</span><span class="text-text-secondary">{{ $line['condition'] }} · {{ \App\Support\WeightFormatter::format($line['weight']) }} kg</span></dt>
                                    <dd class="shrink-0 amount-tabular font-semibold text-deep-green">Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="button" wire:click="addActualItem" class="inline-flex min-h-touch items-center gap-2 justify-self-start rounded-xl border-2 border-forest-600 px-4 text-label font-bold text-forest-700 transition hover:bg-success-bg">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Tambah Detail Timbang
                    </button>
                    <x-ui.button type="button" wire:click="reviewCompletion" wire:loading.attr="disabled" data-photo-picker-action>
                        <span wire:loading.remove>Review Finalisasi</span>
                        <span wire:loading>Memeriksa...</span>
                    </x-ui.button>
                </div>
            </div>
        </x-ui.panel>
    @endif

    @if ($receipt)
        <x-ui.success-state
            title="Setoran pickup berhasil"
            :reference="$receipt['number']"
            :value="'Rp '.number_format($receipt['value'], 0, ',', '.')"
            :time="$receipt['occurredAt']"
            :status="$receipt['status']"
        />
    @endif

    @if ($completionDialogOpen)
        <div class="fixed inset-0 z-overlay flex items-end justify-center bg-overlay p-4 sm:items-center" role="presentation">
            <div class="w-full max-w-form rounded-lg border border-border bg-surface p-5 shadow-dialog sm:p-6" role="dialog" aria-modal="true" aria-labelledby="pickup-completion-title">
                <h2 id="pickup-completion-title" class="text-h2 font-bold text-deep-green">Finalkan setoran aktual?</h2>
                <p class="mt-2 text-body-sm text-text-secondary">Berat dan nilai akan dicatat, saldo warga bertambah, dan tugas selesai. Tindakan ini tidak dapat dibatalkan dari tugas ini.</p>
                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelCompletionReview">Ubah data</x-ui.button>
                    <x-ui.button type="button" wire:click="complete" wire:loading.attr="disabled" wire:target="complete"><span wire:loading.remove wire:target="complete">Finalkan setoran</span><span wire:loading wire:target="complete">Memproses...</span></x-ui.button>
                </div>
            </div>
        </div>
    @endif

    {{-- Status History --}}
    <x-ui.panel title="Tahapan penjemputan" description="Semua tahapan ditampilkan; tahapan berikutnya tetap redup sampai status tugas berubah.">
        <x-ui.status-stepper
            :steps="$pickupStages"
            :current-status="$pickupStatus"
            :history="$pickup->statusHistory"
            :terminal-step="$pickupTerminalStep"
            :completed-statuses="['selesai']"
            label="Tahapan tugas penjemputan"
        />
    </x-ui.panel>

    @if ($failureDialogOpen)
        <div class="fixed inset-0 z-overlay flex items-end justify-center bg-overlay p-4 sm:items-center" role="presentation">
            <div class="w-full max-w-form rounded-lg border border-border bg-surface p-5 shadow-dialog sm:p-6" role="dialog" aria-modal="true" aria-labelledby="failure-dialog-title">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="failure-dialog-title" class="text-h2 font-bold text-deep-green">Laporkan kendala pickup</h2>
                        <p class="mt-1 text-body-sm text-text-secondary">Pengajuan akan dibatalkan dan alasan ini tercatat pada riwayat tugas.</p>
                    </div>
                    <button type="button" wire:click="closeFailureReport" aria-label="Tutup dialog" class="inline-flex min-h-touch min-w-touch items-center justify-center rounded-md text-text-secondary hover:bg-warm-canvas">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <form wire:submit="reportFailure" class="mt-5 grid gap-4">
                    <x-ui.textarea name="failureReason" label="Jelaskan kendala" wire:model="failureReason" rows="4" hint="Minimal 10 karakter. Contoh: alamat tidak dapat diakses setelah dua kali percobaan." :error="$errors->first('failureReason')" />
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <x-ui.button type="button" variant="secondary" wire:click="closeFailureReport">Kembali</x-ui.button>
                        <x-ui.button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="reportFailure">
                            <span wire:loading.remove wire:target="reportFailure">Catat dan hentikan pickup</span>
                            <span wire:loading wire:target="reportFailure">Menyimpan...</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</section>
