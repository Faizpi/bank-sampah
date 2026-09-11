@props([
    'id',
    'property',
    'label',
    'hint',
    'max' => 1,
    'multiple' => false,
    'allowPdf' => false,
    'removeMethod',
    'confirmMethod',
    'initialFiles' => [],
    'initialStatus' => null,
    'galleryLabel' => null,
])

@php
    $labelId = "{$id}-label";
    $hintId = "{$id}-hint";
    $statusId = "{$id}-status";
    $accept = $allowPdf
        ? 'image/*,application/pdf'
        : 'image/*';
    $emptyStatus = $initialStatus ?? ($allowPdf ? 'Belum ada file dipilih.' : 'Belum ada foto dipilih.');
    $resolvedGalleryLabel = $galleryLabel ?? ($allowPdf ? 'Pilih file' : 'Pilih dari galeri');
@endphp

<div
    wire:ignore
    role="group"
    aria-labelledby="{{ $labelId }}"
    {{ $attributes->class('space-y-3') }}
    data-photo-picker
    data-photo-picker-max="{{ $max }}"
    data-photo-picker-allow-pdf="{{ $allowPdf ? 'true' : 'false' }}"
    data-photo-picker-empty-status="{{ $emptyStatus }}"
    data-photo-picker-remove-method="{{ $removeMethod }}"
    data-photo-picker-confirm-method="{{ $confirmMethod }}"
    data-photo-picker-initial-files="{{ json_encode($initialFiles, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
>
    <div>
        <p id="{{ $labelId }}" class="text-label font-bold text-deep-green">{{ $label }}</p>
        <p id="{{ $hintId }}" class="mt-1 text-body-sm text-text-secondary">{{ $hint }}</p>
    </div>

    <div class="flex flex-col gap-2 sm:flex-row">
        <label for="{{ $id }}" role="button" tabindex="0" data-photo-picker-trigger="camera" class="inline-flex min-h-touch cursor-pointer items-center justify-center gap-2 rounded-md border border-border bg-surface px-5 text-label font-semibold text-deep-green transition hover:border-forest-600 hover:bg-success-bg focus:outline-none focus:ring-2 focus:ring-focus">
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 4h-5L8 6H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-3l-1.5-2Z"/><circle cx="12" cy="12.5" r="3.25"/></svg>
            Ambil dari kamera
        </label>
        <label for="{{ $id }}" role="button" tabindex="0" data-photo-picker-trigger="gallery" class="inline-flex min-h-touch cursor-pointer items-center justify-center gap-2 rounded-md border border-border bg-surface px-5 text-label font-semibold text-deep-green transition hover:border-forest-600 hover:bg-success-bg focus:outline-none focus:ring-2 focus:ring-focus">
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-4.5-4.5L7 20"/></svg>
            {{ $resolvedGalleryLabel }}
        </label>
    </div>

    <input
        id="{{ $id }}"
        type="file"
        accept="{{ $accept }}"
        @if ($multiple) multiple @endif
        aria-label="{{ $label }}"
        aria-describedby="{{ $hintId }} {{ $statusId }}"
        data-photo-picker-input
        data-photo-picker-property="{{ $property }}"
        class="sr-only"
    >

    <div data-photo-picker-feedback class="flex min-h-6 items-start gap-2 text-body-sm text-text-secondary">
        <svg data-photo-picker-icon="busy" viewBox="0 0 24 24" class="mt-0.5 hidden size-4 shrink-0 animate-spin motion-reduce:animate-none" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.2-8.56"/></svg>
        <svg data-photo-picker-icon="ready" viewBox="0 0 24 24" class="mt-0.5 hidden size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.25 2.25L15.75 9"/></svg>
        <svg data-photo-picker-icon="error" viewBox="0 0 24 24" class="mt-0.5 hidden size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
        <p id="{{ $statusId }}" data-photo-picker-status aria-live="polite">{{ $emptyStatus }}</p>
    </div>

    <div data-photo-picker-progress class="hidden h-1.5 overflow-hidden rounded-full bg-disabled-bg" aria-hidden="true">
        <div data-photo-picker-progress-bar class="h-full w-0 rounded-full bg-forest-600 transition-[width] duration-180 motion-reduce:transition-none"></div>
    </div>

    <div data-photo-picker-preview class="grid gap-2 sm:grid-cols-2"></div>
</div>
