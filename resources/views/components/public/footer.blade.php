@php
    $isCatalog = request()->routeIs('public.catalog');
    $isPrices = request()->routeIs('public.prices');
    $isSchedule = request()->routeIs('public.mobile-schedule');
    $isAnnouncements = request()->routeIs('public.announcements');
    $isPrograms = request()->routeIs('public.programs');
    $isTerms = request()->routeIs('terms-and-privacy');
    $isRegister = request()->routeIs('register');
    $isLogin = request()->routeIs('login');
    $activeFooterClasses = 'font-bold text-surface underline decoration-harvest-gold decoration-2 underline-offset-4';
    $inactiveFooterClasses = 'font-semibold text-success-bg hover:text-surface hover:underline';
@endphp

<footer class="border-t border-deep-green bg-deep-green text-surface">
    <div class="public-container">
        <div class="grid gap-10 py-12 lg:grid-cols-[1.1fr_1.9fr] lg:gap-16 lg:py-16">
            <div class="max-w-md">
                <a href="{{ route('home') }}" class="inline-flex min-h-touch items-center gap-3 rounded-md focus-visible:outline-offset-4" aria-label="Bank Sampah Digital Sindangheula, beranda">
                    <img src="{{ asset('images/landing/mascot-3.png') }}" alt="" class="size-11 shrink-0 object-contain" aria-hidden="true">
                    <span>
                        <span class="block text-title font-bold leading-tight">Bank Sampah Digital</span>
                        <span class="block text-body-sm font-medium text-success-bg">Desa Sindangheula</span>
                    </span>
                </a>
                <p class="mt-5 max-w-sm text-body-sm leading-6 text-success-bg">Layanan transparan untuk pencatatan setoran, saldo rupiah, dan program pengelolaan sampah desa.</p>
                <x-public.location-preview />
            </div>

            <div class="grid gap-8 sm:grid-cols-3">
                <div>
                    <h2 class="text-label font-bold uppercase tracking-wide text-surface">Jelajahi</h2>
                    <nav class="mt-3 flex flex-col items-start gap-1" aria-label="Navigasi footer jelajahi">
                        <a href="{{ route('home') }}" class="inline-flex min-h-touch items-center text-body-sm {{ request()->routeIs('home') ? $activeFooterClasses : $inactiveFooterClasses }}" @if (request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
                        <a href="{{ route('home') }}#layanan" class="inline-flex min-h-touch items-center text-body-sm {{ $inactiveFooterClasses }}">Layanan</a>
                        <a href="{{ route('public.catalog') }}" class="inline-flex min-h-touch items-center text-body-sm {{ $isCatalog ? $activeFooterClasses : $inactiveFooterClasses }}" @if ($isCatalog) aria-current="page" @endif>Katalog</a>
                        <a href="{{ route('public.prices') }}" class="inline-flex min-h-touch items-center text-body-sm {{ $isPrices ? $activeFooterClasses : $inactiveFooterClasses }}" @if ($isPrices) aria-current="page" @endif>Harga</a>
                        <a href="{{ route('home') }}#cara-kerja" class="inline-flex min-h-touch items-center text-body-sm {{ $inactiveFooterClasses }}">Cara kerja</a>
                    </nav>
                </div>

                <div>
                    <h2 class="text-label font-bold uppercase tracking-wide text-surface">Program publik</h2>
                    <nav class="mt-3 flex flex-col items-start gap-1" aria-label="Navigasi footer program publik">
                        <a href="{{ route('public.mobile-schedule') }}" class="inline-flex min-h-touch items-center text-body-sm {{ $isSchedule ? $activeFooterClasses : $inactiveFooterClasses }}" @if ($isSchedule) aria-current="page" @endif>Jadwal keliling</a>
                        <a href="{{ route('public.announcements') }}" class="inline-flex min-h-touch items-center text-body-sm {{ $isAnnouncements ? $activeFooterClasses : $inactiveFooterClasses }}" @if ($isAnnouncements) aria-current="page" @endif>Pengumuman</a>
                        <a href="{{ route('public.programs') }}" class="inline-flex min-h-touch items-center text-body-sm {{ $isPrograms ? $activeFooterClasses : $inactiveFooterClasses }}" @if ($isPrograms) aria-current="page" @endif>Target dan statistik</a>
                    </nav>
                </div>

                <div>
                    <h2 class="text-label font-bold uppercase tracking-wide text-surface">Akses</h2>
                    <nav class="mt-3 flex flex-col items-start gap-1" aria-label="Navigasi footer akses">
                        <a href="{{ route('terms-and-privacy') }}" class="inline-flex min-h-touch items-center text-body-sm {{ $isTerms ? $activeFooterClasses : $inactiveFooterClasses }}" @if ($isTerms) aria-current="page" @endif>Ketentuan dan privasi</a>
                        <a href="{{ route('register') }}" class="inline-flex min-h-touch items-center text-body-sm {{ $isRegister ? $activeFooterClasses : $inactiveFooterClasses }}" @if ($isRegister) aria-current="page" @endif>Daftar</a>
                        <a href="{{ route('login') }}" class="inline-flex min-h-touch items-center text-body-sm {{ $isLogin ? $activeFooterClasses : $inactiveFooterClasses }}" @if ($isLogin) aria-current="page" @endif>Masuk</a>
                    </nav>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-2 border-t border-success-bg/20 py-5 text-body-sm text-success-bg sm:flex-row sm:items-center sm:justify-between">
            <p>Bank Sampah Digital Desa Sindangheula.</p>
            <p>Informasi publik yang jelas untuk keputusan yang lebih baik.</p>
        </div>
    </div>
</footer>
