<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="waste-catalog-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
        <p class="text-sm font-semibold text-forest-700">Data master</p>
        <h2 id="waste-catalog-title" class="mt-1 text-2xl font-bold text-deep-green">Katalog Sampah</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Kelola kategori, jenis, kondisi, satuan, dan harga dari satu area yang mudah ditemukan.</p>
            </div>
            <img src="{{ asset('images/landing/mascot-3.png') }}" alt="Maskot badak memilah kategori dan jenis sampah" class="h-24 w-24 self-end object-contain sm:h-28 sm:w-28 sm:self-auto">
        </div>
    </section>

    <nav class="mt-6 overflow-x-auto border-b border-border" aria-label="Bagian katalog sampah">
        <div class="flex min-w-max gap-2 sm:gap-4">
            @if ($canViewCategories)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteCategories\WasteCategoryResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-squares-2x2" class="size-5 shrink-0" aria-hidden="true" /><span>Kategori</span></a>@endif
            @if ($canViewTypes)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteTypes\WasteTypeResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-tag" class="size-5 shrink-0" aria-hidden="true" /><span>Jenis</span></a>@endif
            @if ($canViewConditions)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteConditions\WasteConditionResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-clipboard-document-check" class="size-5 shrink-0" aria-hidden="true" /><span>Kondisi</span></a>@endif
            @if ($canViewPrices)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WastePrices\WastePriceResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-banknotes" class="size-5 shrink-0" aria-hidden="true" /><span>Harga</span></a>@endif
            @if ($canViewUnits)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteUnits\WasteUnitResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-scale" class="size-5 shrink-0" aria-hidden="true" /><span>Satuan</span></a>@endif
        </div>
    </nav>
</x-filament-panels::page>
