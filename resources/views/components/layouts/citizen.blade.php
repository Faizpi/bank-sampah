@props([
    'title',
    'context' => null,
])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123D32">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192x192.png') }}" sizes="192x192" type="image/png">
    <title>{{ $title }} — Bank Sampah Sindangheula</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh overflow-x-clip bg-warm-canvas text-body text-text-primary antialiased">
    <a href="#konten-utama" class="fixed left-4 top-4 z-toast -translate-y-24 rounded-md bg-deep-green px-4 py-3 font-semibold text-white transition-transform duration-180 focus:translate-y-0">
        Lewati ke konten utama
    </a>

    <x-public.offline-status />

    @php
        $citizenRoute = request()->route()?->getName() ?? '';
        $citizenActiveNav = match(true) {
            $citizenRoute === 'citizen.dashboard' => 'Beranda',
            $citizenRoute === 'citizen.deposit-history' => 'Setoran',
            str_contains($citizenRoute, 'deposit-receipt') => 'Setoran',
            str_contains($citizenRoute, 'pickup') || str_contains($citizenRoute, 'grocery') || str_contains($citizenRoute, 'withdrawal') || str_contains($citizenRoute, 'estimate') => 'Layanan',
            str_contains($citizenRoute, 'customer-card') => 'Kartu Nasabah',
            default => 'Akun',
        };

        // Navigation component requires relative internal paths (/path)
        $navPath = static fn (string $routeName, ...$params): string =>
            parse_url(route($routeName, ...$params), PHP_URL_PATH)
            . (parse_url(route($routeName, ...$params), PHP_URL_QUERY) ? '?' . parse_url(route($routeName, ...$params), PHP_URL_QUERY) : '');
    @endphp

    {{-- App Header --}}
    <header class="sticky top-0 z-sticky border-b border-border bg-surface shadow-xs">
        <div class="mx-auto flex min-h-16 max-w-citizen items-center gap-3 px-4 sm:px-5">
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-title font-bold text-deep-green">{{ $title }}</h1>
                @if ($context)
                    <p class="truncate text-caption text-text-secondary">{{ $context }}</p>
                @endif
            </div>
            <div class="flex min-h-touch shrink-0 items-center gap-1">
                @isset($headerActions){{ $headerActions }}@endisset
                {{-- Logout button --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        aria-label="Keluar dari akun"
                        class="inline-flex min-h-touch min-w-touch items-center justify-center rounded-xl text-text-secondary transition hover:bg-danger-bg hover:text-terracotta">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m16 17 5-5-5-5M21 12H9M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main id="konten-utama" tabindex="-1"
        class="mx-auto grid min-h-[calc(100dvh-4rem)] w-full max-w-citizen grid-cols-1 content-start gap-6 px-4 pb-[calc(5.75rem+env(safe-area-inset-bottom))] pt-6 sm:px-5 md:pb-24 md:pt-8">
        @isset($balance)<section class="min-w-0" data-slot-balance>{{ $balance }}</section>@endisset
        @isset($quickActions)<section class="min-w-0" data-slot-quick-actions>{{ $quickActions }}</section>@endisset
        @isset($activeRequests)<section class="min-w-0" data-slot-active-requests>{{ $activeRequests }}</section>@endisset
        @isset($recentHistory)<section class="min-w-0" data-slot-recent-history>{{ $recentHistory }}</section>@endisset
        @isset($contextualInformation)<section class="min-w-0" data-slot-contextual-information>{{ $contextualInformation }}</section>@endisset
        {{ $slot }}
    </main>

    {{-- Bottom Navigation --}}
    <x-citizen.navigation
        :destinations="[
            'Beranda'       => $navPath('citizen.dashboard'),
            'Setoran'       => $navPath('citizen.deposit-history'),
            'Layanan'       => $navPath('citizen.pickup.create'),
            'Kartu Nasabah' => $navPath('citizen.customer-card'),
            'Akun'          => $navPath('profile.password'),
        ]"
        :active="$citizenActiveNav"
    />

    @livewireScripts
</body>
</html>
