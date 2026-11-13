<div class="grid gap-4">
    <p class="rounded-lg border border-border bg-warning-bg p-4">Simulasi Xendit (tanpa pengiriman uang riil).</p>
    <div class="flex flex-wrap items-center gap-3"><img src="{{ asset($channel['logo']) }}" alt="Logo {{ $channel['name'] }}" class="h-8 w-24 object-contain"><strong>{{ $channel['name'] }} (simulasi)</strong></div>
    <dl class="grid gap-4 sm:grid-cols-2">
        <div><dt>Nomor pengajuan</dt><dd class="break-all font-semibold">{{ $payout->request_number }}</dd></div>
        <div><dt>Warga</dt><dd class="font-semibold">{{ $payout->customer?->name }}</dd></div>
        <div><dt>Nominal</dt><dd class="amount-tabular font-bold">Rp {{ number_format($payout->amount, 0, ',', '.') }}</dd></div>
        <div><dt>Status</dt><dd class="font-semibold">{{ $payout->label() }}</dd></div>
        <div><dt>Nomor tujuan</dt><dd>{{ $payout->maskedNumber() }}</dd></div>
        <div><dt>Nama pemilik tujuan uji</dt><dd>{{ $payout->recipient['name'] }}</dd></div>
        <div><dt>Pelaksana</dt><dd>Sistem otomatis</dd></div>
        <div><dt>Diajukan</dt><dd>{{ $payout->created_at?->translatedFormat('d M Y H:i') }}</dd></div>
        @if ($payout->settled_at)<div><dt>Diselesaikan</dt><dd>{{ $payout->settled_at->translatedFormat('d M Y H:i') }}</dd></div>@endif
        @if ($payout->provider_id)<div><dt>ID Xendit</dt><dd class="break-all">{{ $payout->provider_id }}</dd></div>@endif
    </dl>
    @if ($payout->failureNotice())<p class="rounded-lg border border-border p-4">{{ $payout->failureNotice() }}</p>@endif
</div>
