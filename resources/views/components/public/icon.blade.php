@props([
    'name' => 'leaf',
    'label' => null,
    'size' => 'size-5',
    'strokeWidth' => '1.8',
])

@php
    $icons = [
        'arrow-left'    => 'arrow-left',
        'arrow-right'   => 'arrow-right',
        'banknote'      => 'banknote',
        'bar-chart-3'   => 'bar-chart-3',
        'bell'          => 'bell',
        'clipboard-check' => 'clipboard-check',
        'book-open'     => 'book-open',
        'calendar-days' => 'calendar-days',
        'chevron-right' => 'chevron-right',
        'circle-alert'  => 'circle-alert',
        'circle-check'  => 'circle-check',
        'clock-3'       => 'clock-3',
        'eye'           => 'eye',
        'eye-off'       => 'eye-off',
        'file-check'    => 'file-check',
        'grid-2x2'      => 'grid-2x2',
        'home'          => 'home',
        'leaf'          => 'leaf',
        'layout-dashboard' => 'layout-dashboard',
        'loader-circle' => 'loader-circle',
        'log-in'        => 'log-in',
        'log-out'       => 'log-out',
        'map-pin'       => 'map-pin',
        'magic-wand'    => 'magic-wand',
        'megaphone'     => 'megaphone',
        'menu'          => 'menu',
        'package-open'  => 'package-open',
        'package-check' => 'package-check',
        'recycle'       => 'recycle',
        'scale'         => 'scale',
        'target'        => 'target',
        'truck'         => 'truck',
        'x'             => 'x',
    ];
    $resolvedName = $icons[$name] ?? 'circle-alert';
@endphp

<svg
    viewBox="0 0 24 24"
    {{ $attributes->class([$size, 'shrink-0']) }}
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $strokeWidth }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif
>
    @switch($resolvedName)
        @case('arrow-left')
            <path d="M19 12H5"/><path d="m11 6-6 6 6 6"/>
            @break
        @case('arrow-right')
            <path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>
            @break
        @case('banknote')
            <rect width="20" height="14" x="2" y="5" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>
            @break
        @case('bar-chart-3')
            <path d="M3 3v18h18"/><path d="M7 16v-4M12 16V8M17 16V5"/>
            @break
        @case('bell')
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
            @break
        @case('clipboard-check')
            <rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0M9 13l2 2 4-4"/>
            @break
        @case('book-open')
            <path d="M2 3h6a4 4 0 0 1 4 4v14a4 4 0 0 0-4-4H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a4 4 0 0 1 4-4h6z"/>
            @break
        @case('calendar-days')
            <path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>
            @break
        @case('chevron-right')
            <path d="m9 18 6-6-6-6"/>
            @break
        @case('circle-alert')
            <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
            @break
        @case('circle-check')
            <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('clock-3')
            <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
            @break
        @case('eye')
            <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.67 7.73 7.62 5 12 5s8.33 2.73 9.94 6.65a1 1 0 0 1 0 .7C20.33 16.27 16.38 19 12 19s-8.33-2.73-9.94-6.65Z"/><circle cx="12" cy="12" r="3"/>
            @break
        @case('eye-off')
            <path d="m3 3 18 18M10.58 10.58a2 2 0 0 0 2.83 2.83M9.88 5.09A10.94 10.94 0 0 1 12 5c4.38 0 8.33 2.73 9.94 6.65a1 1 0 0 1 0 .7 10.97 10.97 0 0 1-4.07 4.65M6.61 6.61A10.97 10.97 0 0 0 2.06 11.65a1 1 0 0 0 0 .7C3.67 16.27 7.62 19 12 19c1.54 0 3.01-.33 4.33-.92"/>
            @break
        @case('file-check')
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 15l2 2 4-4"/>
            @break
        @case('home')
            <path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>
            @break
        @case('grid-2x2')
            <rect width="6" height="6" x="3" y="3" rx="1"/><rect width="6" height="6" x="15" y="3" rx="1"/><rect width="6" height="6" x="3" y="15" rx="1"/><rect width="6" height="6" x="15" y="15" rx="1"/>
            @break
        @case('leaf')
            <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5.3 18 2 18 2c1 5.5-.5 10.5-4.5 12.5"/><path d="M2 21c0-3 1.85-5.36 5.08-6.94C9.3 12.98 12 12 16 12"/>
            @break
        @case('layout-dashboard')
            <rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>
            @break
        @case('loader-circle')
            <path d="M21 12a9 9 0 1 1-6.22-8.56"/>
            @break
        @case('log-in')
            <path d="m10 17 5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
            @break
        @case('log-out')
            <path d="m16 17 5-5-5-5M21 12H9M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            @break
        @case('map-pin')
            <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>
            @break
        @case('magic-wand')
            <path d="m19 3-7.5 7.5"/><circle cx="15" cy="9" r="1"/><path d="m3 19 7.5-7.5"/><circle cx="9" cy="15" r="1"/><path d="M15 3h4v4M3 15h4v4"/>
            @break
        @case('megaphone')
            <path d="m3 11 18-5v12L3 14z"/><path d="M11.6 16.8 10 22H7l1.8-7"/><path d="M3 11v3"/>
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('package-open')
            <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9Z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9M8 5l8 4.5"/>
            @break
        @case('package-check')
            <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9Z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9M8 5l8 4.5M9 16l2 2 4-4"/>
            @break
        @case('recycle')
            <path d="m7 19-4-7 3-5 2 3"/><path d="m17 19 4-7-3-5-2 3"/><path d="M7 19h10M8 10l4-7 4 7M12 3v5"/>
            @break
        @case('scale')
            <path d="M12 3v18M5 6h14M5 6l-3 6a3 3 0 0 0 6 0L5 6ZM19 6l-3 6a3 3 0 0 0 6 0l-3-6ZM5 21h14"/>
            @break
        @case('target')
            <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>
            @break
        @case('truck')
            <path d="M10 17h4V5H2v12h3M14 9h4l4 4v4h-2M5 17a2 2 0 1 0 4 0M16 17a2 2 0 1 0 4 0"/>
            @break
        @case('x')
            <path d="M18 6 6 18M6 6l12 12"/>
            @break
    @endswitch
</svg>
