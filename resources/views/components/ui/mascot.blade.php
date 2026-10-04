@props([
    'variant' => '3',
    'animate' => false,
    'bubble' => null,
    'bubblePosition' => 'top',
])

@php
    $validVariants = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];
    $resolvedVariant = in_array((int) $variant, $validVariants, true) ? (int) $variant : 3;
    $src = asset("images/landing/mascot-{$resolvedVariant}.png");
    $shouldAnimate = filter_var($animate, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="relative inline-flex flex-col items-center">
    @if ($bubble && $bubblePosition === 'top')
        <div class="relative mb-3 max-w-[13rem] rounded-xl border border-border bg-surface px-3 py-1.5 text-center text-caption font-semibold text-text-primary shadow-xs" role="note">
            {{ $bubble }}
            {{-- Arrow pointing down --}}
            <span class="absolute left-1/2 top-full -translate-x-1/2 border-x-[6px] border-t-[6px] border-x-transparent border-t-border" aria-hidden="true"></span>
            <span class="absolute left-1/2 top-[calc(100%-1px)] -translate-x-1/2 border-x-[5px] border-t-[5px] border-x-transparent border-t-surface" aria-hidden="true"></span>
        </div>
    @endif

    <img
        src="{{ $src }}"
        alt="Maskot Bank Sampah Sindangheula"
        loading="lazy"
        {{ $attributes->class([
            'object-contain',
            'animate-mascot' => $shouldAnimate,
        ]) }}
    >

    @if ($bubble && $bubblePosition === 'bottom')
        <div class="relative mt-3 max-w-[13rem] rounded-xl border border-border bg-surface px-3 py-1.5 text-center text-caption font-semibold text-text-primary shadow-xs">
            {{-- Arrow pointing up --}}
            <span class="absolute bottom-full left-1/2 -translate-x-1/2 border-x-[6px] border-b-[6px] border-x-transparent border-b-border" aria-hidden="true"></span>
            <span class="absolute bottom-[calc(100%-1px)] left-1/2 -translate-x-1/2 border-x-[5px] border-b-[5px] border-x-transparent border-b-surface" aria-hidden="true"></span>
            {{ $bubble }}
        </div>
    @endif
</div>
