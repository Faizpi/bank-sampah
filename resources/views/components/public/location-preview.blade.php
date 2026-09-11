@php
    $address = 'Lokasi dan jam layanan ditentukan oleh pengelola bank sampah setempat.';
    $mapUrl = 'https://www.openstreetmap.org/';
@endphp

<section aria-labelledby="footer-location-heading" class="mt-8">
    <h2 id="footer-location-heading" class="text-label font-bold uppercase tracking-wide text-surface">Lokasi layanan</h2>
    <p class="mt-2 text-body-sm leading-6 text-success-bg">{{ $address }}</p>

    <figure class="mt-4 overflow-hidden rounded-lg border border-success-bg/20 bg-deep-green">
        <figcaption class="px-3 py-3">
            <a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-touch items-center gap-2 rounded-md text-body-sm font-bold text-surface underline decoration-harvest-gold decoration-2 underline-offset-4 focus-visible:outline-offset-4">
                Buka peta layanan
                <x-public.icon name="arrow-right" size="size-4" />
                <span class="sr-only"> (terbuka di tab baru)</span>
            </a>
        </figcaption>
    </figure>
</section>
