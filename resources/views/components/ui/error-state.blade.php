@props(['id' => null, 'title', 'message', 'actionLabel' => null, 'actionHref' => null])

@php
    $counterKey = 'ui.component.instance-counter';
    $instance = app()->bound($counterKey) ? app($counterKey) + 1 : 1;
    app()->instance($counterKey, $instance);
    $componentId = $id ?: "error-state-{$instance}";
@endphp

<section
    id="{{ $componentId }}"
    role="alert"
    tabindex="-1"
    data-error-focus
    x-data
    x-ref="errorSummary"
    x-on:focus-error-summary.window="if ($event.detail?.id === {{ Illuminate\Support\Js::from($componentId) }}) $nextTick(() => $refs.errorSummary.focus())"
    {{ $attributes->class('rounded-lg border border-terracotta bg-danger-bg p-4') }}
>
    <div class="flex gap-3">
        <svg data-lucide="circle-alert" viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0 text-terracotta" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
        <div class="min-w-0">
            <h2 class="text-title text-deep-green">{{ $title }}</h2>
            <p class="mt-1 break-words text-body text-text-primary">{{ $message }}</p>
            @if ($actionLabel)
                @if ($actionHref)<a href="{{ $actionHref }}" class="mt-4 inline-flex min-h-touch items-center justify-center rounded-md border border-border bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:border-forest-600 hover:bg-success-bg active:translate-y-px">{{ $actionLabel }}</a>
                @else<x-ui.button class="mt-4" variant="secondary">{{ $actionLabel }}</x-ui.button>@endif
            @endif
        </div>
    </div>
</section>
