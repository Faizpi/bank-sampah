@props([
    'title',
])

@php
    $routeName = request()->route()?->getName() ?? '';
    $actor = auth()->user();
    $access = app(\App\Authorization\PermissionChecker::class);
    $can = static fn (string $ability): bool => $actor instanceof \App\Models\User && $access->allows($actor, $ability);
    $isTreasurer = $actor && $actor->roles()->where('name', 'bendahara')->exists();
    $persona = match (true) {
        str_starts_with($routeName, 'treasurer.') => 'treasurer',
        str_starts_with($routeName, 'officer.') => 'officer',
        default => $isTreasurer ? 'treasurer' : 'officer',
    };

    if ($persona === 'treasurer') {
        $destinations = [
            'Tugas'        => route('treasurer.dashboard'),
        ];
        if ($can('withdrawal.pay')) {
            $destinations['Pembayaran'] = route('treasurer.withdrawal.payments');
        }
        if ($can('report.view')) {
            $destinations['Laporan'] = route('treasurer.reports');
        }
        if ($can('profile.view')) {
            $destinations['Akun'] = route('profile.password');
        }
        $activeNav = match (true) {
            $routeName === 'treasurer.dashboard' => 'Tugas',
            str_starts_with($routeName, 'treasurer.withdrawal') => 'Pembayaran',
            str_starts_with($routeName, 'treasurer.reports') => 'Laporan',
            default => 'Akun',
        };
    } else {
        $destinations = [
            'Tugas'   => route('officer.dashboard'),
        ];
        if ($can('customer.view')) {
            $destinations['Setoran'] = route('officer.customer-identification');
        }
        if ($can('mobile-service.operate')) {
            $destinations['Layanan'] = route('officer.mobile-services');
        }
        if ($can('profile.view')) {
            $destinations['Akun'] = route('profile.password');
        }
        $activeNav = match (true) {
            in_array($routeName, ['officer.dashboard', 'officer.pickup.task', 'officer.grocery.tasks'], true) => 'Tugas',
            in_array($routeName, ['officer.customer-identification', 'officer.deposit-form'], true) => 'Setoran',
            $routeName === 'officer.mobile-services' => 'Layanan',
            default => 'Akun',
        };
    }

    if (! array_key_exists($activeNav, $destinations)) {
        $activeNav = 'Tugas';
    }
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123D32">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192x192.png') }}" sizes="192x192" type="image/png">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh bg-warm-canvas text-body text-text-primary antialiased">
    <a href="#konten-utama" class="fixed left-4 top-4 z-toast -translate-y-24 rounded-md bg-deep-green px-4 py-3 font-semibold text-white transition-transform duration-180 focus:translate-y-0">
        Lewati ke konten utama
    </a>

    <x-officer.header :title="$title">
        @isset($date)
            <x-slot:date>{{ $date }}</x-slot:date>
        @endisset
        @isset($location)
            <x-slot:location>{{ $location }}</x-slot:location>
        @endisset
        @isset($connectivity)
            <x-slot:connectivity>{{ $connectivity }}</x-slot:connectivity>
        @endisset
        <x-slot:profile>
            @isset($profile){{ $profile }}@endisset
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
        </x-slot:profile>
    </x-officer.header>
    <x-public.offline-status />

    <main id="konten-utama" tabindex="-1" class="mx-auto grid min-h-[calc(100dvh-4rem)] w-full max-w-officer content-start gap-8 px-4 pb-[calc(5.75rem+env(safe-area-inset-bottom))] pt-8 sm:px-5 md:pb-24 md:pt-10">
        @isset($todayTasks)
            <section data-slot-today-tasks>{{ $todayTasks }}</section>
        @endisset

        @isset($taskActions)
            <section data-slot-task-actions>{{ $taskActions }}</section>
        @endisset

        @isset($operationalContext)
            <section data-slot-operational-context>{{ $operationalContext }}</section>
        @endisset

        @isset($recentActivity)
            <section data-slot-recent-activity>{{ $recentActivity }}</section>
        @endisset

        {{ $slot }}
    </main>

    {{-- Bottom Navigation --}}
    <x-officer.navigation
        :persona="$persona"
        :destinations="$destinations"
        :active="$activeNav"
    />

    @livewireScripts
</body>
</html>
