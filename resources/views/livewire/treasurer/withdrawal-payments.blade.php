<x-slot:title>Pembayaran pencairan</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<section aria-labelledby="withdrawal-payments-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="text-label font-semibold text-harvest-gold">Bendahara</p>
            <h1 id="withdrawal-payments-title" class="mt-2 text-pretty text-h1 font-bold text-deep-green">Pembayaran pencairan</h1>
            <p class="mt-3 max-w-3xl text-pretty text-body text-text-secondary">
                Pembayaran hanya dapat dilakukan oleh bendahara atau petugas yang ditugaskan. Pilih pencairan yang menjadi tanggung jawab Anda. Verifikasi penerima dan bukti sebelum membayar.
            </p>
        </div>
        <x-ui.mascot variant="12" bubble="Bayar tepat waktu!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if ($receipt)
        <x-ui.success-state
            title="Pembayaran tercatat"
            :reference="$receipt['number']"
            :value="'Rp '.number_format($receipt['value'], 0, ',', '.')"
            :time="$receipt['occurredAt']"
            :status="$receipt['status']"
            :description="session('success')"
        />
    @elseif (session('success'))
        <x-ui.success-state title="Pembayaran berhasil" :description="session('success')" />
    @endif

    {{-- Withdrawal List --}}
    <x-ui.panel title="Antrean pembayaran" description="Cari penerima atau nomor, lalu dahulukan pengajuan terlama atau nominal terbesar.">
        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(12rem,16rem)]">
            <x-ui.input wire:model.live.debounce.300ms="queueSearch" name="queueSearch" label="Cari antrean" placeholder="Nama, nomor nasabah, atau nomor pencairan" />
            <x-ui.select wire:model.live="queueOrder" name="queueOrder" label="Urutkan" :options="['oldest' => 'Paling lama menunggu', 'largest' => 'Nominal terbesar']" />
        </div>
        <p wire:loading wire:target="queueSearch,queueOrder" role="status" aria-live="polite" class="mt-3 text-body-sm font-semibold text-text-secondary">Memuat antrean pembayaran...</p>
    </x-ui.panel>

    <div wire:loading.remove wire:target="queueSearch,queueOrder" class="grid gap-6">
    @forelse ($withdrawals as $withdrawal)
        <x-ui.panel :title="$withdrawal->request_number" :description="$withdrawal->customer?->name ?? 'Nasabah'">
            <div class="flex flex-col items-stretch gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="amount-tabular break-words text-h2 font-extrabold text-deep-green">
                        Rp{{ number_format($withdrawal->amount, 0, ',', '.') }}
                    </p>
                    <p class="break-words text-body-sm text-text-secondary">
                        {{ $withdrawal->pickup_date?->translatedFormat('d F Y') ?? 'Tanggal belum tersedia' }}
                        @if ($withdrawal->pickup_location)
                            · {{ $withdrawal->pickup_location }}
                        @endif
                    </p>
                    <p class="text-body-sm text-text-secondary">Dana ditahan: <strong class="text-deep-green">Rp {{ number_format($withdrawal->balanceHold?->amount ?? $withdrawal->amount, 0, ',', '.') }}</strong></p>
                </div>
                <button type="button" wire:click="select({{ $withdrawal->id }})"
                    class="inline-flex min-h-touch w-full items-center justify-center gap-2 rounded-xl bg-harvest-gold px-5 text-center text-label font-bold text-deep-green shadow-xs transition hover:opacity-90 sm:w-auto sm:shrink-0">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>
                    </svg>
                    Proses Pembayaran
                </button>
            </div>
        </x-ui.panel>
    @empty
        <div class="rounded-xl border border-border bg-surface p-8 text-center shadow-xs">
            <x-ui.mascot variant="5" class="mx-auto h-24 w-auto" />
            <p class="mt-3 text-label font-bold text-deep-green">{{ trim($queueSearch) === '' ? 'Belum ada pencairan siap bayar' : 'Antrean tidak ditemukan' }}</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">{{ trim($queueSearch) === '' ? 'Pencairan yang disetujui dan ditugaskan kepada Anda akan muncul di sini.' : 'Periksa nama atau nomor pencarian, lalu coba lagi.' }}</p>
        </div>
    @endforelse
    </div>

    <div wire:loading wire:target="queueSearch,queueOrder" class="grid gap-3" aria-hidden="true">
        @for ($item = 0; $item < 2; $item++)
            <div class="rounded-xl border border-border bg-surface p-5 shadow-xs">
                <div class="h-5 w-40 rounded-md bg-disabled-bg"></div>
                <div class="mt-4 h-8 w-48 rounded-md bg-disabled-bg"></div>
                <div class="mt-3 h-4 w-64 max-w-full rounded-md bg-disabled-bg"></div>
            </div>
        @endfor
    </div>

    {{-- Verification Form --}}
    @if ($selectedWithdrawal !== null)
        @php
            $isCardVerification = $recipientVerification === 'kartu_nasabah';
            $verificationHint = $isCardVerification
                ? 'Kartu nasabah adalah media identitas fisik atau digital. Periksa kartu yang ditunjukkan warga, lalu cocokkan nama dan nomor yang tercetak.'
                : 'Nomor nasabah adalah kode unik warga. Minta warga menyebutkan nomornya, lalu cocokkan dengan nama penerima pada ringkasan.';
        @endphp
        <x-ui.panel wire:key="withdrawal-payment-{{ $selectedWithdrawal->id }}" title="Verifikasi dan bukti pembayaran" description="Pastikan nama penerima dan nominal sesuai sebelum pembayaran dicatat." state="success">
            <div class="grid gap-4">
                <dl class="grid gap-3 rounded-md bg-warm-canvas p-4 text-body-sm sm:grid-cols-2">
                    <div><dt class="text-text-secondary">Penerima</dt><dd class="mt-1 text-label font-bold text-deep-green">{{ $selectedWithdrawal->customer?->name ?? 'Nasabah' }}</dd></div>
                    <div><dt class="text-text-secondary">Nominal</dt><dd class="mt-1 amount-tabular text-title font-bold text-deep-green">Rp {{ number_format($selectedWithdrawal->amount, 0, ',', '.') }}</dd></div>
                    <div class="min-w-0"><dt class="text-text-secondary">Saldo warga saat ini</dt><dd class="mt-1 amount-tabular break-words font-bold text-forest-700">{{ $availableBalance === null ? 'Dicek saat pembayaran' : 'Rp '.number_format($availableBalance, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-text-secondary">Dana ditahan</dt><dd class="mt-1 amount-tabular font-bold text-harvest-gold">Rp {{ number_format($selectedWithdrawal->balanceHold?->amount ?? $selectedWithdrawal->amount, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-text-secondary">Tanggal pengambilan</dt><dd class="mt-1 font-semibold text-deep-green">{{ $selectedWithdrawal->pickup_date?->translatedFormat('d F Y') ?? 'Belum tersedia' }}</dd></div>
                    <div><dt class="text-text-secondary">Batas pengambilan</dt><dd class="mt-1 font-semibold text-deep-green">{{ $selectedWithdrawal->expires_at?->translatedFormat('d F Y, H:i') ?? 'Belum tersedia' }}</dd></div>
                </dl>
                <x-ui.select wire:model.live="recipientVerification" label="Cara verifikasi penerima" name="recipientVerification"
                    hint="{{ $verificationHint }}"
                    :options="['kartu_nasabah' => 'Pindai kartu nasabah', 'nomor_nasabah' => 'Masukkan nomor nasabah']" />
                @if ($recipientVerification === 'kartu_nasabah')
                    <div class="flex flex-col items-stretch sm:flex-row sm:justify-end">
                        <x-ui.button type="button" variant="secondary" wire:click="openScanner">Pindai QR kartu nasabah</x-ui.button>
                    </div>
                    @if ($scannerOpen)
                        <div
                            x-data="{
                                stream: null,
                                detector: null,
                                running: false,
                                error: '',
                                async start() {
                                    if (!('BarcodeDetector' in window) || ! navigator.mediaDevices?.getUserMedia) {
                                        this.error = 'Peramban ini belum mendukung pemindaian QR kamera. Pilih Masukkan nomor nasabah untuk melanjutkan verifikasi.';
                                        return;
                                    }

                                    try {
                                        const formats = await BarcodeDetector.getSupportedFormats();
                                        if (! formats.includes('qr_code')) {
                                            this.error = 'Pemindaian QR belum tersedia di peramban ini. Pilih Masukkan nomor nasabah untuk melanjutkan verifikasi.';
                                            return;
                                        }
                                        this.detector = new BarcodeDetector({ formats: ['qr_code'] });
                                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                                        this.$refs.video.srcObject = this.stream;
                                        await this.$refs.video.play();
                                        this.running = true;
                                        this.read();
                                    } catch (exception) {
                                        this.error = exception?.name === 'NotAllowedError'
                                            ? 'Izin kamera ditolak. Pilih Masukkan nomor nasabah untuk melanjutkan verifikasi.'
                                            : 'Kamera tidak dapat digunakan. Pilih Masukkan nomor nasabah untuk melanjutkan verifikasi.';
                                        this.stop();
                                    }
                                },
                                async read() {
                                    if (! this.running) return;
                                    try {
                                        const codes = await this.detector.detect(this.$refs.video);
                                        const rawValue = codes[0]?.rawValue;
                                        if (typeof rawValue === 'string' && rawValue !== '') {
                                            this.stop();
                                            await $wire.scanCustomerCard(rawValue);
                                            return;
                                        }
                                    } catch (exception) {
                                        this.error = 'QR belum terbaca. Pastikan kartu berada di dalam bingkai.';
                                    }
                                    if (this.running) requestAnimationFrame(() => this.read());
                                },
                                stop() {
                                    this.running = false;
                                    this.stream?.getTracks().forEach((track) => track.stop());
                                    this.stream = null;
                                    if (this.$refs.video) this.$refs.video.srcObject = null;
                                }
                            }"
                            x-init="start(); return () => stop()"
                            x-on:livewire:navigating.window="stop()"
                            class="space-y-4"
                        >
                            <div class="relative overflow-hidden rounded-xl border border-border bg-deep-green">
                                <video x-ref="video" class="aspect-video w-full object-cover" playsinline muted aria-label="Pratinjau kamera pemindai QR"></video>
                                <div class="pointer-events-none absolute inset-8 rounded-xl border-2 border-white/80"></div>
                            </div>
                            <p x-show="error" x-text="error" role="alert" class="text-body-sm font-semibold text-terracotta"></p>
                            <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                                <x-ui.button type="button" variant="secondary" x-on:click="stop(); $wire.set('recipientVerification', 'nomor_nasabah')">Gunakan nomor nasabah</x-ui.button>
                                <x-ui.button type="button" variant="quiet" x-on:click="stop(); $wire.closeScanner()">Tutup</x-ui.button>
                                <x-ui.button type="button" variant="quiet" x-on:click="stop(); start()">Coba Lagi</x-ui.button>
                            </div>
                        </div>
                    @endif
                @else
                    <x-ui.input wire:model="recipientReference" label="Nomor nasabah" name="recipientReference" placeholder="Masukkan CST-########" hint="Wajib berformat CST-########." :error="$errors->first('recipientReference')" />
                @endif
                @if ($resolvedCustomerName)<p role="status" class="text-body-sm font-semibold text-forest-700">Kartu cocok: {{ $resolvedCustomerName }}</p>@endif
                @error('recipientReference')<p role="alert" class="text-body-sm font-semibold text-terracotta">{{ $message }}</p>@enderror
                <x-ui.media-picker
                    id="payment-proof"
                    property="proof"
                    label="Satu foto bukti pembayaran"
                    hint="Ambil satu foto dari kamera atau galeri, maksimal 1 MB."
                    remove-method="clearProof"
                    confirm-method="confirmProofUpload"
                    wire:key="payment-proof-picker-{{ $selectedWithdrawal->id }}"
                />
                @error('proof')
                    <p class="mt-2 text-body-sm font-semibold text-terracotta">{{ $message }}</p>
                @enderror
                <div class="flex flex-col items-stretch sm:flex-row sm:justify-end">
                    <x-ui.button type="button" wire:click="reviewPayment" wire:loading.attr="disabled" wire:target="reviewPayment" data-photo-picker-action>
                        <span wire:loading.remove wire:target="reviewPayment">Tinjau sebelum bayar</span>
                        <span wire:loading wire:target="reviewPayment">Memeriksa...</span>
                    </x-ui.button>
                </div>

                @if ($showPaymentReview)
                    <div class="rounded-md border-2 border-harvest-gold bg-warning-bg p-4" role="alert">
                        <h3 class="text-title font-bold text-deep-green">Konfirmasi pembayaran final</h3>
                        <p class="mt-1 text-body-sm text-text-primary">Pembayaran akan mengubah dana ditahan menjadi saldo keluar dan tidak dapat diulang. Pastikan penerima, nominal, dan bukti sudah benar.</p>
                        <div class="mt-4 flex flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                            <x-ui.button type="button" variant="secondary" wire:click="cancelPaymentReview">Ubah data</x-ui.button>
                            <x-ui.button type="button" wire:click="pay" wire:loading.attr="disabled" wire:target="pay">
                                <span wire:loading.remove wire:target="pay">Bayar dan catat bukti</span>
                                <span wire:loading wire:target="pay">Memproses...</span>
                            </x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
        </x-ui.panel>
    @endif
</section>
