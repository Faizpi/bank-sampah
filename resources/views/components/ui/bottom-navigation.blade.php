@props([
    'items',
    'label' => 'Navigasi utama',
])

@php
    if (count($items) > 5) {
        throw new InvalidArgumentException('Bottom navigation supports at most five items.');
    }
    if (count(array_filter($items, fn (array $item): bool => (bool) ($item['active'] ?? false))) > 1) {
        throw new InvalidArgumentException('Bottom navigation supports at most one active item.');
    }
    $allowedIcons = ['home', 'layout-dashboard', 'recycle', 'grid-2x2', 'history', 'user-round', 'scan-line', 'clipboard-list', 'wallet-cards'];
@endphp

<nav aria-label="{{ $label }}" {{ $attributes->class('fixed bottom-[calc(0.75rem+env(safe-area-inset-bottom))] left-1/2 z-bottom-nav w-[calc(100%-1.5rem)] max-w-citizen -translate-x-1/2 rounded-full border border-border/90 bg-surface/95 p-1 shadow-sm backdrop-blur') }}>
    <div class="mx-auto grid min-h-16 max-w-citizen grid-flow-col auto-cols-fr">
        @foreach ($items as $item)
            @php
                $active = (bool) ($item['active'] ?? false);
                $icon = in_array($item['icon'] ?? '', $allowedIcons, true) ? $item['icon'] : 'grid-2x2';
            @endphp
            <a
                data-nav-item
                href="{{ $item['href'] ?? '#' }}"
                @if ($active) aria-current="page" @endif
                @class([
                    'inline-flex min-h-touch min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-caption transition duration-180 focus-visible:ring-2 focus-visible:ring-focus sm:px-2',
                    'font-semibold text-forest-600' => $active,
                    'text-text-secondary hover:text-deep-green active:text-forest-600' => ! $active,
                ])
            >
                <span class="relative">
                    <svg data-lucide="{{ $icon }}" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        @switch($icon)
                            @case('home') <path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/> @break
                            @case('layout-dashboard') <rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/> @break
                            @case('recycle') <path d="m7 19-2 2-2-2M5 21v-4a4 4 0 0 1 4-4h8M17 5l2-2 2 2M19 3v4a4 4 0 0 1-4 4H7M7 5 5 3 3 5"/> @break
                            @case('history') <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/> @break
                            @case('user-round') <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/> @break
                            @case('scan-line') <path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/> @break
                            @case('clipboard-list') <rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0M9 12h.01M13 12h2M9 16h.01M13 16h2"/> @break
                            @case('wallet-cards') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M16 13h5M3 9h18"/> @break
                            @default <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                        @endswitch
                    </svg>
                    @if (! empty($item['badge']))<span aria-label="{{ $item['badge'] }} notifikasi" class="absolute -right-3 -top-2 min-w-5 rounded-sm bg-terracotta px-1 text-center text-caption text-white">{{ $item['badge'] }}</span>@endif
                </span>
                <span class="block w-full break-words text-center leading-tight">{{ $item['label'] ?? '' }}</span>
            </a>
        @endforeach
    </div>
</nav>
