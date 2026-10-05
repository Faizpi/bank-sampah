<x-filament-panels::page.simple>
    @php
        $demoAccounts = config('app.demo_mode') ? [
            'admin'      => 'Admin',
            'superadmin' => 'Superadmin',
        ] : [];
    @endphp

    {{--
        Override fi-simple-page AND fi-simple-main (its parent) so the card
        can go wide on desktop. fi-simple-main default is max-w-lg (512px).
    --}}
    <style>
        .fi-simple-layout { align-items: stretch !important; padding: 0 !important; background: var(--color-warm-canvas) !important; }
        .fi-simple-layout .fi-simple-main { background: transparent !important; }
        .fi-simple-main   { max-width: none !important; width: 100% !important; padding: 2rem 1.5rem !important; }
        .fi-simple-page   { max-width: 68rem !important; width: 100% !important; margin: auto !important; }
    </style>

    <div class="w-full overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">

            {{-- ── Left: Branding panel ── --}}
            <div class="flex flex-col justify-between gap-6 border-b border-border bg-warm-canvas p-7 sm:border-b-0 sm:border-r sm:p-8">
                <div>
                    <div class="flex items-center gap-3">
                        <img src="{{ asset('images/landing/mascot-3.png') }}" alt="" class="size-10 shrink-0 object-contain" aria-hidden="true">
                        <div>
                            <p class="text-label font-bold leading-tight text-deep-green">Bank Sampah Digital</p>
                            <p class="text-caption font-medium text-text-secondary">Desa Sindangheula</p>
                        </div>
                    </div>

                    <div class="mt-7">
                        <p class="text-caption font-bold uppercase tracking-widest text-forest-600">Backoffice</p>
                        <h1 class="mt-2 text-h2 font-extrabold leading-tight text-deep-green">Panel pengelolaan bank sampah</h1>
                        <p class="mt-3 text-body-sm leading-relaxed text-text-secondary">
                            Akses terbatas untuk admin dan superadmin. Kelola data setoran, penjemputan, warga, dan laporan.
                        </p>
                        <a
                            href="{{ route('home') }}"
                            class="mt-5 inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-surface px-4 py-2.5 text-label font-semibold text-deep-green transition duration-150 hover:border-forest-600 hover:bg-success-bg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-left" class="size-4 shrink-0 text-forest-600" />
                            Kembali ke halaman utama
                        </a>
                    </div>

                    @if ($demoAccounts)
                        <div class="mt-7">
                            <p class="text-caption font-semibold uppercase tracking-wide text-forest-600">Isi cepat akun demo</p>
                            <div class="mt-3 flex flex-col gap-2">
                                @foreach ($demoAccounts as $role => $label)
                                    <button
                                        type="button"
                                        wire:click="fillDemo('{{ $role }}')"
                                        class="group flex min-h-11 items-center justify-between gap-3 rounded-lg border border-border bg-surface px-4 py-2.5 text-left transition duration-150 hover:border-forest-600 hover:bg-success-bg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                    >
                                        <span class="text-label font-semibold text-deep-green">{{ $label }}</span>
                                        <x-filament::icon icon="heroicon-m-arrow-right" class="size-4 shrink-0 text-forest-600 transition duration-150 group-hover:translate-x-0.5" />
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="hidden justify-end sm:flex">
                    <img src="{{ asset('images/landing/mascot-2.png') }}" alt="" class="h-28 w-auto object-contain" aria-hidden="true">
                </div>
            </div>

            {{-- ── Right: Filament form ── --}}
            <div class="flex flex-col justify-center p-7 sm:p-8">
                <p class="mb-5 text-label font-semibold text-text-secondary">Masuk dengan akun Anda</p>
                {{ $this->content }}
            </div>
        </div>
    </div>
</x-filament-panels::page.simple>
