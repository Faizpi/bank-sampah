@section('title', 'Jadwal Bank Sampah Keliling')
@section('description', 'Jadwal layanan keliling Bank Sampah Digital Desa Sindangheula.')

<div class="public-canvas">
    <section class="border-b border-deep-green bg-deep-green text-surface" aria-labelledby="mobile-schedule-page-title">
        <div class="public-container grid items-center gap-8 section-space lg:grid-cols-[1fr_auto]">
            <div>
            <p class="text-label font-semibold tracking-wide text-surface/80">Layanan per wilayah</p>
            <h1 id="mobile-schedule-page-title" class="mt-2 max-w-3xl text-h1 lg:text-h1-lg text-surface">Jadwal bank sampah keliling.</h1>
            <p class="mt-3 max-w-2xl text-body text-surface/80">Datang ke titik layanan pada waktu yang tertera. Layanan keliling bukan penjemputan rumah.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('register') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:bg-success-bg active:translate-y-px">
                    Daftar untuk menggunakan layanan
                    <x-public.icon name="arrow-right" size="size-5" />
                </a>
                <a href="{{ route('home') }}#layanan" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-surface/40 px-5 text-label text-surface transition duration-180 ease-standard hover:border-surface hover:bg-surface/10 active:translate-y-px">
                    Lihat layanan lain
                </a>
            </div>
            </div>
            <img src="{{ asset('images/landing/mascot-4.png') }}" alt="Maskot badak membawa kantong sampah terpilah menuju layanan keliling" class="mx-auto h-40 w-44 object-contain lg:h-48 lg:w-52">
        </div>
    </section>

    <section class="public-section-canvas">
        <div class="public-container section-space">
            <x-public.section-header
                id="mobile-schedule-title"
                eyebrow="Layanan tersedia"
                title="Jadwal aktif"
                description="Pilih waktu dan titik layanan yang sesuai. Kapasitas yang tampil adalah sisa layanan pada jadwal tersebut."
            />

            @if ($services->isEmpty())
                <x-ui.empty-state
                    class="mt-8"
                    title="Belum ada jadwal aktif"
                    description="Jadwal layanan keliling akan ditampilkan setelah diterbitkan."
                    action-label="Lihat layanan lain"
                    action-href="{{ route('home') }}#layanan"
                />
            @else
                <div class="mt-10 grid gap-4 sm:grid-cols-2">
                    @foreach ($services as $service)
                        @php
                            $status = $service->status->value;
                            $statusPresentation = match ($status) {
                                'dipublikasikan' => 'pending',
                                'dibuka' => 'success',
                                'ditutup' => 'closed',
                                'dibatalkan' => 'cancelled',
                                default => 'pending',
                            };
                            $statusLabel = match ($status) {
                                'dipublikasikan' => 'Terjadwal',
                                'dibuka' => 'Dibuka',
                                'ditutup' => 'Ditutup',
                                'dibatalkan' => 'Dibatalkan',
                                default => 'Status belum ditentukan',
                            };
                        @endphp
                        <article class="rounded-lg border border-border bg-surface p-5 md:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex min-w-0 gap-3">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-md bg-info-bg text-sky-blue" aria-hidden="true">
                                        <x-public.icon name="map-pin" size="size-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-body-sm font-semibold text-forest-600">{{ $service->starts_at->format('d M Y') }}</p>
                                        <h3 class="mt-1 text-h3 text-deep-green">{{ $service->point }}</h3>
                                    </div>
                                </div>
                                <x-ui.status-badge :status="$statusPresentation">{{ $statusLabel }}</x-ui.status-badge>
                            </div>

                            <dl class="mt-6 grid gap-3 border-t border-border pt-4 text-body-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="flex items-center gap-2 text-text-secondary"><x-public.icon name="clock-3" size="size-4" />Waktu</dt>
                                    <dd class="font-semibold text-text-primary">{{ $service->starts_at->format('H:i') }}–{{ $service->ends_at->format('H:i') }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="flex items-center gap-2 text-text-secondary"><x-public.icon name="scale" size="size-4" />Kapasitas</dt>
                                    <dd class="font-semibold text-text-primary">{{ $service->capacity - $service->served_count }} tersisa</dd>
                                </div>
                                <div class="grid gap-1">
                                    <dt class="text-text-secondary">Wilayah layanan</dt>
                                    <dd class="font-semibold text-text-primary">{{ $service->rt?->name ?? $service->rw?->name ?? 'Wilayah layanan' }}</dd>
                                </div>
                                <div class="grid gap-1">
                                    <dt class="text-text-secondary">Sampah yang diterima</dt>
                                    <dd class="font-semibold text-text-primary">{{ $service->wasteTypes->pluck('name')->implode(', ') }}</dd>
                                </div>
                                @if ($service->notes !== null)
                                    <div class="grid gap-1">
                                        <dt class="text-text-secondary">Catatan layanan</dt>
                                        <dd class="text-text-primary">{{ $service->notes }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
