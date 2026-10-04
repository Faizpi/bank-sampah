@php
    $isHome = request()->routeIs('home');
    $isCatalog = request()->routeIs('public.catalog');
    $isPrices = request()->routeIs('public.prices');
    $isSchedule = request()->routeIs('public.mobile-schedule');
    $isAnnouncements = request()->routeIs('public.announcements');
    $isPrograms = request()->routeIs('public.programs');
    $isTerms = request()->routeIs('terms-and-privacy');
    $isRegister = request()->routeIs('register');
    $isLogin = request()->routeIs('login');
    $activeItemClasses = 'rounded-md bg-success-bg font-bold text-deep-green shadow-xs';
    $inactiveItemClasses = 'rounded-md font-semibold text-deep-green hover:bg-success-bg';
    $activeAccountClasses = 'ring-2 ring-focus ring-offset-2';
@endphp

<x-ui.bottom-sheet
    id="public-mobile-navigation"
    name="public-mobile-navigation"
    title="Navigasi"
    description="Pilih halaman publik atau masuk ke akun Anda."
    class="xl:hidden"
>
    <nav aria-label="Navigasi seluler" class="space-y-6">
        <section aria-labelledby="public-mobile-explore">
            <h3 id="public-mobile-explore" class="text-label font-bold uppercase tracking-wide text-forest-700">Jelajahi</h3>
            <ul class="mt-2">
                <li>
                    <a href="{{ route('home') }}" x-on:click="closeModal()" class="flex min-h-touch items-center gap-3 py-3 pl-3 {{ $isHome ? $activeItemClasses : $inactiveItemClasses }}" @if ($isHome) aria-current="page" @endif>
                        <x-public.icon name="leaf" />
                        Beranda
                    </a>
                </li>
                <li>
                    <a href="{{ route('home') }}#layanan" x-on:click="closeModal()" class="flex min-h-touch items-center gap-3 py-3 pl-3 {{ $inactiveItemClasses }}">
                        <x-public.icon name="recycle" />
                        Layanan
                    </a>
                </li>
                <li>
                    <a href="{{ route('public.catalog') }}" x-on:click="closeModal()" class="flex min-h-touch items-center gap-3 py-3 pl-3 {{ $isCatalog ? $activeItemClasses : $inactiveItemClasses }}" @if ($isCatalog) aria-current="page" @endif>
                        <x-public.icon name="book-open" />
                        Katalog
                    </a>
                </li>
                <li>
                    <a href="{{ route('public.prices') }}" x-on:click="closeModal()" class="flex min-h-touch items-center gap-3 py-3 pl-3 {{ $isPrices ? $activeItemClasses : $inactiveItemClasses }}" @if ($isPrices) aria-current="page" @endif>
                        <x-public.icon name="banknote" />
                        Harga
                    </a>
                </li>
                <li>
                    <a href="{{ route('home') }}#cara-kerja" x-on:click="closeModal()" class="flex min-h-touch items-center gap-3 py-3 pl-3 {{ $inactiveItemClasses }}">
                        <x-public.icon name="scale" />
                        Cara kerja
                    </a>
                </li>
            </ul>
        </section>

        <section aria-labelledby="public-mobile-updates">
            <h3 id="public-mobile-updates" class="text-label font-bold uppercase tracking-wide text-forest-700">Informasi publik</h3>
            <ul class="mt-2">
                <li>
                    <a href="{{ route('public.mobile-schedule') }}" x-on:click="closeModal()" class="flex min-h-touch items-center gap-3 py-3 pl-3 {{ $isSchedule ? $activeItemClasses : $inactiveItemClasses }}" @if ($isSchedule) aria-current="page" @endif>
                        <x-public.icon name="calendar-days" />
                        Jadwal keliling
                    </a>
                </li>
                <li>
                    <a href="{{ route('public.announcements') }}" x-on:click="closeModal()" class="flex min-h-touch items-center gap-3 py-3 pl-3 {{ $isAnnouncements ? $activeItemClasses : $inactiveItemClasses }}" @if ($isAnnouncements) aria-current="page" @endif>
                        <x-public.icon name="megaphone" />
                        Pengumuman
                    </a>
                </li>
                <li>
                    <a href="{{ route('public.programs') }}" x-on:click="closeModal()" class="flex min-h-touch items-center gap-3 py-3 pl-3 {{ $isPrograms ? $activeItemClasses : $inactiveItemClasses }}" @if ($isPrograms) aria-current="page" @endif>
                        <x-public.icon name="circle-check" />
                        Target dan statistik
                    </a>
                </li>
                <li>
                    <a href="{{ route('terms-and-privacy') }}" x-on:click="closeModal()" class="flex min-h-touch items-center gap-3 py-3 pl-3 {{ $isTerms ? $activeItemClasses : $inactiveItemClasses }}" @if ($isTerms) aria-current="page" @endif>
                        <x-public.icon name="book-open" />
                        Ketentuan dan privasi
                    </a>
                </li>
            </ul>
        </section>

        <section aria-labelledby="public-mobile-account">
            <h3 id="public-mobile-account" class="text-label font-bold uppercase tracking-wide text-forest-700">Akun</h3>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <a href="{{ route('login') }}" x-on:click="closeModal()" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-forest-600 px-4 font-bold text-surface shadow-xs hover:bg-forest-700 {{ $isLogin ? $activeAccountClasses : '' }}" @if ($isLogin) aria-current="page" @endif>
                    <x-public.icon name="log-in" size="size-5" />
                    Masuk
                </a>
                <a href="{{ route('register') }}" x-on:click="closeModal()" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-forest-600 px-4 font-bold text-forest-700 hover:bg-success-bg {{ $isRegister ? $activeAccountClasses : '' }}" @if ($isRegister) aria-current="page" @endif>
                    Daftar
                    <x-public.icon name="arrow-right" size="size-5" />
                </a>
            </div>
        </section>
    </nav>
</x-ui.bottom-sheet>
