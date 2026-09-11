@props([
    'type' => 'info',
    'title',
    'dismissible' => true,
])

@php
    $types = [
        'info' => ['icon' => 'info', 'classes' => 'border-sky-blue bg-info-bg text-deep-green', 'urgent' => false],
        'success' => ['icon' => 'circle-check', 'classes' => 'border-forest-600 bg-success-bg text-deep-green', 'urgent' => false],
        'warning' => ['icon' => 'triangle-alert', 'classes' => 'border-harvest-gold bg-warning-bg text-deep-green', 'urgent' => false],
        'error' => ['icon' => 'circle-alert', 'classes' => 'border-terracotta bg-danger-bg text-text-primary', 'urgent' => true],
    ];
    $resolvedType = array_key_exists($type, $types) ? $type : 'info';
    $config = $types[$resolvedType];
@endphp

<div
    @if ($dismissible) x-data="{ visible: true }" x-show="visible" @endif
    role="{{ $config['urgent'] ? 'alert' : 'status' }}"
    aria-live="{{ $config['urgent'] ? 'assertive' : 'polite' }}"
    aria-atomic="true"
    {{ $attributes->class(['z-toast flex w-full max-w-md items-start gap-3 rounded-lg border p-4 shadow-sm', $config['classes']]) }}
>
    <svg data-lucide="{{ $config['icon'] }}" viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        @switch($config['icon'])
            @case('circle-check') <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/> @break
            @case('triangle-alert') <path d="m21.7 18-8-14a2 2 0 0 0-3.4 0l-8 14a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7-3zM12 9v4M12 17h.01"/> @break
            @case('circle-alert') <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/> @break
            @default <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
        @endswitch
    </svg>
    <div class="min-w-0 flex-1"><p class="text-label">{{ $title }}</p><div class="mt-1 text-body-sm">{{ $slot }}</div></div>
    @if ($dismissible)
        <button type="button" aria-label="Tutup notifikasi" x-on:click="visible = false" class="inline-flex min-h-touch min-w-touch shrink-0 items-center justify-center rounded-md hover:bg-surface active:bg-disabled-bg">
            <svg data-lucide="x" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    @endif
</div>
