<x-slot:title>Status pencairan digital (simulasi)</x-slot:title>
<section class="grid gap-6" aria-labelledby="payout-status-title" data-print-area @if (! $payout->isTerminal()) wire:poll.10s @endif>
    <x-ui.page-heading id="payout-status-title" title="Pencairan digital (simulasi)" mascot="5" />
    <p class="rounded-xl border border-border bg-warning-bg p-4 font-semibold">Simulasi Xendit (tanpa pengiriman uang riil). Bukti ini bukan struk resmi bank atau dompet digital.</p>
    @if (session('payout_success'))<p role="status">{{ session('payout_success') }}</p>@endif
    @if ($errors->any())<div role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <x-ui.panel title="{{ $payout->label() }}">
        <div class="flex flex-wrap items-center gap-4"><img src="{{ asset($channel['logo']) }}" alt="Logo {{ $channel['name'] }}" class="h-8 w-24 object-contain object-left"><p class="font-bold">{{ $channel['name'] }} (simulasi)</p></div>
        <p class="mt-4 break-all font-semibold">{{ $payout->request_number }}</p>
        <p class="mt-2 text-h2 font-bold amount-tabular">Rp {{ number_format($payout->amount, 0, ',', '.') }}</p>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><dt>Nama pemilik tujuan uji</dt><dd class="break-words font-semibold">{{ $payout->recipient['name'] }}</dd></div>
            <div><dt>Nomor tujuan</dt><dd>{{ $payout->maskedNumber() }}</dd></div>
            <div><dt>Pelaksana</dt><dd>Otomatis oleh sistem</dd></div>
            <div><dt>Diajukan</dt><dd>{{ $payout->created_at?->translatedFormat('d M Y H:i') }}</dd></div>
            @if ($payout->settled_at)<div><dt>Simulasi diselesaikan</dt><dd>{{ $payout->settled_at->translatedFormat('d M Y H:i') }}</dd></div>@endif
            @if ($payout->reversed_at)<div><dt>Saldo dikembalikan</dt><dd>{{ $payout->reversed_at->translatedFormat('d M Y H:i') }}</dd></div>@endif
            @if ($payout->provider_id)<div><dt>Referensi Xendit</dt><dd class="break-all">{{ $payout->provider_id }}</dd></div>@endif
        </dl>
        <p class="mt-4" role="status">{{ $payout->balanceHold?->isActive() ? 'Saldo masih disisihkan sampai hasil pencairan dipastikan.' : match ($payout->status) { 'succeeded' => 'Pencairan simulasi sudah tercatat pada saldo.', 'failed', 'cancelled' => 'Saldo yang disisihkan sudah tersedia kembali.', 'reversed' => 'Saldo pencairan ini sudah dikembalikan.', default => 'Tidak ada saldo yang disisihkan untuk pengajuan ini.' } }}</p>
        @if ($payout->status === 'queued' && $payout->lease === null)<x-ui.button variant="secondary" wire:click="cancel" wire:loading.attr="disabled" class="mt-4">Batalkan sebelum diproses</x-ui.button>@endif
    </x-ui.panel>
    <div class="flex flex-wrap gap-3" data-print-hide><a class="app-row-action inline-flex min-h-touch items-center gap-2 rounded-md px-3 font-semibold text-forest-600" href="{{ route('citizen.withdrawal-history', ['metode' => 'digital']) }}"><x-public.icon name="history" size="size-5" />Riwayat pencairan</a>@if ($payout->ledger_entry_id)<x-ui.button type="button" variant="secondary" data-print-button>Cetak bukti simulasi</x-ui.button>@endif</div>
</section>
