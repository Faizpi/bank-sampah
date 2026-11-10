<x-slot:title>Pantauan pencairan digital (simulasi)</x-slot:title>
<section class="grid gap-6" aria-labelledby="payout-monitor-title">
    <x-ui.page-heading id="payout-monitor-title" title="Pencairan digital (simulasi)" description="Pantauan baca saja sesuai wilayah tugas." mascot="6" />
    <p class="rounded-xl border border-border bg-warning-bg p-4">Pengiriman diproses otomatis oleh sistem. Laporan ini bukan pembayaran tunai dan tidak menunjukkan perpindahan uang riil.</p>
    @if ($errors->any())<div role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="grid gap-4 sm:grid-cols-3"><x-ui.input name="start" label="Dari tanggal" type="date" wire:model.live="start" /><x-ui.input name="end" label="Sampai tanggal" type="date" wire:model.live="end" /><x-ui.input name="search" label="Cari pengajuan atau Warga" placeholder="Contoh: SIM atau Siti" wire:model.live.debounce.300ms="search" /></div>
    <a href="{{ $exportUrl }}" class="app-interactive inline-flex min-h-touch items-center justify-center rounded-lg border border-forest-600 px-4 font-bold text-forest-700">Unduh CSV simulasi (maksimal 1.000 baris)</a>
    <div class="divide-y divide-border">
        @forelse ($payouts as $row)
            <article class="grid gap-2 py-4" wire:key="monitor-{{ $row->id }}"><p class="break-all font-bold">{{ $row->request_number }}</p><p class="break-words">{{ $row->customer?->name }} · {{ $channels[$row->channel]['name'] }} · {{ $row->maskedNumber() }}</p><p>{{ $row->label() }} (simulasi)</p><p class="font-semibold amount-tabular">Rp {{ number_format($row->amount, 0, ',', '.') }}</p>@if ($row->failure_code)<p class="break-all text-body-sm">Kode pemeriksaan: {{ $row->failure_code }}</p>@endif</article>
        @empty<p class="py-6">Belum ada pengajuan digital pada periode dan wilayah ini.</p>@endforelse
    </div>
    {{ $payouts->links('components.ui.pagination') }}
</section>
