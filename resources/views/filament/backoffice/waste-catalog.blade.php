<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="waste-catalog-title">
        <p class="text-sm font-semibold text-forest-700">Data master</p>
        <h2 id="waste-catalog-title" class="mt-1 text-2xl font-bold text-deep-green">Katalog Sampah</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Kelola kategori, jenis, kondisi, satuan, dan harga dari satu area yang mudah ditemukan.</p>
    </section>

    <nav class="mt-6 overflow-x-auto border-b border-gray-200" aria-label="Bagian katalog sampah">
        <div class="flex min-w-max gap-6">
            @if ($canViewCategories)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteCategories\WasteCategoryResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Kategori</a>@endif
            @if ($canViewTypes)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteTypes\WasteTypeResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Jenis</a>@endif
            @if ($canViewConditions)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteConditions\WasteConditionResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Kondisi</a>@endif
            @if ($canViewPrices)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WastePrices\WastePriceResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Harga</a>@endif
            @if ($canViewUnits)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteUnits\WasteUnitResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Satuan</a>@endif
        </div>
    </nav>
</x-filament-panels::page>
