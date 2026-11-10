<x-slot:title>Beranda</x-slot:title>

<div class="grid gap-6">

{{-- Balance Card --}}
<section aria-label="Saldo Anda">
    <div class="rounded-lg border border-border bg-surface p-5 shadow-xs sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h1 id="citizen-dashboard-title" class="min-w-0 break-words text-h2 font-bold text-deep-green">Halo, {{ $actorName }}!</h1>
            <div class="shrink-0" aria-hidden="true"><x-ui.mascot variant="2" class="size-16" /></div>
        </div>
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">

                <p class="text-body font-medium text-text-secondary">Saldo tersedia</p>
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
            <div class="mt-5 grid gap-3 border-t border-border pt-4 sm:grid-cols-3">
                <div class="flex items-baseline justify-between gap-3 sm:block">
                    <p class="text-body-sm font-medium text-text-secondary">Tertahan</p>
                    <p class="mt-0.5 amount-tabular text-body font-bold text-deep-green">Rp{{ number_format($heldBalance, 0, ',', '.') }}</p>
                </div>
                <div class="flex items-baseline justify-between gap-3 sm:block">
                    <p class="text-body-sm font-medium text-text-secondary">Total Masuk</p>
                    <p class="mt-0.5 amount-tabular text-body font-bold text-forest-700">Rp{{ number_format($totalIn, 0, ',', '.') }}</p>
                </div>
                <div class="flex items-baseline justify-between gap-3 sm:block">
                    <p class="text-body-sm font-medium text-text-secondary">Total Keluar</p>
                    <p class="mt-0.5 amount-tabular text-body font-bold text-deep-green">Rp{{ number_format($totalOut, 0, ',', '.') }}</p>
                </div>
            </div>
        @endif
    </div>
</section>

{{-- Quick Actions --}}

<section aria-labelledby="citizen-quick-actions">
    <h2 id="citizen-quick-actions" class="mb-3 text-body font-semibold text-text-secondary">Tindakan cepat</h2>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-ui.action-card :href="route('citizen.pickup.create')" icon="truck" title="Penjemputan" compact />
        <x-ui.action-card :href="$groceryHref" icon="package-open" title="Tukar Sembako" compact />
        <x-ui.action-card :href="route('citizen.estimate')" icon="scale" title="Estimasi" compact />
        <x-ui.action-card :href="route('citizen.withdrawal.create')" icon="banknote" title="Cairkan Saldo" compact />
    </div>
</section>

{{-- Active Requests --}}
<section aria-label="Pengajuan aktif">
    <div>
        <h2 id="pengajuan-aktif" class="text-label font-bold text-text-secondary mb-3">Pengajuan Aktif</h2>
        @if ($activePickups->isEmpty() && $activeWithdrawals->isEmpty() && $activeGroceries->isEmpty() && $activePayouts->isEmpty())<p>Tidak ada pengajuan aktif.</p>@endif
        <div class="space-y-2">
            @foreach ($activePayouts as $payout)
                <a href="{{ route('citizen.payout.show', $payout) }}" class="app-row-action grid gap-2 rounded-xl border border-border bg-surface p-4"><span class="break-all font-bold">{{ $payout->request_number }}</span><span>{{ $payout->label() }} (digital simulasi)</span><span class="amount-tabular font-semibold">Rp {{ number_format($payout->amount, 0, ',', '.') }}</span></a>
            @endforeach
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
                    <x-ui.status-badge :status="$pickup->status->value === 'menunggu_petugas' ? 'pending' : 'in_progress'">{{ \App\Support\StatusLabel::for($pickup->status) }}</x-ui.status-badge>
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
                    <x-ui.status-badge :status="$withdrawal->payer_id === null ? 'pending' : 'in_progress'">{{ \App\Support\StatusLabel::for($withdrawal->status) }}</x-ui.status-badge>
                </a>
            @endforeach
            @foreach ($activeGroceries as $redemption)
                <a href="{{ route('citizen.grocery.show', $redemption) }}" class="flex items-center justify-between rounded-xl border border-border bg-surface p-4 shadow-xs transition hover:border-harvest-gold hover:shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-warning-bg text-harvest-gold">
                            <x-public.icon name="package-open" class="size-4" />
                        </div>
                        <div>
                            <p class="text-label font-semibold text-deep-green">Tukar Sembako</p>
                            <p class="text-caption text-text-secondary">{{ $redemption->request_number }}</p>
                        </div>
                    </div>
                    <x-ui.status-badge :status="$redemption->claimed_by_id === null ? 'pending' : 'in_progress'">{{ \App\Support\StatusLabel::for($redemption->status) }}</x-ui.status-badge>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Recent Deposits --}}
<section aria-label="Setoran terbaru">
    <div>
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-label font-bold text-text-secondary">Setoran Terbaru</h2>
            <a href="{{ route('citizen.deposit-history') }}" class="text-caption font-semibold text-forest-600 hover:text-forest-700">Lihat semua</a>
        </div>
        @if ($recentDeposits->isEmpty())
            <div class="rounded-xl border border-border bg-surface p-6 text-center shadow-xs">
                <x-ui.mascot variant="9" class="mx-auto h-20 w-auto" />
                <p class="mt-3 text-label font-semibold text-deep-green">Belum ada setoran</p>

            </div>
        @else
            <div class="divide-y divide-border rounded-xl border border-border bg-surface shadow-xs">
                @foreach ($recentDeposits as $deposit)
                    <a href="{{ route('citizen.deposit-receipt', $deposit) }}" class="flex items-center justify-between px-4 py-3.5 transition hover:bg-warm-canvas first:rounded-t-xl last:rounded-b-xl">
                        <div>
                            <p class="text-label font-semibold text-deep-green">{{ $deposit->deposit_number }}</p>
                            <p class="mt-0.5 text-caption text-text-secondary">{{ $deposit->occurred_at?->translatedFormat('d M Y') }}</p>
                        </div>
                        <p class="amount-tabular text-label font-bold text-deep-green">Rp{{ number_format($deposit->effectiveTotalValue(), 0, ',', '.') }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
</div>
