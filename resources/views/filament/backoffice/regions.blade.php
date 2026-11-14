<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="regions-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
        <p class="text-sm font-semibold text-forest-700">Data master</p>
        <h2 id="regions-title" class="mt-1 text-2xl font-bold text-deep-green">Wilayah</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Atur wilayah layanan dan penugasan.</p>
            </div>
            <img src="{{ asset('images/landing/mascot-4.png') }}" alt="Maskot badak menata wilayah layanan dan cakupan area" class="h-24 w-24 self-end object-contain sm:h-28 sm:w-28 sm:self-auto">
        </div>
    </section>

    <x-backoffice.section-navigation label="Bagian wilayah">
        @if ($canViewAreas)
            <x-backoffice.section-link :href="\App\Filament\Resources\CustomersRegions\Models\ServiceAreas\ServiceAreaResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.customers-regions.models.service-areas.*')" icon="heroicon-o-map">
                Area pelayanan
            </x-backoffice.section-link>
        @endif
        @if ($canViewDusuns)
            <x-backoffice.section-link :href="\App\Filament\Resources\CustomersRegions\Models\Dusuns\DusunResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.customers-regions.models.dusuns.*')" icon="heroicon-o-home-modern">
                Dusun
            </x-backoffice.section-link>
        @endif
        @if ($canViewRws)
            <x-backoffice.section-link :href="\App\Filament\Resources\CustomersRegions\Models\Rws\RwResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.customers-regions.models.rws.*')" icon="heroicon-o-building-office-2">
                RW
            </x-backoffice.section-link>
        @endif
        @if ($canViewRts)
            <x-backoffice.section-link :href="\App\Filament\Resources\CustomersRegions\Models\Rts\RtResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.customers-regions.models.rts.*')" icon="heroicon-o-map-pin">
                RT
            </x-backoffice.section-link>
        @endif
    </x-backoffice.section-navigation>
</x-filament-panels::page>
