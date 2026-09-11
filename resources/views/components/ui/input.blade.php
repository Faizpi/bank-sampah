@props([
    'name',
    'label',
    'type' => 'text',
    'hint' => null,
    'error' => null,
])

@php
    $inputId = $attributes->get('id', $name);
    $hintId = $hint ? "{$inputId}-hint" : null;
    $errorId = $error ? "{$inputId}-error" : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
@endphp

<div class="space-y-2">
    <label for="{{ $inputId }}" class="block text-label text-text-primary">{{ $label }}</label>
    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except(['id'])->class([
            'min-h-touch w-full rounded-md border bg-surface px-4 text-body text-text-primary shadow-xs transition duration-180 placeholder:text-text-secondary hover:border-forest-600 focus:border-focus disabled:cursor-not-allowed disabled:border-border disabled:bg-disabled-bg disabled:text-text-secondary',
            'border-terracotta' => $error,
            'border-border' => ! $error,
        ]) }}
    >
    @if ($hint)
        <p id="{{ $hintId }}" class="text-body-sm text-text-secondary">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $errorId }}" class="flex items-start gap-2 text-body-sm font-semibold text-terracotta">
            <svg viewBox="0 0 24 24" class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
            {{ $error }}
        </p>
    @endif
</div>
