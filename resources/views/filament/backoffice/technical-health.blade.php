<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="technical-health-title">
        <p class="text-sm font-semibold text-forest-700">Kontrol teknis</p>
        <h2 id="technical-health-title" class="mt-1 text-2xl font-bold text-deep-green">Kondisi sistem</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Kondisi aplikasi ditampilkan tanpa membocorkan lokasi berkas, data internal, atau rahasia.</p>
    </section>

    @if ($health === [])
        <p class="mt-6 rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-600">Izin pemeriksaan sistem tidak tersedia.</p>
    @else
        <section class="mt-6 w-full min-w-0 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="health-checks-title">
            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <h3 id="health-checks-title" class="min-w-0 text-lg font-semibold text-gray-950">Pemeriksaan aktif</h3>
                <a href="{{ route('operations.health') }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-gray-300 px-4 text-center text-sm font-medium text-gray-800 [overflow-wrap:anywhere] hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 sm:w-auto">Lihat detail kondisi</a>
            </div>
            <dl class="mt-4 grid w-full min-w-0 grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))] gap-3">
                @foreach ($health as $name => $check)
                    <div class="min-w-0 rounded-lg border border-gray-200 p-3 [overflow-wrap:anywhere]">
                        <dt class="text-sm font-semibold capitalize text-gray-900">{{ str_replace('_', ' ', $name) }}</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-700">{{ $check['status'] ?? 'Tidak diketahui' }}</dd>
                        @if (isset($check['reason']))<dd class="mt-1 text-xs text-gray-500">{{ $check['reason'] }}</dd>@endif
                    </div>
                @endforeach
            </dl>
        </section>
    @endif
</x-filament-panels::page>
