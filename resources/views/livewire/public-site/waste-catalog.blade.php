@section('title', 'Katalog Sampah dan Edukasi')
@section('description', 'Pelajari jenis sampah yang diterima, satuan, kondisi, dan panduan pemilahannya di Bank Sampah Digital Sindangheula.')

<div class="public-canvas">
    <section class="border-b border-deep-green bg-deep-green text-surface" aria-labelledby="catalog-page-title">
        <div class="public-container grid items-center gap-8 section-space lg:grid-cols-[1fr_auto]">
            <div>
            <p class="text-label font-semibold tracking-wide text-surface/80">Katalog dan edukasi</p>
            <h1 id="catalog-page-title" class="mt-2 max-w-3xl text-h1 lg:text-h1-lg text-surface">Kenali sampah sebelum disetor.</h1>
            <p class="mt-3 max-w-2xl text-body text-surface/80">Pilah berdasarkan jenis dan kondisi yang diterima agar setoran lebih mudah diperiksa oleh petugas.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('public.prices') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:bg-success-bg active:translate-y-px">
                    Lihat harga aktif
                    <x-public.icon name="arrow-right" size="size-5" />
                </a>
                <a href="{{ route('register') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-surface/40 px-5 text-label text-surface transition duration-180 ease-standard hover:border-surface hover:bg-surface/10 active:translate-y-px">
                    Daftar untuk mulai menyetor
                </a>
            </div>
            </div>
            <img src="{{ asset('images/landing/mascot-3.png') }}" alt="Maskot badak memilah botol dan kertas untuk mengenali jenis sampah" class="mx-auto h-40 w-44 object-contain lg:h-48 lg:w-52">
        </div>
    </section>

    <section class="public-section-canvas border-b border-border">
        <div class="public-container section-space">
            <x-public.section-header
                id="catalog-title"
                eyebrow="Jenis aktif"
                title="Yang dapat dipelajari dan disiapkan"
                description="Periksa satuan, kondisi yang diterima, dan panduan pemilahan sebelum membawa setoran."
            >
                <x-slot:actions>
                    <a href="{{ route('public.prices') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-border bg-surface px-4 text-label text-deep-green transition duration-180 ease-standard hover:border-forest-600 hover:bg-success-bg active:translate-y-px">
                        Lihat harga aktif
                        <x-public.icon name="arrow-right" size="size-5" />
                    </a>
                </x-slot:actions>
            </x-public.section-header>

            @if ($wasteTypes->isEmpty())
                <x-ui.empty-state
                    class="mt-8"
                    title="Katalog sedang disiapkan"
                    description="Belum ada jenis sampah aktif yang dapat ditampilkan."
                    action-label="Lihat harga aktif"
                    action-href="{{ route('public.prices') }}"
                />
            @else
                <div class="mt-10 grid gap-4 md:grid-cols-2">
                    @foreach ($wasteTypes as $wasteType)
                        <article class="rounded-lg border border-border bg-surface p-5 md:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex min-w-0 gap-3">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-md bg-success-bg text-forest-700" aria-hidden="true">
                                        <x-public.icon name="recycle" size="size-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-label font-semibold text-forest-600">{{ $wasteType->category->name }}</p>
                                        <h3 class="mt-1 text-h3 text-deep-green">{{ $wasteType->name }}</h3>
                                        <p class="mt-1 text-body-sm text-text-secondary">Kode {{ $wasteType->code }}</p>
                                    </div>
                                </div>
                                @if ($wasteType->is_plastic)
                                    <span class="inline-flex min-h-7 shrink-0 items-center gap-1.5 rounded-sm bg-info-bg px-2.5 py-1 text-caption font-semibold text-sky-blue">
                                        <x-public.icon name="recycle" size="size-4" />
                                        Plastik
                                    </span>
                                @endif
                            </div>

                            <dl class="mt-6 grid grid-cols-2 gap-4 border-y border-border py-4 text-body-sm">
                                <div>
                                    <dt class="text-text-secondary">Satuan</dt>
                                    <dd class="mt-1 font-semibold text-deep-green">{{ $wasteType->unit->name }} ({{ $wasteType->unit->symbol }})</dd>
                                </div>
                                <div>
                                    <dt class="text-text-secondary">Kondisi diterima</dt>
                                    <dd class="mt-1 font-semibold text-deep-green">{{ $wasteType->conditions->pluck('name')->join(', ') }}</dd>
                                </div>
                            </dl>

                            @if ($wasteType->education_description)
                                <div class="mt-5 border-t border-border pt-4">
                                    <div class="flex items-center gap-2 text-label font-semibold text-forest-700">
                                        <x-public.icon name="book-open" size="size-5" />
                                        <span>Panduan pemilahan</span>
                                    </div>
                                    <p class="mt-2 text-body text-text-secondary">{{ $wasteType->education_description }}</p>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
