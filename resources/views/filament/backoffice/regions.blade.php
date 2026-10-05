<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="regions-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
        <p class="text-sm font-semibold text-forest-700">Data master</p>
        <h2 id="regions-title" class="mt-1 text-2xl font-bold text-deep-green">Wilayah</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Kelola area, dusun, RW, dan RT di satu tempat agar penugasan dan wilayah layanan tetap konsisten.</p>
            </div>
            <img src="{{ asset('images/landing/mascot-4.png') }}" alt="Maskot badak menata wilayah layanan dan cakupan area" class="h-24 w-24 self-end object-contain sm:h-28 sm:w-28 sm:self-auto">
        </div>
    </section>

    <nav class="mt-6 overflow-x-auto border-b border-border" aria-label="Bagian wilayah">
        <div class="flex min-w-max gap-2 sm:gap-4">
            @if ($canViewAreas)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\ServiceAreas\ServiceAreaResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-map" class="size-5 shrink-0" aria-hidden="true" /><span>Area pelayanan</span></a>@endif
            @if ($canViewDusuns)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\Dusuns\DusunResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-home-modern" class="size-5 shrink-0" aria-hidden="true" /><span>Dusun</span></a>@endif
            @if ($canViewRws)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\Rws\RwResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-building-office-2" class="size-5 shrink-0" aria-hidden="true" /><span>RW</span></a>@endif
            @if ($canViewRts)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\Rts\RtResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-map-pin" class="size-5 shrink-0" aria-hidden="true" /><span>RT</span></a>@endif
        </div>
    </nav>
</x-filament-panels::page>
