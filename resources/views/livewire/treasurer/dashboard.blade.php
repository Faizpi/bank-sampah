<x-slot:title>Tugas hari ini</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<x-slot:todayTasks>
    <x-ui.panel title="Pencairan siap dibayar" description="Hanya pencairan dalam wilayah atau tugas Anda yang tampil.">
        @if ($readyPayments->isEmpty())
            <x-ui.empty-state title="Belum ada pencairan siap dibayar" description="Pencairan yang disetujui dan siap dibayar akan muncul di sini." />
        @else
            <div class="mb-4 flex flex-col gap-1 border-b border-border pb-4 sm:flex-row sm:items-baseline sm:justify-between">
                <p class="text-body-sm text-text-secondary"><strong class="text-deep-green">{{ $readyPayments->count() }}</strong> antrean aktif</p>
                <p class="amount-tabular text-title font-bold text-deep-green">Rp {{ number_format($readyPaymentTotal, 0, ',', '.') }}</p>
            </div>
            <div class="grid gap-3">
                @foreach ($readyPayments as $withdrawal)
                    <a href="{{ route('treasurer.withdrawal.payments') }}" class="flex min-h-[72px] items-center justify-between gap-3 rounded-md border border-warning-bg bg-warning-bg p-4 transition hover:border-harvest-gold">
                        <span class="min-w-0"><span class="block text-label font-bold text-deep-green">{{ $withdrawal->request_number }}</span><span class="mt-1 block truncate text-body-sm text-text-secondary">{{ $withdrawal->customer?->name ?? 'Nasabah' }} · {{ $withdrawal->pickup_date?->translatedFormat('d M Y') ?? 'Tanggal belum tersedia' }}</span></span>
                        <span class="shrink-0 amount-tabular text-label font-bold text-deep-green">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</span>
                    </a>
                @endforeach
            </div>
            <a href="{{ route('treasurer.withdrawal.payments') }}" class="mt-4 inline-flex min-h-touch items-center gap-2 text-label font-bold text-forest-700 underline underline-offset-4">Buka seluruh antrean pembayaran</a>
        @endif
    </x-ui.panel>
</x-slot:todayTasks>

<div class="grid gap-6">
<section aria-labelledby="treasurer-dashboard-title" class="rounded-2xl border border-border bg-surface p-5 shadow-xs sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/landing/mascot-3.png') }}" alt="" class="size-7 object-contain" aria-hidden="true">
                <span class="text-caption font-semibold text-forest-600 uppercase tracking-wide">Bendahara Bank Sampah</span>
            </div>
            <h2 id="treasurer-dashboard-title" class="mt-2 text-h2 font-bold text-deep-green">Ringkas keuangan hari ini</h2>
            <p class="mt-1.5 text-body-sm text-text-secondary">Selesaikan pencairan yang siap dibayar, lalu periksa laporan sesuai akses Anda.</p>
        </div>
        <x-ui.mascot variant="12" bubble="Rekap keuangan hari ini!" bubblePosition="top" class="h-24 w-auto shrink-0 sm:h-28" animate />
    </div>
</section>

<section aria-labelledby="treasurer-actions-title">
    <h2 id="treasurer-actions-title" class="mb-3 text-label font-bold text-text-secondary">Aksi cepat</h2>
    <div class="grid grid-cols-2 gap-3">
        @if ($canPay)
        <a href="{{ route('treasurer.withdrawal.payments') }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-harvest-gold hover:shadow-sm">
            <div class="flex size-11 items-center justify-center rounded-xl bg-warning-bg text-harvest-gold transition-colors group-hover:bg-harvest-gold group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>
                </svg>
            </div>
            <span class="text-caption font-semibold text-deep-green">Bayar pencairan</span>
        </a>
        @endif

        @if ($canViewReports)
        <a href="{{ route('treasurer.reports') }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-sm">
            <div class="flex size-11 items-center justify-center rounded-xl bg-success-bg text-forest-600 transition-colors group-hover:bg-forest-600 group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0M9 12h.01M13 12h2M9 16h.01M13 16h2"/>
                </svg>
            </div>
            <span class="text-caption font-semibold text-deep-green">Laporan &amp; Ekspor</span>
        </a>
        @endif

    </div>
</section>

</div>{{-- /grid gap-6 (Livewire single root) --}}
