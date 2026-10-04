<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="regions-title">
        <p class="text-sm font-semibold text-forest-700">Data master</p>
        <h2 id="regions-title" class="mt-1 text-2xl font-bold text-deep-green">Wilayah</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Kelola struktur area, dusun, RW, dan RT dari satu pintu agar penugasan dan cakupan layanan tetap konsisten.</p>
    </section>

    <nav class="mt-6 overflow-x-auto border-b border-gray-200" aria-label="Bagian wilayah">
        <div class="flex min-w-max gap-6">
            @if ($canViewAreas)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\ServiceAreas\ServiceAreaResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Area pelayanan</a>@endif
            @if ($canViewDusuns)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\Dusuns\DusunResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Dusun</a>@endif
            @if ($canViewRws)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\Rws\RwResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">RW</a>@endif
            @if ($canViewRts)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\Rts\RtResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">RT</a>@endif
        </div>
    </nav>
</x-filament-panels::page>
