<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="technical-overview-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
        <p class="text-sm font-semibold text-forest-700">Administrasi sistem</p>
        <h2 id="technical-overview-title" class="mt-1 text-2xl font-bold text-deep-green">Health sistem</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Periksa kondisi aplikasi dan layanan yang terhubung.</p>
            </div>
            <img src="{{ asset('images/landing/mascot-10.png') }}" alt="Maskot badak memeriksa kesehatan dan keamanan sistem" class="h-24 w-24 self-end object-contain sm:h-28 sm:w-28 sm:self-auto">
        </div>
    </section>

    <x-backoffice.section-navigation label="Administrasi sistem">
        <x-backoffice.section-link :href="\App\Filament\Pages\TechnicalHealthPage::getUrl()" :active="request()->routeIs('filament.backoffice.pages.technical-health-page')" icon="heroicon-o-heart">
            Health
        </x-backoffice.section-link>
    </x-backoffice.section-navigation>


</x-filament-panels::page>
