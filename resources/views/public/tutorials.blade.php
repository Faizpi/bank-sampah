@extends('layouts.public')

@section('title', 'Tutorial Penggunaan dan Panduan Video | Bank Sampah Digital')
@section('description', 'Kumpulan panduan video tutorial resmi Google Drive untuk warga, petugas, bendahara, dan administrator Bank Sampah Digital.')

@php
    $tutorials = config('tutorials.items', []);
    $roleConfigs = config('tutorials.roles', []);
    $wargaCount = count(array_filter($tutorials, fn($t) => $t['role'] === 'warga'));
    $petugasCount = count(array_filter($tutorials, fn($t) => $t['role'] === 'petugas'));
    $bendaharaCount = count(array_filter($tutorials, fn($t) => $t['role'] === 'bendahara'));
    $adminCount = count(array_filter($tutorials, fn($t) => $t['role'] === 'superadmin'));
    $totalCount = count($tutorials);
@endphp

@section('content')
    <div class="public-canvas">
        {{-- ── Hero Section ── --}}
        <section class="border-b border-deep-green bg-deep-green text-surface" aria-labelledby="tutorials-hero-title">
            <div class="public-container grid items-center gap-8 section-space lg:grid-cols-[1fr_auto]">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 rounded-full bg-surface/10 px-3.5 py-1 text-label font-semibold text-harvest-gold backdrop-blur">
                        <x-public.icon name="video" size="size-4" />
                        Pusat Bantuan & Panduan Resmi
                    </div>
                    <h1 id="tutorials-hero-title" class="mt-3 text-h1 lg:text-h1-lg text-surface">
                        Tutorial Penggunaan Sistem Bank Sampah
                    </h1>
                    <p class="mt-4 text-body text-surface/85 leading-relaxed">
                        Pelajari alur operasional aplikasi Bank Sampah Digital langkah demi langkah melalui video panduan resmi Google Drive yang dikelompokkan sesuai peran Anda.
                    </p>
                    <div class="mt-6 flex flex-wrap items-center gap-3 text-label text-surface/80">
                        <span class="inline-flex items-center gap-1.5">
                            <x-public.icon name="circle-check" size="size-4" class="text-harvest-gold" />
                            {{ $totalCount }} Video Panduan
                        </span>
                        <span class="inline-block size-1 rounded-full bg-surface/40"></span>
                        <span class="inline-flex items-center gap-1.5">
                            <x-public.icon name="circle-check" size="size-4" class="text-harvest-gold" />
                            4 Peran Pengguna
                        </span>
                        <span class="inline-block size-1 rounded-full bg-surface/40"></span>
                        <span class="inline-flex items-center gap-1.5">
                            <x-public.icon name="circle-check" size="size-4" class="text-harvest-gold" />
                            Akses Google Drive Langsung
                        </span>
                    </div>
                </div>
                <div class="flex justify-center">
                    <img
                        src="{{ asset('images/landing/mascot-11.png') }}"
                        alt="Maskot badak membawa tablet dan memberikan petunjuk tutorial penggunaan sistem"
                        class="h-44 w-48 object-contain sm:h-52 sm:w-56 lg:h-60 lg:w-64"
                    >
                </div>
            </div>
        </section>

        {{-- ── Main Tutorials Container with Alpine.js Filtering ── --}}
        <section
            class="public-section-canvas"
            x-data="{
                activeRole: 'all',
                searchQuery: '',
                items: {{ Js::from($tutorials) }},
                filterMatches(item) {
                    const roleOk = this.activeRole === 'all' || item.role === this.activeRole;
                    if (!roleOk) return false;
                    if (!this.searchQuery.trim()) return true;
                    const q = this.searchQuery.toLowerCase().trim();
                    const inTitle = (item.title || '').toLowerCase().includes(q);
                    const inSummary = (item.summary || '').toLowerCase().includes(q);
                    const inSteps = Array.isArray(item.steps) && item.steps.some(s => s.toLowerCase().includes(q));
                    return inTitle || inSummary || inSteps;
                },
                get visibleCount() {
                    return this.items.filter(item => this.filterMatches(item)).length;
                }
            }"
        >
            <div class="public-container section-space">
                {{-- Header Filter & Search --}}
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-label font-semibold tracking-wide text-forest-600">Pilih Kategori Peran</p>
                        <h2 class="mt-1 text-h2 text-deep-green">Temukan Video Sesuai Kebutuhan Anda</h2>
                        <p class="mt-2 max-w-xl text-body-sm text-text-secondary">
                            Klik peran Anda di bawah ini atau gunakan pencarian untuk menemukan panduan fitur secara instan.
                        </p>
                    </div>

                    {{-- Search Input --}}
                    <div class="relative w-full sm:w-80">
                        <label for="tutorial-search" class="sr-only">Cari judul atau topik tutorial</label>
                        <input
                            id="tutorial-search"
                            type="search"
                            x-model="searchQuery"
                            placeholder="Cari tutorial (misal: jemput, cairkan)..."
                            class="min-h-touch w-full rounded-full border border-border bg-surface pl-11 pr-10 text-body-sm text-text-primary placeholder:text-text-secondary/70 focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-600/30"
                        >
                        <div class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-text-secondary">
                            <x-public.icon name="search" size="size-5" />
                        </div>
                        <button
                            type="button"
                            x-show="searchQuery.length > 0"
                            x-on:click="searchQuery = ''"
                            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-text-secondary hover:text-text-primary"
                            aria-label="Hapus pencarian"
                        >
                            <x-public.icon name="x" size="size-4" />
                        </button>
                    </div>
                </div>

                {{-- Role Filter Tabs --}}
                <div class="mt-6 flex flex-wrap items-center gap-2 border-b border-border/80 pb-4">
                    <button
                        type="button"
                        x-on:click="activeRole = 'all'"
                        :class="activeRole === 'all'
                            ? 'bg-deep-green text-surface font-bold shadow-xs'
                            : 'bg-surface text-text-secondary hover:bg-success-bg hover:text-deep-green border border-border/90 font-medium'"
                        class="inline-flex min-h-touch items-center gap-2 rounded-full px-4 py-2 text-body-sm transition duration-180"
                    >
                        <span>Semua Panduan</span>
                        <span
                            :class="activeRole === 'all' ? 'bg-surface/25 text-surface' : 'bg-forest-600/10 text-forest-700'"
                            class="rounded-full px-2 py-0.5 text-caption font-bold"
                        >
                            {{ $totalCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        x-on:click="activeRole = 'warga'"
                        :class="activeRole === 'warga'
                            ? 'bg-forest-600 text-surface font-bold shadow-xs'
                            : 'bg-surface text-text-secondary hover:bg-success-bg hover:text-deep-green border border-border/90 font-medium'"
                        class="inline-flex min-h-touch items-center gap-2 rounded-full px-4 py-2 text-body-sm transition duration-180"
                    >
                        <span>Sesi Warga</span>
                        <span
                            :class="activeRole === 'warga' ? 'bg-surface/25 text-surface' : 'bg-forest-600/10 text-forest-700'"
                            class="rounded-full px-2 py-0.5 text-caption font-bold"
                        >
                            {{ $wargaCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        x-on:click="activeRole = 'petugas'"
                        :class="activeRole === 'petugas'
                            ? 'bg-blue-700 text-surface font-bold shadow-xs'
                            : 'bg-surface text-text-secondary hover:bg-blue-50 hover:text-blue-800 border border-border/90 font-medium'"
                        class="inline-flex min-h-touch items-center gap-2 rounded-full px-4 py-2 text-body-sm transition duration-180"
                    >
                        <span>Sesi Petugas</span>
                        <span
                            :class="activeRole === 'petugas' ? 'bg-surface/25 text-surface' : 'bg-blue-100 text-blue-800'"
                            class="rounded-full px-2 py-0.5 text-caption font-bold"
                        >
                            {{ $petugasCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        x-on:click="activeRole = 'bendahara'"
                        :class="activeRole === 'bendahara'
                            ? 'bg-amber-600 text-surface font-bold shadow-xs'
                            : 'bg-surface text-text-secondary hover:bg-amber-50 hover:text-amber-800 border border-border/90 font-medium'"
                        class="inline-flex min-h-touch items-center gap-2 rounded-full px-4 py-2 text-body-sm transition duration-180"
                    >
                        <span>Sesi Bendahara</span>
                        <span
                            :class="activeRole === 'bendahara' ? 'bg-surface/25 text-surface' : 'bg-amber-100 text-amber-800'"
                            class="rounded-full px-2 py-0.5 text-caption font-bold"
                        >
                            {{ $bendaharaCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        x-on:click="activeRole = 'superadmin'"
                        :class="activeRole === 'superadmin'
                            ? 'bg-purple-700 text-surface font-bold shadow-xs'
                            : 'bg-surface text-text-secondary hover:bg-purple-50 hover:text-purple-800 border border-border/90 font-medium'"
                        class="inline-flex min-h-touch items-center gap-2 rounded-full px-4 py-2 text-body-sm transition duration-180"
                    >
                        <span>Sesi Superadmin / Admin</span>
                        <span
                            :class="activeRole === 'superadmin' ? 'bg-surface/25 text-surface' : 'bg-purple-100 text-purple-800'"
                            class="rounded-full px-2 py-0.5 text-caption font-bold"
                        >
                            {{ $adminCount }}
                        </span>
                    </button>
                </div>

                {{-- Status Hasil Pencarian --}}
                <div class="mt-4 flex items-center justify-between text-body-sm text-text-secondary">
                    <p>
                        Menampilkan <span class="font-bold text-deep-green" x-text="visibleCount"></span> dari {{ $totalCount }} panduan tutorial
                    </p>
                    <button
                        type="button"
                        x-show="searchQuery || activeRole !== 'all'"
                        x-on:click="searchQuery = ''; activeRole = 'all';"
                        class="text-label font-semibold text-forest-600 hover:text-deep-green hover:underline"
                    >
                        Reset Filter
                    </button>
                </div>

                {{-- Tutorial Cards Grid (SSR Blade + Alpine dynamic filtering) --}}
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($tutorials as $item)
                        @php
                            $roleData = match($item['role']) {
                                'warga' => ['badge' => 'bg-emerald-50 text-emerald-800 border-emerald-200', 'dot' => 'bg-emerald-600', 'label' => 'Warga'],
                                'petugas' => ['badge' => 'bg-blue-50 text-blue-800 border-blue-200', 'dot' => 'bg-blue-600', 'label' => 'Petugas'],
                                'bendahara' => ['badge' => 'bg-amber-50 text-amber-800 border-amber-200', 'dot' => 'bg-amber-600', 'label' => 'Bendahara'],
                                'superadmin' => ['badge' => 'bg-purple-50 text-purple-800 border-purple-200', 'dot' => 'bg-purple-600', 'label' => 'Superadmin'],
                                default => ['badge' => 'bg-gray-50 text-gray-800 border-gray-200', 'dot' => 'bg-gray-600', 'label' => ucfirst($item['role'])],
                            };
                        @endphp
                        <article
                            x-show="filterMatches({{ Js::from($item) }})"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="flex flex-col justify-between rounded-xl border border-border bg-surface p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-forest-600/50 hover:shadow-md sm:p-6"
                            data-tutorial-id="{{ $item['id'] }}"
                            data-tutorial-role="{{ $item['role'] }}"
                        >
                            <div>
                                {{-- Card Badges --}}
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-caption font-bold uppercase tracking-wider {{ $roleData['badge'] }}">
                                        <span class="size-1.5 rounded-full {{ $roleData['dot'] }}"></span>
                                        {{ $roleData['label'] }}
                                    </span>

                                    <span class="rounded bg-forest-600/10 px-2 py-0.5 text-caption font-extrabold text-forest-700">
                                        #{{ $item['number'] }}
                                    </span>
                                </div>

                                {{-- Card Title & Summary --}}
                                <h3 class="mt-4 text-h2 leading-snug text-deep-green">{{ $item['number'] }}. {{ $item['title'] }}</h3>
                                <p class="mt-2 text-body-sm leading-relaxed text-text-secondary">{{ $item['summary'] }}</p>

                                {{-- Steps Summary Box --}}
                                <div class="mt-4 rounded-lg bg-surface-muted/60 p-3.5">
                                    <p class="text-caption font-bold uppercase tracking-wide text-forest-700">Ringkasan Alur:</p>
                                    <ol class="mt-2 space-y-1.5 pl-4 text-body-sm text-text-primary list-decimal">
                                        @foreach ($item['steps'] as $step)
                                            <li class="leading-normal">{{ $step }}</li>
                                        @endforeach
                                    </ol>
                                </div>
                            </div>

                            {{-- Google Drive Video CTA Button --}}
                            <div class="mt-6 border-t border-border/70 pt-4">
                                <a
                                    href="{{ $item['video_url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="group inline-flex min-h-touch w-full items-center justify-center gap-2 rounded-lg bg-deep-green px-4 py-2.5 text-label font-bold text-surface shadow-xs transition duration-180 hover:bg-forest-600 active:translate-y-px"
                                >
                                    <x-public.icon name="play" size="size-4" class="transition-transform duration-180 group-hover:scale-110" />
                                    <span>Tonton Video (Google Drive)</span>
                                    <x-public.icon name="external-link" size="size-3.5" class="opacity-75" />
                                </a>
                                <p class="mt-2 text-center text-caption text-text-secondary/80">
                                    Tersimpan di Google Drive • Dapat diputar di HP & Laptop
                                </p>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Empty Search State --}}
                <div
                    x-show="visibleCount === 0"
                    x-cloak
                    class="mx-auto my-12 max-w-md rounded-2xl border border-dashed border-border bg-surface p-8 text-center"
                >
                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-forest-600/10 text-forest-700">
                        <x-public.icon name="circle-alert" size="size-6" />
                    </div>
                    <h3 class="mt-4 text-title font-bold text-deep-green">Tutorial Tidak Ditemukan</h3>
                    <p class="mt-2 text-body-sm text-text-secondary">
                        Tidak ada panduan yang cocok dengan kata kunci <span class="font-semibold text-text-primary" x-text="'&ldquo;' + searchQuery + '&rdquo;'"></span> pada kategori yang dipilih.
                    </p>
                    <button
                        type="button"
                        x-on:click="searchQuery = ''; activeRole = 'all';"
                        class="mt-5 inline-flex min-h-touch items-center justify-center rounded-full bg-forest-600 px-5 py-2 text-label font-bold text-surface transition hover:bg-forest-700"
                    >
                        Tampilkan Semua Tutorial
                    </button>
                </div>

                {{-- Bottom Help Box --}}
                <div class="mt-16 rounded-2xl border border-border bg-gradient-to-r from-success-bg/80 via-surface to-surface p-6 shadow-sm sm:p-8">
                    <div class="grid items-center gap-6 sm:grid-cols-[1fr_auto]">
                        <div>
                            <div class="inline-flex items-center gap-2 text-label font-bold text-forest-700">
                                <x-public.icon name="circle-check" size="size-5" />
                                Masih Butuh Bantuan atau Panduan Lain?
                            </div>
                            <h3 class="mt-2 text-title font-bold text-deep-green">
                                Pengurus Bank Sampah Siap Membantu Anda
                            </h3>
                            <p class="mt-1 text-body-sm text-text-secondary max-w-2xl">
                                Jika Anda mengalami kendala saat login, penimbangan sampah di lapangan, atau membutuhkan panduan langsung, silakan hubungi tim pengelola bank sampah setempat.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex min-h-touch items-center justify-center gap-2 rounded-full bg-forest-600 px-5 text-label font-bold text-surface shadow-xs transition hover:bg-forest-700"
                            >
                                <x-public.icon name="log-in" size="size-4" />
                                Masuk Aplikasi
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
