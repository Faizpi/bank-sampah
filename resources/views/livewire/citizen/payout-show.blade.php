<x-slot:title>Status pencairan digital (simulasi)</x-slot:title>
<section class="grid gap-6" aria-labelledby="payout-status-title" data-print-area @if (! $payout->isTerminal()) wire:poll.10s @endif>
    <x-ui.page-heading id="payout-status-title" title="Pencairan digital (simulasi)" mascot="5" />
    <p class="rounded-xl border border-border bg-warning-bg p-4 font-semibold">Simulasi Xendit (tanpa pengiriman uang riil). Bukti ini bukan struk resmi bank atau dompet digital.</p>
    @if (session('payout_success'))<p role="status">{{ session('payout_success') }}</p>@endif
    @if ($errors->any())<div role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @php
        $status = $payout->status;
        $statusTone = match (true) {
            $status === 'succeeded' => 'success',
            in_array($status, ['failed', 'cancelled', 'reversed'], true) => 'danger',
            $status === 'uncertain' => 'warning',
            default => 'processing',
        };
        $statusLabel = match ($status) {
            'succeeded' => 'Pencairan berhasil',
            'failed' => 'Pencairan gagal',
            'reversed' => 'Saldo pencairan dikembalikan',
            'cancelled' => 'Pencairan dibatalkan',
            'uncertain' => 'Status sedang diperiksa',
            default => 'Pencairan sedang diproses',
        };
        $toneClasses = [
            'success' => 'bg-success-bg text-forest-600',
            'danger' => 'bg-danger-bg text-terracotta',
            'warning' => 'bg-warning-bg text-deep-green',
            'processing' => 'bg-info-bg text-sky-blue',
        ];
    @endphp
    <x-ui.panel>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3" role="status" aria-live="polite">
                <span class="grid size-12 shrink-0 place-items-center rounded-full {{ $toneClasses[$statusTone] }}" aria-hidden="true">
                    @if ($statusTone === 'success')
                        <svg viewBox="0 0 24 24" fill="none" class="size-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path class="payout-status-check" pathLength="100" d="m5 12.5 4.5 4.5L19 7.5" />
                        </svg>
                    @elseif ($statusTone === 'processing')
                        <svg viewBox="0 0 24 24" fill="none" class="payout-status-spinner size-6" stroke="currentColor" stroke-linecap="round">
                            <circle cx="12" cy="12" r="9" stroke-width="2.5" opacity=".22" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke-width="2.5" />
                        </svg>
                    @elseif ($statusTone === 'warning')
                        <svg viewBox="0 0 24 24" fill="none" class="size-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9" /><path d="M12 7.5V12l3 2" />
                        </svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" class="size-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9" /><path d="m15 9-6 6m0-6 6 6" />
                        </svg>
                    @endif
                </span>
                <div class="min-w-0">
                    <p class="text-caption text-text-secondary">Status pencairan</p>
                    <h2 class="break-words text-title text-deep-green">{{ $statusLabel }}</h2>
                </div>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1">
                <img src="{{ asset($channel['logo']) }}" alt="Logo {{ $channel['name'] }}" class="h-8 w-24 object-contain object-right">
                <p class="text-caption text-text-secondary">{{ $channel['name'] }} (simulasi)</p>
            </div>
        </div>
        <div class="mt-6 border-t border-dashed border-border pt-5">
            <p class="break-all text-body-sm text-text-secondary">{{ $payout->request_number }}</p>
            <p class="mt-1 text-h1 amount-tabular text-deep-green">Rp {{ number_format($payout->amount, 0, ',', '.') }}</p>
        </div>
        <dl class="mt-5 divide-y divide-dashed divide-border border-t border-dashed border-border">
            <div class="flex items-baseline justify-between gap-4 py-3">
                <dt class="shrink-0 text-body-sm text-text-secondary">Nama pemilik tujuan uji</dt>
                <dd class="min-w-0 text-right font-semibold break-words">{{ $payout->recipient['name'] }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-4 py-3">
                <dt class="shrink-0 text-body-sm text-text-secondary">Nomor tujuan</dt>
                <dd class="min-w-0 text-right font-semibold amount-tabular">{{ $payout->maskedNumber() }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-4 py-3">
                <dt class="shrink-0 text-body-sm text-text-secondary">Pelaksana</dt>
                <dd class="min-w-0 text-right font-semibold">Otomatis oleh sistem</dd>
            </div>
            <div class="flex items-baseline justify-between gap-4 py-3">
                <dt class="shrink-0 text-body-sm text-text-secondary">Diajukan</dt>
                <dd class="min-w-0 text-right font-semibold">{{ $payout->created_at?->translatedFormat('d M Y H:i') }}</dd>
            </div>
            @if ($payout->settled_at)
                <div class="flex items-baseline justify-between gap-4 py-3">
                    <dt class="shrink-0 text-body-sm text-text-secondary">Pencairan diselesaikan</dt>
                    <dd class="min-w-0 text-right font-semibold">{{ $payout->settled_at->translatedFormat('d M Y H:i') }}</dd>
                </div>
            @endif
            @if ($payout->reversed_at)
                <div class="flex items-baseline justify-between gap-4 py-3">
                    <dt class="shrink-0 text-body-sm text-text-secondary">Saldo dikembalikan</dt>
                    <dd class="min-w-0 text-right font-semibold">{{ $payout->reversed_at->translatedFormat('d M Y H:i') }}</dd>
                </div>
            @endif
            @if ($payout->provider_id)
                <div class="flex items-baseline justify-between gap-4 py-3">
                    <dt class="shrink-0 text-body-sm text-text-secondary">Referensi Xendit</dt>
                    <dd class="min-w-0 text-right text-body-sm font-semibold break-all">{{ $payout->provider_id }}</dd>
                </div>
            @endif
        </dl>
        <p class="mt-4 border-t border-dashed border-border pt-4 text-body-sm" role="status">{{ $payout->balanceHold?->isActive() ? 'Saldo masih disisihkan sampai hasil pencairan dipastikan.' : match ($payout->status) { 'succeeded' => 'Pencairan sudah tercatat pada saldo.', 'failed', 'cancelled' => 'Saldo yang disisihkan sudah tersedia kembali.', 'reversed' => 'Saldo pencairan ini sudah dikembalikan.', default => 'Tidak ada saldo yang disisihkan untuk pengajuan ini.' } }}</p>
        @if ($payout->status === 'queued' && $payout->lease === null)<x-ui.button variant="secondary" wire:click="cancel" wire:loading.attr="disabled" class="mt-4">Batalkan sebelum diproses</x-ui.button>@endif
    </x-ui.panel>
    <div class="flex flex-wrap gap-3" data-print-hide><a class="app-row-action inline-flex min-h-touch items-center gap-2 rounded-md px-3 font-semibold text-forest-600" href="{{ route('citizen.withdrawal-history', ['metode' => 'digital']) }}"><x-public.icon name="history" size="size-5" />Riwayat pencairan</a>@if ($payout->ledger_entry_id)<x-ui.button type="button" variant="secondary" data-print-button>Cetak bukti simulasi</x-ui.button>@endif</div>
</section>
