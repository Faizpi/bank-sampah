<x-slot:title>Identifikasi warga</x-slot:title>
<section class="space-y-6">
    <x-ui.page-heading id="identification-title" title="Pilih warga untuk setoran" description="Cari nama warga atau pindai kartu nasabah." mascot="7" />
    @if ($mobileServices->isNotEmpty())
        <x-ui.panel title="Metode setoran">
            <x-ui.select name="selectedMobileServiceId" label="Metode setoran" wire:model.live="selectedMobileServiceId" :error="$errors->first('mobileServiceId')">
                <option value="">Setoran langsung</option>
                @foreach ($mobileServices as $mobileService)
                    <option value="{{ $mobileService->id }}">Keliling · {{ $mobileService->point }} · {{ $mobileService->starts_at->format('d M H:i') }}</option>
                @endforeach
            </x-ui.select>
            @error('mobileServiceId')
                <p role="alert" class="mt-2 text-body-sm font-semibold text-terracotta">{{ $message }}</p>
            @enderror
        </x-ui.panel>
    @endif

    @if ($scannerOpen)
        <x-ui.panel title="Pindai QR nasabah" description="Pindai kartu nasabah, lalu periksa nama warga." state="success">
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
        <x-ui.panel title="Pindai dengan kamera" description="Arahkan kamera ke QR kartu nasabah.">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-body-sm text-text-secondary">Jika kamera tidak bisa dipakai, cari nomor atau nama warga di bawah.</p>
                <div class="flex flex-col items-end">
                    <x-ui.button type="button" wire:click="openScanner">Buka Pemindai QR</x-ui.button>
                </div>
            </div>
        </x-ui.panel>
    @endif

    {{-- Search Form --}}
    <x-ui.panel title="Cari warga">
        <form wire:submit="find" class="space-y-4">
            <x-ui.input
                name="search"
                label="Nomor atau nama warga"
                placeholder="Contoh: Siti atau CST-00000001"
                wire:model="search"
                autocomplete="off"
                :error="$errors->first('search')"
            />
            <div class="flex flex-col items-end">
                <x-ui.button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Cari Nasabah</span>
                    <span wire:loading>Mencari...</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.panel>

    @if ($searched && $matchingCustomerIds === [])
        <x-ui.empty-state title="Warga tidak ditemukan" description="Periksa nama/nomor atau cari warga dalam area tugas Anda." />
    @elseif (count($matchingCustomerIds) > 1 && ! $candidate)
        <x-ui.panel title="Pilih warga yang sesuai" description="Ada beberapa hasil. Periksa nomor nasabah dan domisilinya.">
            <div class="divide-y divide-border">
                @foreach ($matchingCustomers as $match)
                    <button type="button" wire:click="chooseCustomer({{ $match->id }})" wire:loading.attr="disabled" class="min-h-touch w-full py-4 text-left" wire:key="customer-match-{{ $match->id }}">
                        <span class="block font-bold">{{ $match->name }} · {{ $match->customerProfile?->customer_number }}</span>
                        <span class="text-body-sm text-text-secondary">{{ $match->customerProfile?->serviceRegion() }}</span>
                    </button>
                @endforeach
            </div>
        </x-ui.panel>
    @endif

    @if ($candidate)
        <x-ui.panel title="Warga yang dipilih">
            <p class="text-h2 font-bold">{{ $candidate->name }}</p>
            <p>{{ $candidate->maskedNumber() }}</p>
            @if (! $confirmed)
                <x-ui.button wire:click="startDeposit" wire:loading.attr="disabled">Nama sudah sesuai — catat setoran</x-ui.button>
            @else
                <x-ui.button wire:click="startDeposit" wire:loading.attr="disabled">Catat setoran</x-ui.button>
            @endif
        </x-ui.panel>
    @endif
</section>
