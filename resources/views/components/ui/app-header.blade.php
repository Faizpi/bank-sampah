@props([
    'title',
    'context' => null,
    'backHref' => null,
])

<header {{ $attributes->class('sticky top-0 z-sticky border-b border-border bg-surface') }}>
    <div class="mx-auto flex min-h-16 max-w-citizen items-center gap-3 px-4">
        @if ($backHref)
            <a href="{{ $backHref }}" aria-label="Kembali" class="inline-flex min-h-touch min-w-touch items-center justify-center rounded-md text-deep-green transition duration-180 hover:bg-success-bg active:translate-y-px">
                <svg data-lucide="arrow-left" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7M19 12H5"/></svg>
            </a>
        @endif
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-title text-deep-green">{{ $title }}</h1>
            @if ($context)<p class="truncate text-caption text-text-secondary">{{ $context }}</p>@endif
        </div>
        @isset($actions)<div class="flex min-h-touch items-center gap-2">{{ $actions }}</div>@endisset
    </div>
</header>
