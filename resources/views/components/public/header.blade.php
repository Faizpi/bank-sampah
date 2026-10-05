@php
    $isHome = request()->routeIs('home');
    $isCatalog = request()->routeIs('public.catalog');
    $isPrices = request()->routeIs('public.prices');
    $isSchedule = request()->routeIs('public.mobile-schedule');
    $isAnnouncements = request()->routeIs('public.announcements');
    $isPrograms = request()->routeIs('public.programs');
    $isTerms = request()->routeIs('terms-and-privacy');
    $hasHeroOverlay = $isHome || request()->routeIs(
        'public.catalog',
        'public.prices',
        'public.mobile-schedule',
        'public.announcements',
        'public.programs',
        'public.deposit-verification',
        'terms-and-privacy',
    );
    $isExploreActive = $isHome || $isCatalog || $isPrices;
    $isPublicInformationActive = $isSchedule || $isAnnouncements || $isPrograms || $isTerms;
    $isLogin = request()->routeIs('login');
    $isRegister = request()->routeIs('register');
    $activeAccountClasses = 'ring-2 ring-focus ring-offset-2';
    $activeNavClasses = 'bg-success-bg font-bold text-deep-green shadow-xs';
    $inactiveNavClasses = 'font-semibold text-text-secondary hover:bg-success-bg hover:text-deep-green';
@endphp

<header
    data-public-header="{{ $hasHeroOverlay ? 'overlay' : 'flow' }}"
    @class([
        'sticky top-0 z-sticky px-3 pt-3 sm:px-5',
        '-mb-[4.75rem]' => $hasHeroOverlay,
    ])
