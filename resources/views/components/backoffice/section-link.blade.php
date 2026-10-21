@props(['href', 'icon', 'active' => false])

<li class="min-w-0">
    <a href="{{ $href }}" {{ $attributes->class(['backoffice-section-link']) }} @if ($active) aria-current="page" @endif>
        <x-filament::icon :icon="$icon" class="size-6 shrink-0" aria-hidden="true" />
        <span class="min-w-0 [overflow-wrap:anywhere]">{{ $slot }}</span>
        @if ($active)
            <x-filament::icon icon="heroicon-m-check" class="ml-auto size-5 shrink-0" aria-hidden="true" />
        @endif
    </a>
</li>
