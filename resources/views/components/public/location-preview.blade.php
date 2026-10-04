@php
    $address = 'Desa Sindangheula, Kecamatan Pabuaran, Kabupaten Serang, Provinsi Banten.';
    $mapEmbedUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=106.09638888889%2C-6.203611111111%2C106.13638888889%2C-6.163611111111&layer=mapnik&marker=-6.183611111111%2C106.11638888889';
    $mapUrl = 'https://www.openstreetmap.org/#map=14/-6.183611111111/106.11638888889';
@endphp

<section aria-labelledby="footer-location-heading" class="mt-8">
    <h2 id="footer-location-heading" class="text-label font-bold uppercase tracking-wide text-surface">Lokasi layanan</h2>
    <p class="mt-2 text-body-sm leading-6 text-success-bg">{{ $address }}</p>

    <figure class="mt-4 overflow-hidden rounded-lg border border-success-bg/20 bg-deep-green">
        <div class="aspect-video w-full bg-surface">
            <iframe
                src="{{ $mapEmbedUrl }}"
                title="Peta OpenStreetMap area Desa Sindangheula"
                loading="lazy"
                class="block size-full border-0"
            ></iframe>
        </div>
        <figcaption class="border-t border-success-bg/20 px-3 py-3">
            <a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-touch items-center gap-2 rounded-md text-body-sm font-bold text-surface underline decoration-harvest-gold decoration-2 underline-offset-4 focus-visible:outline-offset-4">
                Buka peta Desa Sindangheula
                <x-public.icon name="arrow-right" size="size-4" />
                <span class="sr-only"> (terbuka di tab baru)</span>
            </a>
        </figcaption>
    </figure>
</section>
