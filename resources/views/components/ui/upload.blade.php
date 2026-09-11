@props([
    'name',
    'label',
    'hint' => 'JPEG, PNG, WebP, atau PDF. Maksimal 5 MB.',
    'error' => null,
    'accept' => 'image/jpeg,image/png,image/webp,application/pdf',
])

@php
    $uploadId = $attributes->get('id', $name);
    $hintId = "{$uploadId}-hint";
    $errorId = $error ? "{$uploadId}-error" : null;
@endphp

<div class="space-y-2">
    <label for="{{ $uploadId }}" class="block text-label text-text-primary">{{ $label }}</label>
    <div @class(['rounded-lg border bg-surface p-4', 'border-terracotta' => $error, 'border-border' => ! $error])>
        <input
            id="{{ $uploadId }}"
            name="{{ $name }}"
            type="file"
            accept="{{ $accept }}"
            aria-describedby="{{ collect([$hintId, $errorId])->filter()->implode(' ') }}"
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['id'])->class('block min-h-touch w-full cursor-pointer rounded-md text-body-sm text-text-secondary file:mr-4 file:min-h-touch file:cursor-pointer file:rounded-md file:border-0 file:bg-success-bg file:px-4 file:text-label file:text-deep-green hover:file:bg-warning-bg disabled:cursor-not-allowed disabled:bg-disabled-bg') }}
        >
        <p id="{{ $hintId }}" class="mt-2 text-body-sm text-text-secondary">{{ $hint }}</p>
    </div>
    @if ($error)
        <p id="{{ $errorId }}" class="flex items-start gap-2 text-body-sm font-semibold text-terracotta">
            <svg viewBox="0 0 24 24" class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
            {{ $error }}
        </p>
    @endif
</div>
