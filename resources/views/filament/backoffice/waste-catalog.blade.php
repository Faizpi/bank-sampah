<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="waste-catalog-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
        <p class="text-sm font-semibold text-forest-700">Data master</p>
        <h2 id="waste-catalog-title" class="mt-1 text-2xl font-bold text-deep-green">Katalog Sampah</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Atur jenis sampah dan harga.</p>
            </div>
            <img src="{{ asset('images/landing/mascot-3.png') }}" alt="Maskot badak memilah kategori dan jenis sampah" class="h-24 w-24 self-end object-contain sm:h-28 sm:w-28 sm:self-auto">
        </div>
    </section>

    <x-backoffice.section-navigation label="Bagian katalog sampah">
        @if ($canViewCategories)
            <x-backoffice.section-link :href="\App\Filament\Resources\WasteMaster\Models\WasteCategories\WasteCategoryResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.waste-master.models.waste-categories.*')" icon="heroicon-o-squares-2x2">
                Kategori
            </x-backoffice.section-link>
        @endif
        @if ($canViewTypes)
            <x-backoffice.section-link :href="\App\Filament\Resources\WasteMaster\Models\WasteTypes\WasteTypeResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.waste-master.models.waste-types.*')" icon="heroicon-o-tag">
                Jenis
            </x-backoffice.section-link>
        @endif
        @if ($canViewConditions)
            <x-backoffice.section-link :href="\App\Filament\Resources\WasteMaster\Models\WasteConditions\WasteConditionResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.waste-master.models.waste-conditions.*')" icon="heroicon-o-clipboard-document-check">
                Kondisi
            </x-backoffice.section-link>
        @endif
        @if ($canViewPrices)
            <x-backoffice.section-link :href="\App\Filament\Resources\WasteMaster\Models\WastePrices\WastePriceResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.waste-master.models.waste-prices.*')" icon="heroicon-o-banknotes">
                Harga
            </x-backoffice.section-link>
        @endif
        @if ($canViewUnits)
            <x-backoffice.section-link :href="\App\Filament\Resources\WasteMaster\Models\WasteUnits\WasteUnitResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.waste-master.models.waste-units.*')" icon="heroicon-o-scale">
                Satuan
            </x-backoffice.section-link>
        @endif
    </x-backoffice.section-navigation>
</x-filament-panels::page>
