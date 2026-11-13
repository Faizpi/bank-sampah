<x-slot:title>Penukaran sembako</x-slot:title>
<section class="grid gap-6" aria-labelledby="grocery-status-title">
    <x-ui.page-heading id="grocery-status-title" title="Status penukaran sembako" mascot="3" />
    @if (session('success'))<p role="status" class="rounded-xl bg-success-bg p-4">{{ session('success') }}</p>@endif
    @if ($errors->any())<div role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @php($locked = $redemption->exception_reported_at && ! $redemption->exception_resolved_at)
    <x-ui.panel title="Paket Anda">
        <p class="font-bold">{{ $redemption->request_number }}</p>
        <p class="text-title font-semibold">{{ data_get($redemption->package_snapshot, 'name') }}</p>
        <p>{{ data_get($redemption->package_snapshot, 'contents') }}</p>
        <p class="text-h2 amount-tabular">Rp {{ number_format($redemption->value_snapshot, 0, ',', '.') }}</p>
        <p class="mt-2 font-semibold">{{ $locked && $redemption->exception_kind === 'cancellation_request' ? 'Permintaan batal sedang dikonfirmasi' : \App\Support\StatusLabel::for($redemption->status) }}</p>
        @if ($locked)
            <p role="alert" class="mt-3">{{ $redemption->exception_kind === 'cancellation_request' ? 'Petugas memastikan paket belum diserahkan sebelum pembatalan dan pengembalian saldo.' : 'Admin sedang memastikan penyerahan paket. Jangan meminta penyerahan kedua.' }}</p>
        @elseif ($redemption->status->value === 'menunggu_petugas')
            <p class="mt-3">Menunggu petugas menyiapkan paket.</p>
        @elseif ($redemption->status->value === 'siap_diambil')
            <p class="mt-3">Paket siap diambil. Temui {{ $redemption->claimant?->name ?? 'Petugas' }} dan pastikan nama serta isi paket sesuai.</p>
        @elseif ($redemption->status->value === 'diproses_petugas')
            <p class="mt-3">Paket sedang disiapkan oleh {{ $redemption->claimant?->name ?? 'Petugas' }}.</p>
        @endif
        <p class="mt-3">{{ $redemption->balanceHold?->status === 'aktif' ? 'Saldo disisihkan sampai paket diserahkan.' : ($redemption->receipt_ledger_entry_id ? 'Saldo sudah dipotong untuk penukaran ini.' : 'Tidak ada saldo yang disisihkan untuk pengajuan ini.') }}</p>
        @if ($redemption->cancellation_reason || $redemption->rejection_reason)<p>Alasan: {{ $redemption->cancellation_reason ?? $redemption->rejection_reason }}</p>@endif
        @if (! $redemption->status->isTerminal() && ! $locked)
            <x-ui.button variant="secondary" wire:click="cancel" wire:loading.attr="disabled">{{ $redemption->claimed_by_id === null ? 'Batalkan pengajuan' : 'Minta pembatalan' }}</x-ui.button>
        @endif
        @if ($redemption->receipt_ledger_entry_id)<a class="inline-flex min-h-touch items-center font-bold text-forest-600" href="{{ route('citizen.grocery.receipt', $redemption) }}">Lihat kuitansi</a>@endif
    </x-ui.panel>
    <details class="rounded-xl border border-border bg-surface p-4"><summary class="min-h-touch cursor-pointer font-semibold">Lihat riwayat status</summary>
        @foreach ($redemption->statusHistory->sortBy('occurred_at') as $history)<p class="border-t border-border py-2">{{ $history->occurred_at->translatedFormat('d M Y H:i') }} · {{ \App\Support\StatusLabel::for($history->new_status) }} · {{ $history->reason }}</p>@endforeach
    </details>
</section>
