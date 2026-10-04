@props([
    'name',
    'label',
    'hint' => null,
    'error' => null,
    'options' => [],
])

@php
    $selectId = $attributes->get('id', $name);
    $hintId = $hint ? "{$selectId}-hint" : null;
    $errorId = $error ? "{$selectId}-error" : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
@endphp

<div class="space-y-2">
    <label for="{{ $selectId }}" class="block text-label text-text-primary">{{ $label }}</label>
    <select
        id="{{ $selectId }}"
        name="{{ $name }}"
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except(['id'])->class([
            'min-h-touch w-full rounded-md border bg-surface px-4 text-body text-text-primary shadow-xs transition duration-180 hover:border-forest-600 focus:border-focus disabled:cursor-not-allowed disabled:border-border disabled:bg-disabled-bg disabled:text-text-secondary',
            'border-terracotta' => $error,
            'border-border' => ! $error,
        ]) }}
    >
        {{ $slot }}
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
        @endforeach
    </select>
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
