@section('title', 'Harga Sampah Aktif')
@section('description', 'Lihat harga sampah aktif per kondisi dan satuan di Bank Sampah Digital Sindangheula.')

<div class="public-canvas">
    <section class="border-b border-deep-green bg-deep-green text-surface" aria-labelledby="prices-page-title">
        <div class="public-container grid items-center gap-8 section-space lg:grid-cols-[1fr_auto]">
            <div>
            <p class="text-label font-semibold tracking-wide text-surface/80">Harga transparan</p>
            <h1 id="prices-page-title" class="mt-2 max-w-3xl text-h1 lg:text-h1-lg text-surface">Harga aktif yang bisa diperiksa.</h1>
            <p class="mt-3 max-w-2xl text-body text-surface/80">Harga mengikuti jenis, kondisi, dan waktu transaksi. Nilai akhir tetap mengikuti berat aktual saat setoran.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('public.catalog') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:bg-success-bg active:translate-y-px">
                    Pelajari jenis sampah
                    <x-public.icon name="arrow-right" size="size-5" />
                </a>
                <a href="{{ route('register') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-surface/40 px-5 text-label text-surface transition duration-180 ease-standard hover:border-surface hover:bg-surface/10 active:translate-y-px">
                    Daftar untuk mulai menyetor
                </a>
            </div>
            </div>
            <img src="{{ asset('images/landing/mascot-7.png') }}" alt="Maskot badak menimbang sampah dengan timbangan" class="mx-auto h-40 w-44 object-contain lg:h-48 lg:w-52">
        </div>
    </section>

    <section class="public-section-canvas border-b border-border">
        <div class="public-container section-space">
            <x-public.section-header
                id="prices-title"
                eyebrow="Periode berjalan"
                title="Daftar harga penerimaan"
                description="Gunakan informasi ini sebagai acuan sebelum setoran. Harga transaksi final mengikuti berat aktual dan periode yang berlaku."
            >
                <x-slot:actions>
                    <a href="{{ route('public.catalog') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-border bg-surface px-4 text-label text-deep-green transition duration-180 ease-standard hover:border-forest-600 hover:bg-success-bg active:translate-y-px">
                        Buka katalog edukasi
                        <x-public.icon name="book-open" size="size-5" />
                    </a>
                </x-slot:actions>
            </x-public.section-header>

            @if ($prices->isEmpty())
                <x-ui.empty-state
                    class="mt-8"
                    title="Harga belum tersedia"
                    description="Belum ada harga aktif yang dapat ditampilkan saat ini."
                    action-label="Buka katalog edukasi"
                    action-href="{{ route('public.catalog') }}"
                />
            @else
                <div class="mt-10 overflow-hidden rounded-lg border border-border bg-surface">
                    <div class="divide-y divide-border">
                        @foreach ($prices as $price)
                            <article class="grid gap-5 p-5 sm:grid-cols-[1fr_auto] sm:items-center sm:p-6">
                                <div class="flex min-w-0 gap-3">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-md bg-success-bg text-forest-700" aria-hidden="true">
                                        <x-public.icon name="banknote" size="size-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-label font-semibold text-forest-600">{{ $price->wasteType->category->name }}</p>
                                        <h3 class="mt-1 text-h3 text-deep-green">{{ $price->wasteType->name }}</h3>
                                        <p class="mt-2 text-body-sm text-text-secondary">{{ $price->condition->name }} · {{ $price->wasteType->unit->name }} ({{ $price->wasteType->unit->symbol }})</p>
                                    </div>
                                </div>
                                <div class="sm:text-right">
                                    <p class="text-amount tabular-nums text-deep-green">Rp{{ number_format($price->price, 0, ',', '.') }}</p>
                                    <p class="mt-1 text-body-sm text-text-secondary">per {{ $price->wasteType->unit->symbol }}</p>
                                    <p class="mt-2 text-caption text-text-secondary">Mulai {{ $price->effective_from->format('d M Y, H:i') }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif

            <p class="mt-6 max-w-3xl text-body-sm leading-6 text-text-secondary">Harga publik adalah informasi aktif. Harga yang tercatat pada transaksi final disimpan sebagai rekaman saat transaksi dan tidak berubah ketika periode baru dibuat.</p>
        </div>
    </section>
</div>
