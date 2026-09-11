<x-filament-panels::page>
    <div class="space-y-6">
        <section class="backoffice-page-intro" aria-labelledby="work-queue-heading">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    <img src="{{ asset('images/landing/mascot-8.png') }}" alt="Maskot badak siap membantu memantau antrean operasional" class="h-24 w-24 shrink-0 object-contain sm:h-28 sm:w-28">
                    <div>
                    <p class="text-sm font-semibold text-forest-700">Admin operasional</p>
                    <h2 id="work-queue-heading" class="mt-1 text-2xl font-bold text-deep-green">Pusat perhatian hari ini</h2>
                    <p class="mt-2 max-w-2xl text-sm text-text-secondary">Prioritaskan antrean yang memengaruhi layanan warga dan saldo.</p>
                    </div>
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:text-right"><div><dt class="text-text-secondary">Lingkungan</dt><dd class="font-bold text-deep-green">{{ $environment }}</dd></div><div><dt class="text-text-secondary">Area aktif</dt><dd class="font-bold text-deep-green">{{ $activeAreas ?? 'Tidak tersedia' }}</dd></div></dl>
            </div>
            @if ($maintenanceEnabled)
                <p class="mt-5 flex items-start gap-3 rounded-lg border border-warning-300 bg-warning-100 px-4 py-3 text-sm font-semibold leading-6 text-warning-950"><x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />Pemeliharaan aktif. Pastikan pekerjaan yang sedang berjalan aman sebelum melanjutkan.</p>
            @endif
        </section>

        <section aria-labelledby="attention-counts-title">
            <div class="flex items-end justify-between gap-4"><div><h2 id="attention-counts-title" class="text-xl font-bold text-gray-950">Antrean yang perlu perhatian</h2><p class="mt-1 text-sm text-gray-600">Diperbarui {{ $lastUpdated }} WIB.</p></div></div>
            @php($hasPendingQueue = collect($queues)->contains(fn (array $queue): bool => $queue['count'] > 0))
            @php($pendingQueues = collect($queues)->filter(fn (array $queue): bool => $queue['count'] > 0))
            @if (! $hasVisibleQueues)
                    <div class="mt-4 flex items-start gap-3 rounded-xl border border-border bg-surface p-5 text-sm text-text-secondary"><x-filament::icon icon="heroicon-o-queue-list" aria-hidden="true" /><p>Tidak ada antrean yang tersedia untuk peran ini.</p></div>
            @elseif (collect($queues)->every(fn (array $queue): bool => $queue['count'] === 0))
                    <div class="mt-4 flex items-start gap-3 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm leading-6 text-success-900"><x-filament::icon icon="heroicon-o-check-circle" aria-hidden="true" /><div><strong>Semua antrean prioritas selesai.</strong><span class="mt-1 block font-normal">Tidak ada pekerjaan mendesak saat ini.</span></div></div>
            @endif
            @if ($hasPendingQueue)
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($pendingQueues as $queue)
                        <a href="{{ $queue['href'] }}" class="group flex min-h-52 flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-500 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">
                            <div class="flex items-start justify-between gap-3"><span class="flex size-10 items-center justify-center rounded-lg bg-primary-50 text-primary-700"><x-filament::icon icon="heroicon-o-clipboard-document-check" class="size-5" aria-hidden="true" /></span><strong class="text-3xl font-bold tabular-nums text-primary-800">{{ $queue['count'] }}</strong></div>
                            <div class="mt-4 flex-1"><h3 class="text-sm font-semibold leading-5 text-gray-900">{{ $queue['label'] }}</h3><p class="mt-2 text-sm leading-6 text-gray-600">{{ $queue['description'] }}</p></div>
                            <span class="mt-4 inline-flex min-h-10 items-center gap-1 text-sm font-semibold text-primary-700 underline underline-offset-4">{{ $queue['cta'] }}<x-filament::icon icon="heroicon-m-arrow-right" class="size-4" aria-hidden="true" /></span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <details class="group rounded-xl border border-primary-100 bg-primary-50 p-5">
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 rounded-md text-lg font-bold text-primary-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">
                <span>Panduan pemeriksaan yang aman</span>
                <svg data-disclosure-chevron viewBox="0 0 24 24" class="size-5 shrink-0 text-primary-700 transition-transform group-open:rotate-180 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </summary>
            <ol class="mt-5 grid list-none gap-x-6 gap-y-4 text-sm text-primary-950 sm:grid-cols-3">
                <li class="grid grid-cols-[1.75rem_minmax(0,1fr)] items-start gap-3">
                    <span aria-hidden="true" class="flex size-7 items-center justify-center rounded-sm bg-surface text-caption font-bold text-primary-800">1</span>
                    <div><p class="font-bold">Konteks</p><p class="mt-1 leading-6">Periksa warga, wilayah, bukti, dan riwayat.</p></div>
                </li>
                <li class="grid grid-cols-[1.75rem_minmax(0,1fr)] items-start gap-3">
                    <span aria-hidden="true" class="flex size-7 items-center justify-center rounded-sm bg-surface text-caption font-bold text-primary-800">2</span>
                    <div><p class="font-bold">Dampak</p><p class="mt-1 leading-6">Pastikan kapasitas, saldo, dana ditahan, dan batas waktu sesuai.</p></div>
                </li>
                <li class="grid grid-cols-[1.75rem_minmax(0,1fr)] items-start gap-3">
                    <span aria-hidden="true" class="flex size-7 items-center justify-center rounded-sm bg-surface text-caption font-bold text-primary-800">3</span>
                    <div><p class="font-bold">Keputusan</p><p class="mt-1 leading-6">Setujui atau tolak, lalu catat alasannya.</p></div>
                </li>
            </ol>
        </details>
    </div>
</x-filament-panels::page>
