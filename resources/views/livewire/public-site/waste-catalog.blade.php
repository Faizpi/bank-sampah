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
                title="Jenis sampah yang diterima"
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
                <div class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($wasteTypes as $wasteType)
                        <x-public.waste-card :waste-type="$wasteType" :show-education="true" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
