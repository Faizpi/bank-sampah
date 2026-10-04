@props(['title', 'reference' => null, 'value' => null, 'time' => null, 'status' => 'success', 'viewHref' => null, 'printHref' => null, 'description' => null])

<x-ui.panel data-success-receipt state="success">
    <div class="flex gap-3">
        <svg data-lucide="circle-check" viewBox="0 0 24 24" class="size-8 shrink-0 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12 2.5 2.5L16 9"/></svg>
        <div class="min-w-0 flex-1">
            <h2 class="text-title text-deep-green">{{ $title }}</h2>
            <p class="mt-2 text-label text-text-secondary">Status</p>
            <div class="mt-1"><x-ui.status-badge status="success">Berhasil</x-ui.status-badge></div>
            @if ($description)
                <p class="mt-3 text-body text-text-secondary">{{ $description }}</p>
            @endif
            @if ($viewHref || $printHref)
                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($viewHref)<a href="{{ $viewHref }}" class="inline-flex min-h-touch items-center justify-center rounded-md bg-forest-600 px-5 text-label text-white transition duration-180 ease-standard hover:bg-forest-700 active:translate-y-px">Lihat bukti</a>@endif
                    @if ($printHref)<a href="{{ $printHref }}" class="inline-flex min-h-touch items-center justify-center rounded-md border border-border bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:border-forest-600 hover:bg-success-bg active:translate-y-px">Cetak bukti</a>@endif
                </div>
            @endif
        </div>
    </div>
</x-ui.panel>
