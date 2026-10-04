@props([
    'title',
    'context',
    'imageSrc' => null,
    'imageAlt' => null,
    'maskedReference' => null,
    'fallbackNumber',
    'downloadHref' => null,
    'printHref' => null,
    'compact' => false,
    'emptyMessage' => 'QR belum tersedia',
])

@php
    $safeUrl = static function (mixed $url): ?string {
        if (!is_string($url) || $url === '' || preg_match('/[\x00-\x20\x7F]/', $url) === 1 || preg_match('/%(?![0-9A-Fa-f]{2})/', $url) === 1 || str_contains($url, '\\')) return null;
        $decoded = rawurldecode($url);
        if (preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1 || preg_match('/^\s/', $decoded) === 1 || str_contains($decoded, '\\') || str_starts_with($url, '//') || str_starts_with($decoded, '//')) return null;
        foreach ([$url, $decoded] as $candidate) {
            if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $candidate) === 1) {
                $parts = parse_url($candidate);
                if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true) || $parts['host'] === '') return null;
            } elseif (str_contains($candidate, '://') || str_starts_with($candidate, ':')) return null;
        }
        return $url;
    };
    // QR presenters generate a local base64 image. Keep the general URL guard
    // for remote sources, while allowing only strict, non-scriptable image data.
    $safeImage = is_string($imageSrc) && preg_match('/^data:image\/(?:png|jpe?g|webp|gif|svg\+xml);base64,[A-Za-z0-9+\/]+={0,2}$/', $imageSrc) === 1
        ? $imageSrc
        : $safeUrl($imageSrc);
    $download = $safeUrl($downloadHref);
    $print = $safeUrl($printHref);
    $reference = is_string($maskedReference) && str_contains($maskedReference, '*') ? $maskedReference : null;
@endphp

<section {{ $attributes->except(['image-markup'])->class($compact ? 'min-w-0 rounded-lg border-0 bg-transparent p-0' : 'min-w-0 rounded-xl border border-border bg-surface p-4 sm:p-6') }}>
    <div class="text-center">
        <div class="mx-auto mb-3 flex size-10 items-center justify-center rounded-xl bg-success-bg">
            <svg viewBox="0 0 24 24" class="size-5 text-forest-600" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
                <path d="M14 14h.01M14 17h3M17 14v3M20 14h.01M20 20h.01"/>
            </svg>
        </div>
        <h2 class="text-title font-bold text-deep-green">{{ $title }}</h2>
        <p class="mx-auto mt-2 max-w-lg text-body-sm text-text-secondary">{{ $context }}</p>
    </div>

    <div class="mx-auto mt-4 flex w-fit max-w-full items-center justify-center overflow-hidden rounded-md bg-warm-canvas p-3">
        @if ($safeImage)
            <img src="{{ $safeImage }}" alt="{{ $imageAlt ?: $title }}" width="200" height="200" class="size-[200px] max-w-full object-contain">
        @else
            <div role="status" class="flex size-[200px] max-w-full items-center justify-center border border-border bg-disabled-bg p-4 text-center text-body-sm text-text-secondary">{{ $emptyMessage }}</div>
        @endif
    </div>

    <dl class="mx-auto mt-4 max-w-lg border-y border-border py-3 text-body-sm">
        @if (is_string($maskedReference) && str_contains($maskedReference, '*'))<div class="flex flex-wrap justify-between gap-2"><dt class="text-text-secondary">Referensi tersamarkan</dt><dd class="amount-tabular text-deep-green">{{ $maskedReference }}</dd></div>@endif
        <div class="mt-2 flex flex-wrap justify-between gap-2"><dt class="text-text-secondary">Nomor alternatif</dt><dd class="amount-tabular text-deep-green">{{ $fallbackNumber }}</dd></div>
    </dl>

    <p class="mx-auto mt-3 max-w-lg text-body-sm text-text-secondary">Jika pemindaian gagal, sebutkan nomor alternatif kepada petugas. Pastikan seluruh kotak putih terlihat saat dipindai.</p>

    @if ($download || $print)
        <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-center">
            @if ($download)
                <a href="{{ $download }}" download aria-label="Unduh {{ $title }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-border bg-surface px-5 text-label text-deep-green hover:border-forest-600 hover:bg-success-bg">
                    <svg data-lucide="download" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M5 21h14"/></svg>
                    Unduh
                </a>
            @endif
            @if ($print)
                <a href="{{ $print }}" aria-label="Cetak {{ $title }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-forest-600 px-5 text-label text-white hover:bg-forest-700">
                    <svg data-lucide="printer" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/></svg>
                    Cetak
                </a>
            @endif
        </div>
    @endif
</section>
