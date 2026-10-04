@props(['lines' => 3, 'label' => 'Memuat', 'delayed' => false])

<div role="status" aria-live="polite" {{ $attributes->class('space-y-3') }}>
    <span class="sr-only">{{ $label }}</span>
    <div aria-hidden="true" class="space-y-3">
        @for ($line = 0; $line < max(1, min((int) $lines, 8)); $line++)
            <div data-skeleton-line class="h-4 rounded-sm bg-disabled-bg animate-pulse motion-reduce:animate-none {{ $line === (int) $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>
        @endfor
    </div>
    @if ($delayed)<p class="text-body-sm text-text-secondary">Pemuatan membutuhkan waktu lebih lama.</p>@endif
</div>
