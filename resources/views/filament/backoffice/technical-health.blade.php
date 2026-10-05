<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="technical-health-title">
        <p class="text-sm font-semibold text-forest-700">Kontrol teknis</p>
        <h2 id="technical-health-title" class="mt-1 text-2xl font-bold text-deep-green">Health sistem</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Ringkasan kondisi aplikasi tanpa menampilkan path, payload, checksum, atau rahasia.</p>
    </section>

    @if ($health === [])
        <p class="mt-6 rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-600">Izin pemeriksaan sistem tidak tersedia.</p>
    @else
        <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="health-checks-title">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <h3 id="health-checks-title" class="text-lg font-semibold text-gray-950">Pemeriksaan aktif</h3>
                <a href="{{ route('operations.health') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-800 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600">Lihat detail kondisi</a>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($health as $name => $check)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-sm font-semibold capitalize text-gray-900">{{ str_replace('_', ' ', $name) }}</div>
                        <div class="mt-1 text-sm font-medium text-gray-700">{{ $check['status'] ?? 'Tidak diketahui' }}</div>
                        @if (isset($check['reason']))<div class="mt-1 text-xs text-gray-500">{{ $check['reason'] }}</div>@endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-filament-panels::page>
