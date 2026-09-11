@props([
    'type' => 'button',
    'variant' => 'primary',
    'loading' => false,
    'loadingText' => 'Memproses',
])

@php
    $variants = [
        'primary' => 'bg-forest-600 text-surface hover:bg-forest-700 active:translate-y-px',
        'secondary' => 'border border-border bg-surface text-deep-green hover:border-forest-600 hover:bg-success-bg active:translate-y-px',
        'quiet' => 'bg-transparent text-deep-green hover:bg-success-bg active:translate-y-px',
        'danger' => 'bg-terracotta text-surface hover:brightness-95 active:translate-y-px',
        'link' => 'bg-transparent text-forest-600 underline decoration-transparent underline-offset-4 hover:decoration-current',
    ];
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->class([
        'inline-flex min-h-touch items-center justify-center gap-2 rounded-md px-5 text-label transition duration-180 ease-standard disabled:cursor-not-allowed disabled:bg-disabled-bg disabled:text-text-secondary disabled:shadow-none disabled:translate-y-0',
        $variants[$variant] ?? $variants['primary'],
    ])->merge(['disabled' => $loading ? true : null]) }}
    @if ($loading) aria-busy="true" @endif
>
    @if ($loading)
        <svg viewBox="0 0 24 24" class="size-5 animate-spin motion-reduce:animate-none" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M21 12a9 9 0 1 1-6.22-8.56"/>
        </svg>
        <span>{{ $loadingText }}</span>
    @else
        {{ $slot }}
    @endif
</button>
