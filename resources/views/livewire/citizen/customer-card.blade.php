<x-slot:title>Kartu nasabah</x-slot:title>
<x-slot:context>Nomor dan QR untuk layanan bank sampah</x-slot:context>

<section aria-labelledby="customer-card-title" class="space-y-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-6 rounded-2xl border border-border bg-surface p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div class="space-y-2">
            <div class="inline-flex items-center gap-2 text-label text-forest-600">
                <x-public.icon name="leaf" class="size-4" />
                <span>Identitas Warga</span>
            </div>
            <h1 id="customer-card-title" class="text-h2 font-bold text-deep-green sm:text-h1">Kartu Digital Nasabah</h1>
            <p class="max-w-xl text-body text-text-secondary">
                Tunjukkan QR ini kepada petugas saat transaksi setoran atau penjemputan. QR aman &amp; tidak memuat informasi pribadi peka.
            </p>
        </div>
        <div class="flex shrink-0 items-center justify-center">
            <x-ui.mascot variant="5" bubble="Tunjukkan QR ini ke petugas ya!" bubblePosition="top"
                class="h-28 w-auto sm:h-32" />
        </div>
    </div>

    @if ($available)
        <div class="mx-auto max-w-sm">
            {{-- Clean card — no gradient, no glow --}}
            <div class="overflow-hidden rounded-2xl border border-border bg-deep-green shadow-sm">
                {{-- Card header --}}
                <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
                    <div class="flex items-center gap-2.5">
                        <div class="flex size-9 items-center justify-center rounded-xl bg-white/10 border border-white/15">
                            <x-public.icon name="recycle" class="size-4 text-harvest-gold" />
                        </div>
                        <div>
                            <p class="text-caption font-semibold uppercase tracking-wider text-emerald-300">Kartu Nasabah Resmi</p>
                            <p class="text-body-sm font-bold text-white">Bank Sampah Sindangheula</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/40 bg-emerald-400/15 px-2.5 py-1 text-caption font-semibold text-emerald-200">
                        <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Aktif
                    </span>
                </div>

                {{-- QR Code --}}
                <div class="bg-surface p-5 mx-4 my-4 rounded-xl shadow-xs">
                    <x-ui.qr-display
                        title="QR Nasabah Warga"
                        context="Pindai QR untuk verifikasi identitas nasabah cepat &amp; akurat."
                        :image-src="$qrImageSrc"
                        image-alt="Kode QR nasabah"
                        :masked-reference="$maskedNumber"
                        :fallback-number="$maskedNumber"
                    />
                </div>

                {{-- Card footer --}}
                <div class="flex items-center justify-between px-5 py-3 text-caption text-emerald-300">
                    <span>Privasi Terjaga · Tanpa Saldo di QR</span>
                    <span class="font-mono font-medium text-white">{{ $maskedNumber }}</span>
                </div>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-border bg-surface p-8 text-center shadow-xs">
            <x-ui.mascot variant="9" bubble="Nomor nasabah segera diterbitkan!" bubblePosition="bottom" class="mx-auto h-24 w-auto" />
            <p class="mt-8 text-label font-bold text-deep-green">Kartu Belum Tersedia</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">Petugas atau pengelola akan menerbitkan identitas digital setelah verifikasi warga selesai.</p>
        </div>
    @endif
</section>