<x-slot:title>Tugas sembako</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<section aria-labelledby="grocery-tasks-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Petugas</p>
            <h1 id="grocery-tasks-title" class="mt-2 text-h1 font-bold text-deep-green">Tugas sembako</h1>
            <p class="mt-3 text-body text-text-secondary">
                Ikuti urutan persiapan. Serah-terima hanya tersedia bagi petugas dengan izin penyerahan dan memerlukan verifikasi penerima serta bukti privat.
            </p>
        </div>
        <x-ui.mascot variant="4" bubble="Siapkan paket sembako!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif
    @if ($receipt)
        <x-ui.success-state title="Serah-terima berhasil" description="{{ $receipt['number'] }} · Rp {{ number_format($receipt['value'], 0, ',', '.') }} · {{ $receipt['occurredAt'] }} · Status: {{ $receipt['status'] }}" />
    @endif

    {{-- Redemption Tasks --}}
    @forelse ($redemptions as $redemption)
        <x-ui.panel :title="$redemption->request_number" :description="$redemption->customer?->name ?? 'Warga'">
            <div class="grid gap-5">
                {{-- Status + Details --}}
                <dl class="grid gap-3 text-body sm:grid-cols-3">
                    <div class="rounded-lg bg-warm-canvas px-3 py-2">
                        <dt class="text-caption font-medium text-text-secondary">Paket</dt>
                        <dd class="mt-0.5 font-semibold text-deep-green">{{ $redemption->package?->name ?? 'Paket' }}</dd>
                    </div>
                    <div class="rounded-lg bg-warm-canvas px-3 py-2">
                        <dt class="text-caption font-medium text-text-secondary">Nilai saat pengajuan</dt>
                        <dd class="mt-0.5 amount-tabular font-semibold text-deep-green">Rp{{ number_format($redemption->value_snapshot, 0, ',', '.') }}</dd>
                    </div>
                    <div class="rounded-lg bg-warm-canvas px-3 py-2">
                        <dt class="text-caption font-medium text-text-secondary">Status</dt>
                        <dd class="mt-0.5 font-semibold text-deep-green">{{ str_replace('_', ' ', ucfirst($redemption->status->value)) }}</dd>
                    </div>
                    <div class="rounded-lg bg-warm-canvas px-3 py-2 sm:col-span-3">
                        <dt class="text-caption font-medium text-text-secondary">Isi paket</dt>
                        <dd class="mt-0.5 whitespace-pre-line font-semibold text-deep-green">{{ $redemption->package_snapshot['contents'] ?? $redemption->package?->contents ?? 'Tidak tersedia' }}</dd>
                    </div>
                </dl>

                {{-- Actions --}}
                <div class="flex flex-col gap-3 sm:flex-row">
                    @if ($redemption->status->value === 'disetujui' && $canPrepare)
                        <button type="button" wire:click="prepare({{ $redemption->id }})" wire:loading.attr="disabled"
                            class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700 disabled:cursor-wait disabled:opacity-60">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>
                            Mulai siapkan
                        </button>
                    @elseif ($redemption->status->value === 'sedang_disiapkan' && $canPrepare)
                        <button type="button" wire:click="ready({{ $redemption->id }})" wire:loading.attr="disabled"
                            class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-sky-blue px-5 text-label font-bold text-white transition hover:opacity-90 disabled:cursor-wait disabled:opacity-60">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                            Tandai siap diambil
                        </button>
                    @elseif ($redemption->status->value === 'disetujui' || $redemption->status->value === 'sedang_disiapkan')
                        <p class="text-body-sm text-text-secondary">Menunggu petugas dengan izin persiapan untuk melanjutkan paket.</p>
                    @elseif ($redemption->status->value === 'siap_diambil' && $canHandover)
                        <button type="button" wire:click="select({{ $redemption->id }})"
                            class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-harvest-gold px-5 text-label font-bold text-deep-green transition hover:opacity-90">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7-7"/></svg>
                            Proses serah-terima
                        </button>
                    @elseif ($redemption->status->value === 'siap_diambil')
                        <p class="text-body-sm text-text-secondary">Menunggu petugas dengan izin serah-terima untuk menyerahkan paket.</p>
                    @endif
                </div>
            </div>
        </x-ui.panel>
    @empty
        <div class="rounded-xl border border-border bg-surface p-8 text-center shadow-xs">
            <x-ui.mascot variant="9" class="mx-auto h-24 w-auto" />
            <p class="mt-3 text-label font-bold text-deep-green">Belum ada tugas sembako</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">Penukaran dalam cakupan Anda akan muncul setelah ada keputusan operasional.</p>
        </div>
    @endforelse

    {{-- Verifikasi serah-terima --}}
    @if ($selectedRedemptionId !== null && $canHandover)
        <x-ui.panel title="Verifikasi penerima dan bukti" description="Nomor nasabah wajib cocok dengan data warga. Bukti disimpan privat dan hanya dapat diakses melalui halaman terotorisasi." state="success">
            <div class="grid gap-4">
                <x-ui.select wire:model.live="recipientVerification" label="Metode verifikasi penerima" name="recipientVerification"
                    :options="['kartu_nasabah' => 'Pindai kartu nasabah', 'nomor_nasabah' => 'Masukkan nomor nasabah']" />
                @if ($recipientVerification === 'kartu_nasabah')
                    <x-ui.button type="button" variant="secondary" wire:click="openScanner">Pindai QR kartu nasabah</x-ui.button>
                    @if ($scannerOpen)
                        <x-ui.input wire:model="scanToken" label="Token QR kartu" name="scanToken" placeholder="Masukkan token QR bila pemindai tidak tersedia" :error="$errors->first('recipientReference')" />
                        <div class="flex gap-3"><x-ui.button type="button" wire:click="scanCustomerCard(scanToken)">Resolusi kartu</x-ui.button><x-ui.button type="button" variant="quiet" wire:click="closeScanner">Tutup</x-ui.button></div>
                    @endif
                @else
                    <x-ui.input wire:model="recipientReference" label="Nomor nasabah" name="recipientReference" placeholder="CST-00000001" hint="Wajib berformat CST-########." :error="$errors->first('recipientReference')" />
                @endif
                @if ($resolvedCustomerName)<p role="status" class="text-body-sm font-semibold text-forest-700">Kartu cocok: {{ $resolvedCustomerName }}</p>@endif
                @error('recipientReference')<p role="alert" class="text-body-sm font-semibold text-terracotta">{{ $message }}</p>@enderror
                <x-ui.media-picker
                    id="grocery-proof"
                    property="proof"
                    label="Bukti serah-terima"
                    hint="Foto JPEG, PNG, atau WebP dikompres menjadi JPEG maksimal 1 MB. PDF diterima maksimal 5 MB."
                    :allow-pdf="true"
                    remove-method="clearProof"
                    confirm-method="confirmProofUpload"
                    wire:key="grocery-proof-picker-{{ $selectedRedemptionId }}"
                />
                @error('proof')
                    <p class="text-body-sm font-semibold text-terracotta">{{ $message }}</p>
                @enderror
                <x-ui.button type="button" wire:click="reviewHandover" wire:loading.attr="disabled" data-photo-picker-action>
                    <span wire:loading.remove>Tinjau serah-terima</span>
                    <span wire:loading>Memeriksa...</span>
                </x-ui.button>
                @if ($handoverReviewOpen)
                    <div class="rounded-md border-2 border-harvest-gold bg-warning-bg p-4" role="alert"><h3 class="text-title font-bold text-deep-green">Konfirmasi serah-terima final</h3><p class="mt-1 text-body-sm text-text-primary">Paket diserahkan, saldo warga dikurangi, dan status penukaran selesai. Tindakan ini tidak dapat dibatalkan dari tugas ini.</p><div class="mt-4 flex flex-col gap-3 sm:flex-row"><x-ui.button type="button" variant="secondary" wire:click="cancelHandoverReview">Ubah data</x-ui.button><x-ui.button type="button" wire:click="handover" wire:loading.attr="disabled" wire:target="handover"><span wire:loading.remove wire:target="handover">Serahkan paket</span><span wire:loading wire:target="handover">Memproses...</span></x-ui.button></div></div>
                @endif
            </div>
        </x-ui.panel>
    @endif
</section>
