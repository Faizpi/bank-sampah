<x-slot:title>Kartu nasabah</x-slot:title>
<x-slot:context>Nomor dan QR untuk layanan bank sampah</x-slot:context>

<section aria-labelledby="customer-card-title" class="space-y-5">
    {{-- Page header --}}
    <div class="flex flex-col gap-5 rounded-2xl border border-border bg-surface p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div class="space-y-2">
            <p class="text-label font-semibold text-forest-700">Identitas warga</p>
            <h1 id="customer-card-title" class="text-h2 font-bold text-deep-green sm:text-h1">Kartu Digital Nasabah</h1>
            <p class="max-w-xl text-body text-text-secondary">
                Tunjukkan QR ini kepada petugas saat setoran atau penjemputan.
            </p>
        </div>
        <div class="hidden shrink-0 items-center justify-center sm:flex">
            <x-ui.mascot variant="5" bubble="Tunjukkan QR ini ke petugas ya!" bubblePosition="top"
                class="h-28 w-auto sm:h-32" />
        </div>
    </div>

    @if ($available)
        <div data-customer-card-page class="mx-auto w-full max-w-[40rem]">
            <article id="customer-card-printable" data-customer-card-printable class="customer-card-grid relative isolate flex aspect-[1.586/1] flex-col overflow-hidden rounded-xl border border-border bg-surface shadow-sm" aria-labelledby="customer-card-name">
                <img data-customer-card-preview-image hidden src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="Preview kartu nasabah {{ $customerNumber }}" class="size-full object-cover">
                <div data-customer-card-preview-fallback class="contents">
                <div class="pointer-events-none absolute inset-0 bg-surface/70" aria-hidden="true"></div>
                <div class="relative grid min-h-0 flex-1 grid-cols-[minmax(0,1fr)_7.25rem] gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_10.5rem] sm:gap-6 sm:p-6">
                    <div class="flex min-w-0 flex-col">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="whitespace-nowrap text-[0.5rem] font-bold uppercase tracking-[0.12em] text-forest-700 sm:text-caption"><span class="sm:hidden">Kartu nasabah</span><span class="hidden sm:inline">Kartu nasabah digital</span></p>
                                <p class="mt-0.5 hidden text-caption font-semibold text-text-secondary sm:block sm:text-body-sm">Bank Sampah Sindangheula</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-forest-600/25 bg-success-bg px-1.5 py-0.5 text-[0.5rem] font-bold text-forest-700 sm:px-2 sm:py-1 sm:text-caption">
                                <span class="size-1.5 rounded-full bg-forest-600" aria-hidden="true"></span>
                                Aktif
                            </span>
                        </div>

                        <div class="mt-auto min-w-0 pt-2 sm:pt-3">
                            <p class="text-[0.5rem] font-semibold uppercase tracking-[0.1em] text-text-secondary sm:text-caption">Nama nasabah</p>
                            <h2 id="customer-card-name" data-customer-card-name class="mt-0.5 break-words text-[1rem] font-bold leading-tight text-deep-green sm:mt-1 sm:text-h2">{{ $customerName }}</h2>

                            <dl class="mt-2 grid gap-1 border-t border-border/80 pt-2 text-[0.5rem] sm:mt-4 sm:grid-cols-2 sm:gap-x-5 sm:gap-y-2 sm:pt-3 sm:text-caption">
                                <div>
                                    <dt class="font-medium text-text-secondary">Nomor nasabah</dt>
                                    <dd data-customer-card-number class="amount-tabular mt-0.5 text-[0.625rem] font-bold text-deep-green sm:text-body-sm">{{ $customerNumber }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-text-secondary">Wilayah layanan</dt>
                                    <dd data-customer-card-area class="mt-0.5 truncate text-[0.625rem] font-bold text-deep-green sm:text-body-sm">{{ $serviceArea }}</dd>
                                </div>
                            </dl>
                        </div>

                        <p class="mt-3 hidden text-[0.5625rem] font-medium text-text-secondary sm:block sm:text-caption">Gunakan untuk setoran, penjemputan, dan verifikasi di layanan resmi.</p>
                    </div>

                    <div class="flex min-w-0 flex-col items-center justify-center border-l border-border/80 pl-3 sm:pl-5">
                        <div class="w-full rounded-lg border border-border bg-surface p-1 shadow-xs sm:p-2">
                            @if ($qrImageSrc)
                                <img data-customer-card-qr src="{{ $qrImageSrc }}" alt="Kode QR nasabah {{ $customerNumber }}" width="200" height="200" class="aspect-square w-full object-contain">
                            @else
                                <div role="status" class="flex aspect-square items-center justify-center bg-disabled-bg p-2 text-center text-[0.5625rem] text-text-secondary sm:text-caption">QR belum aktif</div>
                            @endif
                        </div>
                        <p class="mt-1.5 hidden text-center text-[0.5625rem] font-semibold leading-tight text-text-secondary sm:block sm:text-caption">Pindai untuk verifikasi</p>
                    </div>
                </div>

                <div class="relative flex items-center justify-between gap-2 border-t border-border/80 bg-warm-canvas/90 px-3 py-1 text-[0.5rem] text-text-secondary sm:px-6 sm:py-2 sm:text-caption">
                    <span>QR tanpa data saldo</span>
                    <span class="amount-tabular font-semibold text-deep-green">{{ $maskedNumber }}</span>
                </div>
                </div>
            </article>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-body-sm text-text-secondary">Simpan sebagai PNG untuk dicetak.</p>
                <div class="flex gap-2">
                    <button type="button" data-customer-card-download class="inline-flex min-h-touch flex-1 items-center justify-center gap-2 rounded-md border border-forest-600 bg-surface px-3 text-label font-bold text-forest-700 transition hover:bg-success-bg active:translate-y-px">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M5 21h14"/></svg>
                        Simpan PNG
                    </button>
                </div>
            </div>
            <p id="customer-card-status" data-customer-card-status class="sr-only" aria-live="polite"></p>
        </div>
    @else
        <div class="rounded-xl border border-border bg-surface p-8 text-center shadow-xs">
            <x-ui.mascot variant="9" bubble="Nomor nasabah segera diterbitkan!" bubblePosition="bottom" class="mx-auto h-24 w-auto" />
            <p class="mt-8 text-label font-bold text-deep-green">Kartu Belum Tersedia</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">Petugas atau pengelola akan menerbitkan identitas digital setelah verifikasi warga selesai.</p>
        </div>
    @endif
</section>
