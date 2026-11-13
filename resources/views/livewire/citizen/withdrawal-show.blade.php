<x-slot:title>Pencairan tunai</x-slot:title>
<section class="grid gap-6" aria-labelledby="withdrawal-status-title">
    <x-ui.page-heading id="withdrawal-status-title" title="Status pencairan tunai" mascot="5" />
    @if (session('success'))<p role="status" class="rounded-xl bg-success-bg p-4">{{ session('success') }}</p>@endif
    @if ($errors->any())<div role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @php($locked = $withdrawal->exception_reported_at && ! $withdrawal->exception_resolved_at)
    <x-ui.panel title="Pengajuan Anda">
        <p class="font-bold">{{ $withdrawal->request_number }}</p>
        <p class="text-h2 amount-tabular">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</p>
        <p class="mt-2 font-semibold">{{ $locked && $withdrawal->exception_kind === 'cancellation_request' ? 'Permintaan batal sedang dikonfirmasi' : \App\Support\StatusLabel::for($withdrawal->status) }}</p>
        <p>{{ $withdrawal->pickup_location }} · {{ $withdrawal->pickup_date?->translatedFormat('d M Y') }}</p>
        @if ($locked)
            <p role="alert" class="mt-3">{{ $withdrawal->exception_kind === 'cancellation_request' ? 'Bendahara perlu memastikan uang belum diserahkan sebelum pembatalan dan pengembalian saldo.' : 'Admin sedang memastikan penyerahan uang. Jangan meminta penyerahan kedua.' }}</p>
        @elseif ($withdrawal->status->value === 'menunggu_bendahara')
            <p class="mt-3">Menunggu Bendahara mengambil pembayaran saat uang tunai tersedia.</p>
        @elseif ($withdrawal->status->value === 'diproses_bendahara')
            <p class="mt-3">Pembayaran ditangani {{ $withdrawal->payer?->name ?? 'Bendahara' }}. Pastikan nama penerima dan nominal sesuai saat mengambil uang.</p>
        @endif
        <p class="mt-3">{{ $withdrawal->balanceHold?->status === 'aktif' ? 'Saldo disisihkan sampai uang tunai diserahkan.' : ($withdrawal->receipt_ledger_entry_id ? 'Pembayaran tunai sudah tercatat pada saldo.' : 'Tidak ada saldo yang disisihkan untuk pengajuan ini.') }}</p>
        @if ($withdrawal->cancellation_reason || $withdrawal->rejection_reason)<p>Alasan: {{ $withdrawal->cancellation_reason ?? $withdrawal->rejection_reason }}</p>@endif
        @if (! $withdrawal->status->isTerminal() && ! $locked)
            <x-ui.button variant="secondary" wire:click="cancel" wire:loading.attr="disabled">{{ $withdrawal->payer_id === null ? 'Batalkan pengajuan' : 'Minta pembatalan' }}</x-ui.button>
        @endif
        @if ($withdrawal->receipt_ledger_entry_id)<a class="inline-flex min-h-touch items-center font-bold text-forest-600" href="{{ route('citizen.withdrawal.receipt', $withdrawal) }}">Lihat kuitansi</a>@endif
    </x-ui.panel>
    <details class="rounded-xl border border-border bg-surface p-4"><summary class="min-h-touch cursor-pointer font-semibold">Lihat riwayat status</summary>
        @foreach ($withdrawal->statusHistory->sortBy('occurred_at') as $history)<p class="border-t border-border py-2">{{ $history->occurred_at->translatedFormat('d M Y H:i') }} · {{ \App\Support\StatusLabel::for($history->new_status) }} · {{ $history->reason }}</p>@endforeach
    </details>
</section>
