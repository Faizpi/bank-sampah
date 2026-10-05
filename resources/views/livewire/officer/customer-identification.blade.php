<x-slot:title>Identifikasi warga</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<section aria-labelledby="customer-identification-title" class="space-y-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Layanan Petugas</p>
            <h1 id="customer-identification-title" class="mt-2 text-h1 font-bold text-deep-green">Identifikasi Warga</h1>
            <p class="mt-3 text-body text-text-secondary">
                Pindai QR atau masukkan nomor nasabah. Hasil scan hanya kandidat sampai nama dikonfirmasi.
            </p>
        </div>
        <x-ui.mascot variant="6" bubble="Cari warga dengan mudah!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @php
        $identificationSteps = [
            ['key' => 'identify', 'title' => 'Identifikasi', 'description' => 'Pindai QR atau cari nomor warga.', 'icon' => 'eye'],
            ['key' => 'confirm', 'title' => 'Konfirmasi', 'description' => 'Pastikan nama warga sesuai.', 'icon' => 'circle-check'],
            ['key' => 'service', 'title' => 'Pilih layanan', 'description' => 'Tentukan proses yang akan dijalankan.', 'icon' => 'clipboard-check'],
            ['key' => 'complete', 'title' => 'Selesaikan', 'description' => 'Jalankan layanan yang dipilih.', 'icon' => 'flag'],
            ['key' => 'summary', 'title' => 'Ringkasan', 'description' => 'Periksa hasil layanan warga.', 'icon' => 'file-check'],
        ];
        $identificationCurrentStep = $candidate === null
            ? 'identify'
            : (! $confirmed ? 'confirm' : ($selectedService === '' ? 'service' : 'complete'));
    @endphp

    <x-ui.panel title="Tahapan layanan warga" description="Ikuti alur ini agar identifikasi dan layanan warga tetap jelas.">
        <x-ui.status-stepper
            :steps="$identificationSteps"
            :current-status="$identificationCurrentStep"
            label="Tahapan layanan warga"
        />
    </x-ui.panel>

    @if ($scannerOpen)
        <x-ui.panel title="Pindai QR nasabah" description="Arahkan kamera ke QR kartu nasabah. QR hanya digunakan untuk mengenali kartu nasabah; nama tetap harus dikonfirmasi." state="success">
            <div
                x-data="{
                    stream: null,
                    detector: null,
                    running: false,
                    error: '',
                    async start() {
                        if (!('BarcodeDetector' in window) || ! navigator.mediaDevices?.getUserMedia) {
                            this.error = 'Peramban ini belum mendukung pemindaian QR kamera. Gunakan nomor nasabah sebagai alternatif.';
                            return;
                        }

                        try {
                            const formats = await BarcodeDetector.getSupportedFormats();
                            if (! formats.includes('qr_code')) {
                                this.error = 'Pemindaian QR belum tersedia di peramban ini. Gunakan nomor nasabah sebagai alternatif.';
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
                                ? 'Izin kamera ditolak. Gunakan nomor nasabah sebagai alternatif.'
                                : 'Kamera tidak dapat digunakan. Gunakan nomor nasabah sebagai alternatif.';
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
                                await $wire.scan(rawValue);
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
                x-init="start()"
                x-on:livewire:navigating.window="stop()"
                class="space-y-4"
            >
                <div class="relative overflow-hidden rounded-xl border border-border bg-deep-green">
                    <video x-ref="video" class="aspect-video w-full object-cover" playsinline muted aria-label="Pratinjau kamera pemindai QR"></video>
                    <div class="pointer-events-none absolute inset-8 rounded-xl border-2 border-white/80"></div>
                </div>
                <p x-show="error" x-text="error" role="alert" class="text-body-sm font-semibold text-terracotta"></p>
                @error('token')
                    <p role="alert" class="text-body-sm font-semibold text-terracotta">{{ $message }}</p>
                @enderror
                <div class="flex flex-col items-end gap-3 sm:flex-row sm:justify-end">
                    <x-ui.button type="button" variant="secondary" x-on:click="stop(); $wire.closeScanner()">Tutup Pemindai</x-ui.button>
                    <x-ui.button type="button" variant="quiet" x-on:click="stop(); start()">Coba Lagi</x-ui.button>
                </div>
            </div>
        </x-ui.panel>
    @else
        <x-ui.panel title="Pindai dengan kamera" description="Gunakan kamera perangkat untuk membaca QR kartu nasabah. Kodenya tidak ditampilkan di layar.">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-body-sm text-text-secondary">Jika kamera tidak tersedia atau izinnya ditolak, cari warga menggunakan nomor atau nama di bawah.</p>
                <div class="flex flex-col items-end">
                    <x-ui.button type="button" wire:click="openScanner">Buka Pemindai QR</x-ui.button>
                </div>
            </div>
        </x-ui.panel>
    @endif

    @if ($mobileServices->isNotEmpty())
        <x-ui.panel title="Pilih konteks setoran" description="Pilih layanan keliling aktif sebelum memindai QR atau mencari nomor warga. Pilih setoran langsung untuk memakai cakupan area tugas biasa.">
            <x-ui.select name="mobileServiceId" label="Metode setoran" wire:model.live="mobileServiceId">
                <option value="">Setoran langsung</option>
                @foreach ($mobileServices as $mobileService)
                    <option value="{{ $mobileService->id }}">Keliling · {{ $mobileService->point }} · {{ $mobileService->starts_at->format('d M H:i') }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.panel>
    @endif

    {{-- Search Form --}}
    <x-ui.panel title="Cari dengan nomor nasabah" description="Gunakan nomor kartu sebagai alternatif ketika pemindaian tidak tersedia.">
        <form wire:submit="find" class="space-y-4" aria-describedby="identification-help">
            <x-ui.input
                name="search"
                label="Nomor atau nama warga"
                hint="Masukkan awalan nomor, misalnya CST-1234."
                wire:model="search"
                autocomplete="off"
                :error="$errors->first('search')"
            />
            <p id="identification-help" class="text-body-sm text-text-secondary">
                Data warga hanya tampil dalam wilayah dan layanan yang menjadi tanggung jawab Anda.
            </p>
            <div class="flex flex-col items-end">
                <x-ui.button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Cari Nasabah</span>
                    <span wire:loading>Mencari...</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.panel>

    {{-- Candidate Result --}}
    @if ($candidate)
        <x-ui.panel title="Kandidat identitas">
            @if ($confirmed)
                <div role="status" class="space-y-1">
                    <div class="flex items-center gap-2">
                        <svg viewBox="0 0 24 24" class="size-5 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10Z"/><path d="m9 12 2 2 4-4"/>
                        </svg>
                        <p class="text-label font-bold text-forest-600">Identitas warga terkonfirmasi</p>
                    </div>
                    <p class="text-h2 font-bold text-deep-green">{{ $candidate->name }}</p>
                    <p class="text-body-sm text-text-secondary">Nomor referensi: {{ $candidate->maskedNumber() }}</p>


                    @if ($selectedService === 'deposit')
                        <div class="mt-5 border-t border-border pt-4">
                            @php
                                $depositQuery = http_build_query(array_filter([
                                    'mobileServiceId' => $mobileServiceId,
                                    'assistedServiceId' => $assistedServiceId,
                                ]));
                                $depositUrl = route('officer.deposit-form', ['customerId' => $candidate->userId]).($depositQuery !== '' ? '?'.$depositQuery : '');
                                $depositLabel = $mobileServiceId === null ? 'Mulai Setoran Langsung' : 'Mulai Setoran Keliling';
                            @endphp
                            <div class="rounded-xl border border-border bg-warm-canvas p-4">
                                <div class="flex flex-col items-end">
                                    <a href="{{ $depositUrl }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2">
                                        {{ $depositLabel }}
                                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                            @if ($selectedService === 'assisted' && $canCreateAssisted)
                        <div class="mt-5 rounded-xl border border-border bg-warm-canvas p-4">
                            <p class="text-label font-bold text-deep-green">Layanan berbantuan</p>
                            <p class="mt-1 text-body-sm text-text-secondary">Catat layanan atas nama warga setelah persetujuan terpisah. Kata sandi tidak pernah diminta.</p>
                            @if ($assistedRecorded)
                                <p role="status" class="mt-3 text-body-sm font-semibold text-forest-700">Layanan berbantuan tercatat dengan persetujuan dan bukti yang hanya dapat dilihat oleh pihak berwenang.</p>
                                @if ($assistedServiceId)
                                    <div class="mt-3 flex flex-col items-end">
                                        <button type="button" wire:click="handoff" wire:loading.attr="disabled" class="inline-flex min-h-touch items-center rounded-xl border-2 border-forest-600 px-5 text-label font-bold text-forest-700 transition hover:bg-success-bg">Serahkan Bukti dan Saldo</button>
                                    </div>
                                @endif
                                @if (session('assisted-handoff'))
                                    @php
                                        $handoff = session('assisted-handoff');
                                    @endphp
                                    <div role="status" class="mt-4 rounded-xl border border-border bg-warm-canvas p-4">
                                        <p class="text-label font-bold text-deep-green">Handoff selesai</p>
                                        <p class="mt-1 text-body-sm text-text-secondary">Bukti {{ $handoff->receipt['number'] }} · Saldo tersedia Rp {{ number_format($handoff->availableBalance, 0, ',', '.') }}</p>
                                    </div>
                                @endif
                            @else
                                <div class="mt-4 grid gap-4">
                                    <label class="flex items-start gap-3 text-body-sm text-text-primary">
                                        <input type="checkbox" wire:model="assistedConsent" class="mt-1 size-5 rounded border-border text-forest-600 focus:ring-focus" />
                                        <span>Saya sudah menjelaskan layanan ini dan warga memberikan persetujuan terpisah.</span>
                                    </label>
                                    @error('assistedConsent')
                                        <p class="text-body-sm font-semibold text-terracotta">{{ $message }}</p>
                                    @enderror
                                    <x-ui.media-picker
                                        id="assisted-evidence"
                                        property="assistedEvidence"
                                        label="Bukti persetujuan privat"
                                        hint="Foto JPEG, PNG, atau WebP dikompres menjadi JPEG maksimal 1 MB. PDF diterima maksimal 5 MB."
                                        :allow-pdf="true"
                                        remove-method="clearAssistedEvidence"
                                        confirm-method="confirmAssistedEvidenceUpload"
                                    />
                                    @error('assistedEvidence')
                                        <p class="text-body-sm font-semibold text-terracotta">{{ $message }}</p>
                                    @enderror
                                    <div class="flex flex-col items-end">
                                        <x-ui.button type="button" wire:click="recordAssistedService" wire:loading.attr="disabled" data-photo-picker-action>
                                            <span wire:loading.remove wire:target="recordAssistedService">Catat Layanan Berbantuan</span>
                                            <span wire:loading wire:target="recordAssistedService">Menyimpan...</span>
                                        </x-ui.button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($selectedService === 'withdrawal' && $canCreateAssistedWithdrawal)
                        @php
                            $requestedWithdrawalAmount = ctype_digit($withdrawalAmount) ? (int) $withdrawalAmount : null;
                            $withdrawalBalanceInsufficient = $candidateAvailableBalance !== null && $requestedWithdrawalAmount !== null && $requestedWithdrawalAmount > $candidateAvailableBalance;
                        @endphp
                        <div class="mt-5 rounded-xl border border-warning-bg bg-warning-bg p-4">
                            <p class="text-label font-bold text-deep-green">Pencairan berbantuan</p>
                            <p class="mt-1 text-body-sm text-text-secondary">Ajukan pencairan atas nama warga setelah persetujuan dan bukti privat tercatat. Nominal akan mengikuti proses persetujuan dan dana ditahan yang sama.</p>
                            @if ($candidateAvailableBalance !== null)
                                <div role="status" aria-live="polite" class="mt-3 flex items-center gap-3 rounded-xl border border-forest-600 bg-success-bg px-3 py-2.5">
                                    <svg viewBox="0 0 24 24" class="size-5 shrink-0 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
                                    <div>
                                        <p class="text-caption text-forest-700">Saldo tersedia warga</p>
                                        <p class="amount-tabular text-label font-bold text-forest-700">Rp{{ number_format($candidateAvailableBalance, 0, ',', '.') }}</p>
                                    </div>
                                </div>
                            @endif
                            @if ($assistedWithdrawalId)
                                <div role="status" class="mt-3 rounded-xl border border-forest-600 bg-success-bg p-3 text-body-sm text-forest-700">
                                    Pencairan {{ $assistedWithdrawalId }} berhasil diajukan dan terhubung ke bukti consent.
                                </div>
                            @else
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <x-ui.input name="withdrawalAmount" label="Nominal (rupiah)" wire:model.live.debounce.300ms="withdrawalAmount" inputmode="numeric" placeholder="Minimal Rp10.000" :hint="$withdrawalBalanceInsufficient ? 'Nominal melebihi saldo tersedia warga.' : null" :error="$errors->first('withdrawalAmount')" />
                                    <x-ui.input name="withdrawalDate" label="Tanggal pengambilan" wire:model="withdrawalDate" type="date" :error="$errors->first('withdrawalDate')" />
                                    <x-ui.textarea name="withdrawalLocation" label="Lokasi pengambilan" wire:model="withdrawalLocation" rows="2" class="sm:col-span-2" :error="$errors->first('withdrawalLocation')" />
                                    <label class="sm:col-span-2 flex items-start gap-3 text-body-sm text-text-primary">
                                        <input type="checkbox" wire:model="withdrawalConsent" class="mt-1 size-5 rounded border-border text-forest-600 focus:ring-focus" />
                                        <span>Saya sudah menjelaskan proses pencairan dan warga memberikan persetujuan terpisah.</span>
                                    </label>
                                    @error('withdrawalConsent')
                                        <p class="sm:col-span-2 text-body-sm font-semibold text-terracotta">{{ $message }}</p>
                                    @enderror
                                    <x-ui.media-picker
                                        id="withdrawal-evidence"
                                        property="withdrawalEvidence"
                                        label="Bukti persetujuan pencairan"
                                        hint="Foto JPEG, PNG, atau WebP dikompres menjadi JPEG maksimal 1 MB. PDF diterima maksimal 5 MB."
                                        :allow-pdf="true"
                                        remove-method="clearWithdrawalEvidence"
                                        confirm-method="confirmWithdrawalEvidenceUpload"
                                        class="sm:col-span-2"
                                    />
                                    @error('withdrawalEvidence')
                                        <p class="sm:col-span-2 text-body-sm font-semibold text-terracotta">{{ $message }}</p>
                                    @enderror
                                    <div class="flex flex-col items-end sm:col-span-2">
                                        <x-ui.button type="button" wire:click="requestAssistedWithdrawal" wire:loading.attr="disabled" wire:target="requestAssistedWithdrawal" :disabled="$withdrawalBalanceInsufficient" data-photo-picker-action>
                                            <span wire:loading.remove wire:target="requestAssistedWithdrawal">Ajukan Pencairan Berbantuan</span>
                                            <span wire:loading wire:target="requestAssistedWithdrawal">Memproses...</span>
                                        </x-ui.button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <div class="space-y-4">
                    <div class="rounded-xl bg-warm-canvas px-4 py-3.5">
                        <p class="text-caption font-medium text-text-secondary">Konfirmasi nama warga</p>
                        <p class="mt-0.5 text-title font-bold text-deep-green">{{ $candidate->name }}</p>
                        <p class="mt-0.5 text-body-sm text-text-secondary">Nomor referensi: {{ $candidate->maskedNumber() }}</p>
                    </div>
                    <div class="flex flex-col items-end">
                        <x-ui.button type="button" wire:click="confirm">Konfirmasi Nama</x-ui.button>
                    </div>
                </div>
            @endif
        </x-ui.panel>
    @elseif ($search !== '' && ! $errors->has('search'))
        <div class="rounded-xl border border-border bg-surface p-6 text-center shadow-xs">
            <x-ui.mascot variant="9" class="mx-auto h-20 w-auto" />
            <p class="mt-3 text-label font-bold text-deep-green">Nasabah tidak ditemukan</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">Periksa nomor alternatif atau minta warga menunjukkan kartunya kembali.</p>
        </div>
    @endif
</section>
