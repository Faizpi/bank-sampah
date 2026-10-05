<x-slot:title>{{ $withdrawal->request_number }}</x-slot:title>
<section class="space-y-6">
    <h1 class="text-h1 font-bold">Pencairan tunai</h1>
    @if (session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if ($errors->any())<div role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <x-ui.panel title="Status pengajuan">
        <p class="font-bold">{{ $withdrawal->request_number }} · {{ \App\Support\StatusLabel::for($withdrawal->status) }}</p>
        <p>Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</p>
        <p>Saldo tertahan: {{ \App\Support\StatusLabel::for($withdrawal->balanceHold?->status) }}. Penahanan belum merupakan mutasi keluar.</p>
        <p>Uang diserahkan tunai oleh Bendahara. Jika kas belum tersedia, pengajuan tetap menunggu; tidak ada pembayaran sebagian.</p>
        @if ($withdrawal->exception_reported_at && ! $withdrawal->exception_resolved_at)<p role="alert">Penyerahan sedang diperiksa Admin. Jangan meminta atau melakukan penyerahan kedua.</p>@endif
        @if ($withdrawal->cancellation_reason || $withdrawal->rejection_reason)<p>Alasan: {{ $withdrawal->cancellation_reason ?? $withdrawal->rejection_reason }}</p>@endif
        @if (! $withdrawal->status->isTerminal() && (! $withdrawal->exception_reported_at || $withdrawal->exception_resolved_at))
            <x-ui.button variant="secondary" wire:click="cancel" wire:loading.attr="disabled">Batalkan pengajuan</x-ui.button>
        @endif
        @if ($withdrawal->receipt_ledger_entry_id)<a class="font-bold text-forest-600" href="{{ route('citizen.withdrawal.receipt', $withdrawal) }}">Lihat kuitansi</a>@endif
    </x-ui.panel>
    <x-ui.panel title="Riwayat status">
        @foreach ($withdrawal->statusHistory->sortBy('occurred_at') as $history)<p>{{ $history->occurred_at->format('d M Y H:i') }} · {{ \App\Support\StatusLabel::for($history->new_status) }} · {{ $history->reason }}</p>@endforeach
    </x-ui.panel>
</section>
