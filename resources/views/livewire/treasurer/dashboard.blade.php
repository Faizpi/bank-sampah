<x-slot:title>Tugas hari ini</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<div class="grid gap-6">
    <x-ui.page-heading id="treasurer-dashboard-title" title="Pembayaran hari ini" description="Periksa antrean dan catat pembayaran tunai." mascot="12" />
    <div class="grid grid-cols-2 gap-3">
        @if ($canPay)
            <x-ui.action-card :href="route('treasurer.withdrawal.payments')" icon="banknote" title="Bayar pencairan" compact />
        @endif
        @if ($canViewReports)
            <x-ui.action-card :href="route('treasurer.reports')" icon="bar-chart-3" title="Laporan dan ekspor" compact />
        @endif
    </div>
    <x-ui.panel title="Pencairan siap dibayar">
        @if ($readyPayments->isEmpty())
            <x-ui.empty-state title="Belum ada pencairan siap dibayar" description="Pengajuan baru akan muncul di sini."
                :action-label="$canViewReports ? 'Periksa laporan pencairan' : null"
                :action-href="$canViewReports ? route('treasurer.reports') : null" />
        @else
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                <p class="font-semibold text-deep-green">{{ $readyPayments->count() }} pembayaran memerlukan tindakan</p>
                <a href="{{ route('treasurer.withdrawal.payments') }}" class="app-interactive inline-flex min-h-touch items-center gap-2 rounded-md bg-forest-600 px-4 font-semibold text-white hover:bg-forest-700">
                    <x-public.icon name="banknote" />Mulai pembayaran
                </a>
            </div>
            <div class="grid gap-3">
                @foreach ($readyPayments as $withdrawal)
                    <a href="{{ route('treasurer.withdrawal.payments') }}" class="app-row-action flex min-h-touch flex-wrap items-center justify-between gap-3 rounded-md border border-border p-4">
                        <span class="min-w-0"><span class="block break-words font-bold text-deep-green">{{ $withdrawal->customer?->name ?? 'Nasabah' }}</span><span class="mt-1 block break-words text-body-sm text-text-secondary">{{ $withdrawal->request_number }} · {{ $withdrawal->pickup_date?->translatedFormat('d M Y') ?? 'Tanggal belum tersedia' }}</span></span>
                        <span class="amount-tabular font-bold text-forest-700">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</span>
                    </a>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap items-baseline justify-between gap-2 border-t border-border pt-4" aria-label="Ringkasan antrean pembayaran">
                <p class="text-body text-text-secondary">Total {{ $readyPayments->count() }} antrean yang tampil</p>
                <p class="amount-tabular text-title font-bold text-deep-green">Rp {{ number_format($readyPaymentTotal, 0, ',', '.') }}</p>
            </div>
            <a href="{{ route('treasurer.withdrawal.payments') }}" class="mt-4 inline-flex min-h-touch items-center gap-2 font-semibold text-forest-700 underline underline-offset-4 hover:text-deep-green">Buka seluruh antrean pembayaran<x-public.icon name="arrow-right" /></a>
        @endif
    </x-ui.panel>
</div>
