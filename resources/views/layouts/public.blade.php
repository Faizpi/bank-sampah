@php
    $defaultTitle = 'Bank Sampah Digital Sindangheula';
    $defaultDescription = 'Layanan bank sampah digital Desa Sindangheula untuk pencatatan setoran, saldo rupiah, penjemputan, dan informasi program desa yang transparan.';
    $pageTitle = trim((string) $__env->yieldContent('title')) ?: (string) ($title ?? $defaultTitle);
    $pageDescription = trim((string) $__env->yieldContent('description')) ?: (string) ($description ?? $defaultDescription);
@endphp
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $pageDescription }}">
        <meta name="theme-color" content="#123D32">
        <meta name="color-scheme" content="light">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" href="{{ asset('icons/icon-192x192.png') }}" sizes="192x192" type="image/png">

        <title>{{ $pageTitle }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body @if (session('pwa_logout_confirmed') === true) data-pwa-logout-confirmed="true" @endif class="min-h-[100dvh] overflow-x-hidden bg-surface text-text-primary antialiased">
        <a href="#konten-utama" class="sr-only z-toast rounded-md bg-deep-green px-4 py-3 text-surface focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
            Lewati ke konten utama
        </a>

        <div class="flex min-h-[100dvh] flex-col">
            <x-public.header />
            <x-public.offline-status />

            <main id="konten-utama" tabindex="-1" class="min-w-0 flex-1">
                @hasSection('content')
                    @yield('content')
                @else
                    {{ $slot ?? '' }}
                @endif
            </main>

            <x-public.footer />
        </div>

        @livewireScripts
    </body>
</html>
