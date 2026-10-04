@props([
    'status' => 'pending',
])

@php
    $statuses = [
        'pending' => ['icon' => 'clock-3', 'classes' => 'bg-warning-bg text-deep-green'],
        'in_progress' => ['icon' => 'loader-circle', 'classes' => 'bg-info-bg text-deep-green'],
        'success' => ['icon' => 'circle-check', 'classes' => 'bg-success-bg text-deep-green'],
        'error' => ['icon' => 'circle-x', 'classes' => 'bg-danger-bg text-terracotta'],
        'cancelled' => ['icon' => 'ban', 'classes' => 'bg-disabled-bg text-text-primary'],
        'closed' => ['icon' => 'ban', 'classes' => 'bg-disabled-bg text-text-primary'],
        'expired' => ['icon' => 'timer-off', 'classes' => 'bg-disabled-bg text-text-primary'],
    ];
    $resolvedStatus = array_key_exists($status, $statuses) ? $status : 'pending';
    $config = $statuses[$resolvedStatus];
@endphp

<span
    data-status="{{ $resolvedStatus }}"
    {{ $attributes->class([
        'inline-flex min-h-7 items-center gap-1.5 rounded-sm px-2.5 py-1 text-caption',
        $config['classes'],
    ]) }}
>
    <svg data-lucide="{{ $config['icon'] }}" viewBox="0 0 24 24" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        @switch($config['icon'])
            @case('circle-check') <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/> @break
            @case('circle-x') <circle cx="12" cy="12" r="10"/><path d="m15 9-6 6m0-6 6 6"/> @break
            @case('ban') <circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/> @break
            @case('timer-off') <path d="M10 2h4M12 14v-4M4 13a8 8 0 0 0 11 7.4M20 15a8 8 0 0 0-11-10"/><path d="m2 2 20 20"/> @break
            @case('loader-circle') <path d="M21 12a9 9 0 1 1-6.2-8.6"/> @break
            @default <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
        @endswitch
    </svg>
    <span>{{ $slot }}</span>
</span>
