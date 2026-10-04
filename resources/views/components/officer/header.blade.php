@props([
    'title',
])

<header {{ $attributes->class('sticky top-0 z-sticky border-b border-border bg-surface') }}>
    <div class="mx-auto flex min-h-16 max-w-officer items-center gap-3 px-4 sm:px-5">
        <div class="min-w-0 flex-1">
            <h1 class="break-words text-title text-deep-green">{{ $title }}</h1>
            @if (isset($date) || isset($location))
                <div class="flex flex-wrap gap-x-3 text-caption text-text-secondary">
                    @isset($date)<span>{{ $date }}</span>@endisset
                    @isset($location)<span>{{ $location }}</span>@endisset
                </div>
            @endif
        </div>
        @isset($connectivity)<div class="shrink-0">{{ $connectivity }}</div>@endisset
        @isset($profile)<div class="flex min-h-touch shrink-0 items-center">{{ $profile }}</div>@endisset
    </div>
</header>
