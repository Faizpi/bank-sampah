<x-slot:title>Riwayat digital (simulasi)</x-slot:title>
<section class="grid gap-6" aria-labelledby="payout-history-title">
    <x-ui.page-heading id="payout-history-title" title="Riwayat digital (simulasi)" description="Pengajuan dan hasil pencairan melalui Xendit Test Mode." mascot="6" />
    <nav aria-label="Metode pencairan" class="flex flex-wrap gap-4"><a class="inline-flex min-h-touch items-center font-semibold text-forest-600" href="{{ route('citizen.withdrawal-history') }}">Tunai</a><span aria-current="page" class="inline-flex min-h-touch items-center font-bold">Digital (simulasi)</span></nav>
    @if (\App\Domain\Payouts\Services\PayoutAccess::configured())<a href="{{ route('citizen.payout.create') }}" class="app-interactive inline-flex min-h-touch items-center justify-center rounded-lg bg-forest-600 px-5 py-3 font-bold text-surface">Ajukan pencairan simulasi</a>@endif
    <div class="divide-y divide-border">
        @forelse ($payouts as $row)
            <a href="{{ route('citizen.payout.show', $row) }}" class="app-row-action grid gap-2 py-4" wire:key="payout-{{ $row->id }}"><span class="break-all font-bold">{{ $row->request_number }}</span><span>{{ $channels[$row->channel]['name'] }} (simulasi) · {{ $row->label() }}</span><span class="font-semibold amount-tabular">Rp {{ number_format($row->amount, 0, ',', '.') }}</span><span class="text-body-sm text-text-secondary">{{ $row->created_at?->translatedFormat('d M Y H:i') }}</span></a>
        @empty<p class="py-6">Belum ada pengajuan pencairan digital (simulasi).</p>@endforelse
    </div>
    {{ $payouts->links('components.ui.pagination') }}
</section>
