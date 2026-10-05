<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="technical-overview-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
        <p class="text-sm font-semibold text-forest-700">Administrasi sistem</p>
        <h2 id="technical-overview-title" class="mt-1 text-2xl font-bold text-deep-green">Health sistem</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Lihat ringkasan kondisi teknis aplikasi.</p>
            </div>
            <img src="{{ asset('images/landing/mascot-10.png') }}" alt="Maskot badak memeriksa kesehatan dan keamanan sistem" class="h-24 w-24 self-end object-contain sm:h-28 sm:w-28 sm:self-auto">
        </div>
    </section>

    <nav class="mt-6 overflow-x-auto border-b border-border" aria-label="Administrasi sistem">
        <div class="flex min-w-max gap-2 sm:gap-4">
            <a href="{{ \App\Filament\Pages\TechnicalHealthPage::getUrl() }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-heart" class="size-5 shrink-0" aria-hidden="true" /><span>Health</span></a>
        </div>
    </nav>

    <p class="mt-4 max-w-2xl text-sm leading-6 text-gray-600">Health menyajikan informasi kondisi aplikasi secara baca-saja.</p>
</x-filament-panels::page>
