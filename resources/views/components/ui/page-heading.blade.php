@props(['id', 'title', 'description' => null, 'mascot' => '3'])

<div data-page-heading {{ $attributes->class('flex items-center justify-between gap-4 rounded-lg border border-border bg-surface p-5 shadow-xs sm:gap-6 sm:p-6') }}>
    <div class="min-w-0">
        <h1 id="{{ $id }}" class="break-words text-pretty text-h1 font-bold text-deep-green">{{ $title }}</h1>
        @if ($description)<p class="mt-2 text-body text-text-secondary">{{ $description }}</p>@endif
    </div>
    <div class="shrink-0" aria-hidden="true">
        <x-ui.mascot :variant="$mascot" class="size-20 sm:size-24" />
    </div>
</div>
