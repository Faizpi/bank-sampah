<x-slot:title>Beranda</x-slot:title>
<x-slot:context>Akun warga</x-slot:context>

{{-- Balance Card --}}
<x-slot:balance>
    <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <img src="{{ asset('images/landing/mascot-3.png') }}" alt="" class="size-7 object-contain" aria-hidden="true">
                    <span class="text-caption font-bold uppercase tracking-wide text-forest-600">Bank Sampah Sindangheula</span>
                </div>
                <p class="mt-3 text-caption font-medium text-text-secondary">Saldo tersedia</p>
                @if ($hasLedger)
                    <p class="mt-1 amount-tabular text-amount-lg font-extrabold leading-none text-deep-green">
                        Rp{{ number_format($availableBalance, 0, ',', '.') }}
                    </p>
                @else
                    <p class="mt-1 text-h2 font-bold text-text-secondary">Belum ada saldo</p>
                @endif
            </div>
            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-forest-600 bg-success-bg px-3 py-1 text-caption font-semibold text-forest-700">
                <span class="size-1.5 rounded-full bg-forest-600"></span>
                Aktif
            </span>
        </div>

        @if ($hasLedger)
            <div class="mt-5 grid grid-cols-3 gap-2 border-t border-border pt-4">
                <div class="rounded-lg bg-warm-canvas px-3 py-2">
                    <p class="text-caption font-medium text-text-secondary">Tertahan</p>
                    <p class="mt-0.5 amount-tabular text-label font-bold text-deep-green">Rp{{ number_format($heldBalance, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg bg-warm-canvas px-3 py-2">
                    <p class="text-caption font-medium text-text-secondary">Total Masuk</p>
                    <p class="mt-0.5 amount-tabular text-label font-bold text-forest-700">Rp{{ number_format($totalIn, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg bg-warm-canvas px-3 py-2">
                    <p class="text-caption font-medium text-text-secondary">Total Keluar</p>
                    <p class="mt-0.5 amount-tabular text-label font-bold text-deep-green">Rp{{ number_format($totalOut, 0, ',', '.') }}</p>
                </div>
            </div>
        @endif
    </div>
</x-slot:balance>

{{-- Quick Actions --}}

<x-slot:quickActions>
    <div>
        <h2 class="text-label font-bold text-text-secondary mb-3">Aksi Cepat</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <a href="{{ route('citizen.pickup.create') }}"
                class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-sm">
                <div class="flex size-11 items-center justify-center rounded-xl bg-success-bg text-forest-600 transition-colors group-hover:bg-forest-600 group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/></svg>
            </div>
                <span class="text-caption font-semibold text-deep-green">Penjemputan</span>
            </a>

            <a href="{{ $groceryHref }}"
                class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-harvest-gold hover:shadow-sm">
                <div class="flex size-11 items-center justify-center rounded-xl bg-warning-bg text-harvest-gold transition-colors group-hover:bg-harvest-gold group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>
            </div>
                <span class="text-caption font-semibold text-deep-green">Tukar Sembako</span>
            </a>

            <a href="{{ route('citizen.estimate') }}"
                class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-sky-blue hover:shadow-sm">
                <div class="flex size-11 items-center justify-center rounded-xl bg-info-bg text-sky-blue transition-colors group-hover:bg-sky-blue group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg>
            </div>
                <span class="text-caption font-semibold text-deep-green">Estimasi</span>
            </a>

            <a href="{{ $customerCardHref }}"
                class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-sm">
                <div class="flex size-11 items-center justify-center rounded-xl bg-success-bg text-forest-600 transition-colors group-hover:bg-forest-600 group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            </div>
                <span class="text-caption font-semibold text-deep-green">Kartu Nasabah</span>
            </a>
        </div>
    </div>
</x-slot:quickActions>

{{-- Active Requests --}}
@if ($activePickups->isNotEmpty() || $activeWithdrawals->isNotEmpty())
<x-slot:activeRequests>
    <div>
        <h2 class="text-label font-bold text-text-secondary mb-3">Pengajuan Aktif</h2>
        <div class="space-y-2">
            @foreach ($activePickups as $pickup)
                <a href="{{ route('citizen.pickup.show', $pickup) }}" class="flex items-center justify-between rounded-xl border border-border bg-surface p-4 shadow-xs transition hover:border-forest-600 hover:shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-success-bg text-forest-600">
                            <x-public.icon name="truck" class="size-4" />
                        </div>
                        <div>
                            <p class="text-label font-semibold text-deep-green">Penjemputan</p>
                            <p class="text-caption text-text-secondary">{{ $pickup->request_number ?? 'Menunggu konfirmasi' }}</p>
                        </div>
                    </div>
                    <x-ui.status-badge :status="$pickup->status?->value ?? 'diajukan'" />
                </a>
            @endforeach
            @foreach ($activeWithdrawals as $withdrawal)
                <a href="{{ route('citizen.withdrawal.show', $withdrawal) }}" class="flex items-center justify-between rounded-xl border border-border bg-surface p-4 shadow-xs transition hover:border-harvest-gold hover:shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-warning-bg text-harvest-gold">
                            <x-public.icon name="banknote" class="size-4" />
                        </div>
                        <div>
                            <p class="text-label font-semibold text-deep-green">Pencairan</p>
                            <p class="text-caption text-text-secondary">Rp{{ number_format($withdrawal->amount ?? 0, 0, ',', '.') }}</p>
                        </div>
                    </div>
                    <x-ui.status-badge :status="$withdrawal->status?->value ?? 'diajukan'" />
                </a>
            @endforeach
        </div>
    </div>
</x-slot:activeRequests>
@endif

{{-- Recent Deposits --}}
<x-slot:recentHistory>
    <div>
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-label font-bold text-text-secondary">Setoran Terbaru</h2>
            <a href="{{ route('citizen.deposit-history') }}" class="text-caption font-semibold text-forest-600 hover:text-forest-700">Lihat semua</a>
        </div>
        @if ($recentDeposits->isEmpty())
            <div class="rounded-xl border border-border bg-surface p-6 text-center shadow-xs">
                <x-ui.mascot variant="9" class="mx-auto h-20 w-auto" />
                <p class="mt-3 text-label font-semibold text-deep-green">Belum ada setoran</p>
                <p class="mt-1 text-caption text-text-secondary">Setoran pertamamu akan muncul di sini.</p>
            </div>
        @else
            <div class="divide-y divide-border rounded-xl border border-border bg-surface shadow-xs">
                @foreach ($recentDeposits as $deposit)
                    <a href="{{ route('citizen.deposit-receipt', $deposit) }}" class="flex items-center justify-between px-4 py-3.5 transition hover:bg-warm-canvas first:rounded-t-xl last:rounded-b-xl">
                        <div>
                            <p class="text-label font-semibold text-deep-green">{{ $deposit->deposit_number }}</p>
                            <p class="mt-0.5 text-caption text-text-secondary">{{ $deposit->occurred_at?->translatedFormat('d M Y') }}</p>
                        </div>
                        <p class="amount-tabular text-label font-bold text-deep-green">Rp{{ number_format((int)($deposit->total_value ?? 0), 0, ',', '.') }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-slot:recentHistory>

{{-- Welcome Section --}}
<section aria-labelledby="citizen-dashboard-title"
    class="rounded-2xl border border-border bg-surface p-5 shadow-xs sm:p-6">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-2">
            <div class="flex items-center gap-2 text-label text-forest-600">
                <x-public.icon name="leaf" class="size-4" />
                <span>Beranda Warga</span>
            </div>
            <h1 id="citizen-dashboard-title" class="text-h2 font-bold text-deep-green">
                Halo, {{ $actorName }}!
            </h1>
            <p class="text-body-sm text-text-secondary">
                Kelola pemilahan sampah, ajukan layanan, dan pantau kartu identitas nasabah Anda.
            </p>
            <div class="flex flex-wrap gap-2 pt-1">
                <a href="{{ $customerCardHref }}"
                    class="inline-flex min-h-touch items-center gap-2 rounded-xl border border-forest-600 px-4 text-label font-semibold text-forest-700 shadow-xs transition hover:bg-success-bg">
                    <x-public.icon name="book-open" class="size-4" />
                    Kartu Nasabah
                </a>
                <a href="{{ route('citizen.withdrawal.create') }}"
                    class="inline-flex min-h-touch items-center gap-2 rounded-xl border border-border px-4 text-label font-semibold text-text-primary shadow-xs transition hover:border-harvest-gold">
                    <x-public.icon name="banknote" class="size-4" />
                    Cairkan Saldo
                </a>
            </div>
        </div>
        <div class="flex shrink-0 items-center justify-center">
            <x-ui.mascot variant="2" bubble="Yuk pilah sampahmu hari ini!" bubblePosition="top"
                class="h-28 w-auto sm:h-32" animate />
        </div>
    </div>
</section>