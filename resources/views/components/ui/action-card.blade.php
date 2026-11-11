@props(['href' => null, 'icon', 'title', 'description' => null, 'compact' => false])

@php
    $cardAttributes = $attributes->class(['app-interactive group flex min-h-touch items-center rounded-lg border border-border bg-surface p-4 shadow-xs sm:p-5', 'min-h-32 flex-col justify-center gap-3 text-center' => $compact, 'gap-4 text-left' => ! $compact, 'w-full cursor-pointer' => $href === null]);
@endphp
@if ($href === null)
<button type="button" data-action-card {{ $cardAttributes }}>
@else
<a href="{{ $href }}" data-action-card {{ $cardAttributes }}>
@endif
    <span class="flex size-12 shrink-0 items-center justify-center rounded-md bg-success-bg text-forest-700 transition-colors duration-180 group-hover:bg-forest-600 group-hover:text-white group-focus-visible:bg-forest-600 group-focus-visible:text-white">
        <x-public.icon :name="$icon" size="size-6" />
    </span>
    <span @class(['min-w-0', 'flex-1' => ! $compact])>
        <span class="block text-body font-bold text-deep-green">{{ $title }}</span>
        @if ($description)<span class="mt-1 block text-body text-text-secondary">{{ $description }}</span>@endif
    </span>
    @unless ($compact)<x-public.icon name="chevron-right" size="size-5" class="text-forest-600" />@endunless
@if ($href === null)</button>@else</a>@endif
