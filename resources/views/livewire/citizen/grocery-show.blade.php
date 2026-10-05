<x-slot:title>{{ $redemption->request_number }}</x-slot:title>
<section class="space-y-6">
    <h1 class="text-h1 font-bold">Penukaran sembako</h1>
    @if (session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if ($errors->any())<div role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <x-ui.panel title="Status pengajuan">
        <p class="font-bold">{{ $redemption->request_number }} · {{ \App\Support\StatusLabel::for($redemption->status) }}</p>
        <p>Rp {{ number_format($redemption->value_snapshot, 0, ',', '.') }}</p>
        <p>Saldo tertahan: {{ \App\Support\StatusLabel::for($redemption->balanceHold?->status) }}. Penahanan belum merupakan mutasi keluar.</p>
        <p>Petugas mengambil tugas bila paket fisik tersedia. Jika tidak tersedia, saldo tertahan dilepas.</p>
        @if ($redemption->exception_reported_at && ! $redemption->exception_resolved_at)<p role="alert">Penyerahan sedang diperiksa Admin. Jangan meminta atau melakukan penyerahan kedua.</p>@endif
        @if ($redemption->cancellation_reason || $redemption->rejection_reason)<p>Alasan: {{ $redemption->cancellation_reason ?? $redemption->rejection_reason }}</p>@endif
        @if (! $redemption->status->isTerminal() && (! $redemption->exception_reported_at || $redemption->exception_resolved_at))
            <x-ui.button variant="secondary" wire:click="cancel" wire:loading.attr="disabled">Batalkan pengajuan</x-ui.button>
        @endif
        @if ($redemption->receipt_ledger_entry_id)<a class="font-bold text-forest-600" href="{{ route('citizen.grocery.receipt', $redemption) }}">Lihat kuitansi</a>@endif
    </x-ui.panel>
    <x-ui.panel title="Riwayat status">
        @foreach ($redemption->statusHistory->sortBy('occurred_at') as $history)<p>{{ $history->occurred_at->format('d M Y H:i') }} · {{ \App\Support\StatusLabel::for($history->new_status) }} · {{ $history->reason }}</p>@endforeach
    </x-ui.panel>
</section>