>
    <div class="public-container flex min-h-16 items-center justify-between gap-3 rounded-full border border-border/90 bg-surface/95 px-3 shadow-sm backdrop-blur sm:px-4">
        <a href="{{ route('home') }}" class="flex min-h-touch shrink-0 items-center gap-2 rounded-md focus-visible:outline-offset-4" aria-label="Bank Sampah Digital Sindangheula, beranda">
            <img src="{{ asset('images/landing/mascot-3.png') }}" alt="" class="size-11 shrink-0 object-contain" aria-hidden="true">
            <span class="min-w-0">
                <span class="block whitespace-nowrap text-caption font-bold leading-tight text-deep-green sm:text-title">Bank Sampah Digital</span>
                <span class="block whitespace-nowrap text-caption font-medium text-text-secondary sm:text-body-sm">Desa Sindangheula</span>
            </span>
        </a>

        <nav class="flex min-w-0 items-center gap-1" aria-label="Navigasi utama">
            <div class="hidden min-w-0 items-center gap-1 xl:flex">
                <div class="relative" data-public-nav-group>
                    <button type="button" class="inline-flex min-h-touch items-center gap-2 whitespace-nowrap rounded-full px-3 text-body-sm transition-colors duration-180 hover:bg-success-bg hover:text-deep-green {{ $isExploreActive ? $activeNavClasses : $inactiveNavClasses }}" aria-controls="public-nav-jelajahi-menu" aria-expanded="false" data-public-nav-trigger>
                        Jelajahi
                        <x-public.icon name="chevron-right" size="size-4" data-public-nav-chevron />
                    </button>
                    <div id="public-nav-jelajahi-menu" class="absolute left-0 top-full z-popover mt-2 min-w-52 rounded-md border border-border bg-surface p-2 shadow-sm before:absolute before:inset-x-0 before:-top-2 before:h-2 before:content-['']" hidden data-public-nav-menu>
                        <a href="{{ route('home') }}" class="flex min-h-touch w-full items-center whitespace-nowrap rounded-md px-3 text-body-sm transition-colors duration-180 {{ $isHome ? $activeNavClasses : $inactiveNavClasses }}" @if ($isHome) aria-current="page" @endif data-public-nav-link>Beranda</a>
                        <a href="{{ route('home') }}#layanan" class="flex min-h-touch w-full items-center whitespace-nowrap rounded-md px-3 text-body-sm transition-colors duration-180 {{ $inactiveNavClasses }}" data-public-nav-link>Layanan</a>
                        <a href="{{ route('public.catalog') }}" class="flex min-h-touch w-full items-center whitespace-nowrap rounded-md px-3 text-body-sm transition-colors duration-180 {{ $isCatalog ? $activeNavClasses : $inactiveNavClasses }}" @if ($isCatalog) aria-current="page" @endif data-public-nav-link>Katalog</a>
                        <a href="{{ route('public.prices') }}" class="flex min-h-touch w-full items-center whitespace-nowrap rounded-md px-3 text-body-sm transition-colors duration-180 {{ $isPrices ? $activeNavClasses : $inactiveNavClasses }}" @if ($isPrices) aria-current="page" @endif data-public-nav-link>Harga</a>
                        <a href="{{ route('home') }}#cara-kerja" class="flex min-h-touch w-full items-center whitespace-nowrap rounded-md px-3 text-body-sm transition-colors duration-180 {{ $inactiveNavClasses }}" data-public-nav-link>Cara kerja</a>
                    </div>
                </div>

                <div class="relative" data-public-nav-group>
                    <button type="button" class="inline-flex min-h-touch items-center gap-2 whitespace-nowrap rounded-full px-3 text-body-sm transition-colors duration-180 hover:bg-success-bg hover:text-deep-green {{ $isPublicInformationActive ? $activeNavClasses : $inactiveNavClasses }}" aria-controls="public-nav-informasi-menu" aria-expanded="false" data-public-nav-trigger>
                        Informasi publik
                        <x-public.icon name="chevron-right" size="size-4" data-public-nav-chevron />
                    </button>
                    <div id="public-nav-informasi-menu" class="absolute right-0 top-full z-popover mt-2 min-w-60 rounded-md border border-border bg-surface p-2 shadow-sm before:absolute before:inset-x-0 before:-top-2 before:h-2 before:content-['']" hidden data-public-nav-menu>
                        <a href="{{ route('public.mobile-schedule') }}" class="flex min-h-touch w-full items-center whitespace-nowrap rounded-md px-3 text-body-sm transition-colors duration-180 {{ $isSchedule ? $activeNavClasses : $inactiveNavClasses }}" @if ($isSchedule) aria-current="page" @endif data-public-nav-link>Jadwal keliling</a>
                        <a href="{{ route('public.announcements') }}" class="flex min-h-touch w-full items-center whitespace-nowrap rounded-md px-3 text-body-sm transition-colors duration-180 {{ $isAnnouncements ? $activeNavClasses : $inactiveNavClasses }}" @if ($isAnnouncements) aria-current="page" @endif data-public-nav-link>Pengumuman</a>
                        <a href="{{ route('public.programs') }}" class="flex min-h-touch w-full items-center whitespace-nowrap rounded-md px-3 text-body-sm transition-colors duration-180 {{ $isPrograms ? $activeNavClasses : $inactiveNavClasses }}" @if ($isPrograms) aria-current="page" @endif data-public-nav-link>Target dan statistik</a>
                        <a href="{{ route('terms-and-privacy') }}" class="flex min-h-touch w-full items-center whitespace-nowrap rounded-md px-3 text-body-sm transition-colors duration-180 {{ $isTerms ? $activeNavClasses : $inactiveNavClasses }}" @if ($isTerms) aria-current="page" @endif data-public-nav-link>Ketentuan dan privasi</a>
                    </div>
                </div>
            </div>

            <div class="hidden shrink-0 items-center gap-2 xl:flex">
                @auth
                    @php
                        $authedDashboard = app(\App\Support\Auth\AuthenticatedUserRedirector::class)->dashboardUrl();
                    @endphp
                    <a href="{{ $authedDashboard }}" class="inline-flex min-h-touch items-center justify-center gap-2 whitespace-nowrap rounded-full bg-forest-600 px-4 text-body-sm font-bold text-surface shadow-xs transition duration-180 ease-standard hover:bg-forest-700 active:translate-y-px">
                        <x-public.icon name="layout-dashboard" size="size-5" />
                        Buka Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex min-h-touch items-center justify-center gap-2 whitespace-nowrap rounded-full bg-forest-600 px-4 text-body-sm font-bold text-surface shadow-xs transition duration-180 ease-standard hover:bg-forest-700 active:translate-y-px {{ $isLogin ? $activeAccountClasses : '' }}" @if ($isLogin) aria-current="page" @endif><x-public.icon name="log-in" size="size-5" />Masuk</a>
                    <a href="{{ route('register') }}" class="inline-flex min-h-touch items-center justify-center gap-2 whitespace-nowrap rounded-full border border-forest-600 bg-surface px-4 text-body-sm font-bold text-forest-700 transition duration-180 ease-standard hover:bg-success-bg active:translate-y-px {{ $isRegister ? $activeAccountClasses : '' }}" @if ($isRegister) aria-current="page" @endif>Daftar<x-public.icon name="arrow-right" size="size-5" /></a>
                @endauth
            </div>


            <button type="button" class="inline-flex min-h-touch min-w-touch shrink-0 items-center justify-center rounded-full text-deep-green transition-colors duration-180 hover:bg-success-bg active:bg-warning-bg xl:hidden" aria-label="Buka navigasi" aria-haspopup="dialog" aria-controls="public-mobile-navigation" data-public-navigation-trigger>
                <x-public.icon name="menu" size="size-6" />
            </button>
        </nav>
    </div>

    <x-public.mobile-navigation />
</header>
