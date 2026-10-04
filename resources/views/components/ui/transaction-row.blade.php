@props(['type', 'reference', 'status' => 'pending', 'statusLabel', 'time', 'method' => null, 'weight' => null, 'value', 'href', 'corrected' => false])

<a href="{{ $href }}" {{ $attributes->class('flex min-h-18 min-w-0 flex-col items-stretch gap-2 border-b border-border py-3 text-text-primary hover:bg-success-bg focus-visible:bg-success-bg sm:flex-row sm:items-center sm:gap-3') }}>
    <div class="min-w-0 flex-1">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <span data-transaction-type class="min-w-0 max-w-full break-words text-label text-deep-green">{{ $type }}</span>
            <span aria-hidden="true" class="text-text-secondary">·</span>
            <span data-transaction-reference class="min-w-0 max-w-full break-words [overflow-wrap:anywhere] text-label text-deep-green">{{ $reference }}</span>
            <x-ui.status-badge :status="$status">{{ $statusLabel }}</x-ui.status-badge>
            @if ($corrected)<span class="rounded-sm bg-warning-bg px-2 py-1 text-caption text-deep-green">Dikoreksi</span>@endif
        </div>
        <p class="mt-1 max-w-full break-words text-body-sm text-text-secondary">{{ $time }}@if ($method) · {{ $method }}@endif</p>
        @if ($weight)<p class="mt-1 min-w-0 max-w-full break-words amount-tabular text-body-sm text-text-secondary">{{ $weight }}</p>@endif
    </div>
    <div class="flex min-w-0 items-center justify-between gap-2 sm:shrink-0 sm:justify-end">
        <span class="min-w-0 max-w-full self-start break-words text-left text-amount amount-tabular text-deep-green sm:text-right">{{ $value }}</span>
        <svg data-lucide="chevron-right" viewBox="0 0 24 24" class="size-5 shrink-0 text-text-secondary" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
    </div>
</a>
