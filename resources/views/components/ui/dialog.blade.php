@props([
    'id' => null,
    'name',
    'title',
    'description' => null,
    'open' => false,
    'state' => 'default',
])

@php
    $safeName = preg_replace('/[^a-z0-9_-]+/i', '-', $name) ?: 'dialog';
    $counterKey = 'ui.component.instance-counter';
    $instance = app()->bound($counterKey) ? app($counterKey) + 1 : 1;
    app()->instance($counterKey, $instance);
    $componentId = $id ?: "dialog-{$safeName}-{$instance}";
    $titleId = "{$componentId}-title";
    $descriptionId = "{$componentId}-description";
    $states = ['default' => 'border-border', 'error' => 'border-terracotta', 'success' => 'border-forest-600'];
    $resolvedState = array_key_exists($state, $states) ? $state : 'default';
@endphp

<div
    x-data="{ open: @js((bool) $open), invoker: null, focusInitial() { this.$nextTick(() => this.$refs.initialFocus.focus()) }, openModal(invoker = document.activeElement) { this.invoker = invoker instanceof HTMLElement && typeof invoker.focus === 'function' && invoker.isConnected ? invoker : null; this.open = true; this.focusInitial() }, closeModal() { const invoker = this.invoker; this.invoker = null; this.open = false; this.$nextTick(() => { if (invoker instanceof HTMLElement && typeof invoker.focus === 'function' && invoker.isConnected) invoker.focus() }) } }"
    x-init="open ? focusInitial() : null"
    x-show="open"
    x-on:open-dialog.window="if ($event.detail?.id === @js($componentId)) openModal($event.detail?.invoker)"
    x-on:keydown.escape.window="closeModal()"
    class="fixed inset-0 z-dialog flex items-end justify-center p-4 md:items-center"
    {{ $attributes }}
>
    <button type="button" class="absolute inset-0 z-overlay bg-overlay" aria-label="Tutup {{ Str::lower($title) }}" x-on:click="closeModal()"></button>
    <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $titleId }}"
        @if ($description) aria-describedby="{{ $descriptionId }}" @endif
        x-trap.inert.noscroll="open"
        @class(['relative z-dialog w-full max-w-lg rounded-lg border bg-surface p-5 shadow-dialog transition duration-240 ease-standard motion-reduce:transform-none', $states[$resolvedState]])
    >
        <div class="flex items-start justify-between gap-4">
            <div><h2 id="{{ $titleId }}" class="text-h3 text-deep-green">{{ $title }}</h2>@if ($description)<p id="{{ $descriptionId }}" class="mt-1 text-body-sm text-text-secondary">{{ $description }}</p>@endif</div>
            <button data-initial-focus x-ref="initialFocus" type="button" aria-label="Tutup {{ Str::lower($title) }}" x-on:click="closeModal()" class="inline-flex min-h-touch min-w-touch items-center justify-center rounded-md text-deep-green hover:bg-success-bg active:bg-warning-bg">
                <svg data-lucide="x" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="mt-4 text-body">{{ $slot }}</div>
        @isset($actions)<div class="mt-5 flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end">{{ $actions }}</div>@endisset
    </section>
</div>
