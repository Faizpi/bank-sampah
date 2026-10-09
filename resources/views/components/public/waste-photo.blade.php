@props(['wasteType'])
@php($photoUrl = app(\App\Domain\WasteMaster\Queries\PublicWasteCatalog::class)->photoUrl($wasteType))
<div x-data="{ photoFailed: false }" {{ $attributes->class(['relative overflow-hidden bg-warm-canvas']) }}>
    @if ($photoUrl)
        <img x-show="!photoFailed" x-on:error="photoFailed = true" src="{{ $photoUrl }}" alt="Foto {{ $wasteType->name }}" loading="lazy" decoding="async" width="800" height="600" class="h-full w-full object-cover">
        <div x-cloak x-show="photoFailed" class="flex h-full min-h-24 items-center justify-center px-3 py-4 text-center text-caption text-text-secondary">Foto belum dapat dimuat</div>
    @else
        <div class="flex h-full min-h-24 items-center justify-center px-3 py-4 text-center text-caption text-text-secondary">
            <span>Foto belum diunggah</span>
        </div>
    @endif
</div>
