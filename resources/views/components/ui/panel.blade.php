@props([
    'id' => null,
    'title' => null,
    'description' => null,
    'state' => 'default',
    'loading' => false,
])

@php
    $states = [
        'default' => 'border-border',
        'error' => 'border-terracotta',
        'success' => 'border-forest-600/25',
        'disabled' => 'border-border bg-disabled-bg text-text-secondary',
    ];
    $resolvedState = array_key_exists($state, $states) ? $state : 'default';
    $counterKey = 'ui.component.instance-counter';
    $instance = app()->bound($counterKey) ? app($counterKey) + 1 : 1;
    app()->instance($counterKey, $instance);
    $componentId = $id ?: "panel-{$instance}";
    $titleId = "{$componentId}-title";
    $descriptionId = "{$componentId}-description";
@endphp

<section
    @if ($title) aria-labelledby="{{ $titleId }}" @endif
    @if ($description) aria-describedby="{{ $descriptionId }}" @endif
    @if ($loading) aria-busy="true" @endif
    {{ $attributes->class(['rounded-lg border bg-surface p-4 shadow-xs md:p-6', $states[$resolvedState]]) }}
>
    @if ($title || $description || isset($actions))
        <div class="flex flex-col items-stretch gap-4 border-b border-border pb-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                @if ($title)<h2 id="{{ $titleId }}" class="break-words text-title text-deep-green">{{ $title }}</h2>@endif
                @if ($description)<p id="{{ $descriptionId }}" class="mt-1 break-words text-body-sm text-text-secondary">{{ $description }}</p>@endif
            </div>
            @isset($actions)<div class="flex min-w-0 flex-wrap items-center gap-2 sm:shrink-0">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div @class(['text-body', 'pt-4' => $title || $description || isset($actions)])>{{ $slot }}</div>
</section>
