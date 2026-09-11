@props(['title', 'reference' => null, 'value' => null, 'time' => null, 'status' => 'success', 'viewHref' => null, 'printHref' => null, 'description' => null])

@php
    $isPending = in_array($status, ['pending', 'menunggu_persetujuan'], true);
    $badgeStatus = $isPending ? 'pending' : 'success';
    $badgeLabel = $isPending ? 'Menunggu persetujuan' : 'Berhasil';
    $panelState = $isPending ? 'default' : 'success';
    $iconClasses = $isPending ? 'text-harvest-gold' : 'text-forest-600';
@endphp

<x-ui.panel data-success-receipt :state="$panelState">
    <div class="flex gap-3">
        <x-public.icon :name="$isPending ? 'clock-3' : 'circle-check'" size="size-8" class="shrink-0 {{ $iconClasses }}" />
        <div class="min-w-0 flex-1">
            <h2 class="text-title text-deep-green">{{ $title }}</h2>
            <p class="mt-2 text-label text-text-secondary">Status</p>
            <div class="mt-1"><x-ui.status-badge :status="$badgeStatus">{{ $badgeLabel }}</x-ui.status-badge></div>
            @if ($reference || $value || $time)
                <dl class="mt-4 grid gap-3 border-t border-border pt-3 text-body-sm sm:grid-cols-3">
                    @if ($reference)
                        <div><dt class="text-caption text-text-secondary">Referensi</dt><dd class="mt-0.5 font-semibold text-deep-green">{{ $reference }}</dd></div>
                    @endif
                    @if ($value)
                        <div><dt class="text-caption text-text-secondary">Nilai</dt><dd class="mt-0.5 amount-tabular font-semibold text-deep-green">{{ $value }}</dd></div>
                    @endif
                    @if ($time)
                        <div><dt class="text-caption text-text-secondary">Waktu</dt><dd class="mt-0.5 font-semibold text-deep-green">{{ $time }}</dd></div>
                    @endif
                </dl>
            @endif
            @if ($description)
                <p class="mt-3 text-body text-text-secondary">{{ $description }}</p>
            @endif
            @if ($viewHref || $printHref || isset($actions))
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    @if ($viewHref)<a href="{{ $viewHref }}" class="inline-flex min-h-touch items-center justify-center rounded-md bg-forest-600 px-5 text-label text-white transition duration-180 ease-standard hover:bg-forest-700 active:translate-y-px">Lihat bukti</a>@endif
                    @if ($printHref)<a href="{{ $printHref }}" class="inline-flex min-h-touch items-center justify-center rounded-md border border-border bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:border-forest-600 hover:bg-success-bg active:translate-y-px">Cetak bukti</a>@endif
                    {{ $actions ?? '' }}
                </div>
            @endif
        </div>
    </div>
</x-ui.panel>
