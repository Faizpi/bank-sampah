<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="directory-title">
        <p class="text-sm font-semibold text-forest-700">Data identitas</p>
        <h2 id="directory-title" class="mt-1 text-2xl font-bold text-deep-green">Satu pintu untuk warga dan pengguna</h2>
        <p class="mt-2 max-w-2xl text-sm text-text-secondary">Pilih sudut pandang yang sesuai. Data tetap memakai izin dan alur kerja masing-masing, tanpa menampilkan dua menu yang membingungkan.</p>
    </section>

    <nav class="mt-6 overflow-x-auto border-b border-gray-200" aria-label="Bagian direktori">
        <div class="flex min-w-max gap-6">
            @if ($canViewCustomers)
                <a href="{{ \App\Filament\Resources\Identity\Models\Customers\CustomerResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Nasabah</a>
            @endif
            @if ($canViewUsers)
                <a href="{{ \App\Filament\Resources\Identity\Models\Users\UserResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Pengguna &amp; peran</a>
            @endif
            @if ($canVerifyCitizens)
                <a href="{{ \App\Filament\Resources\Identity\Models\CitizenVerifications\CitizenVerificationResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Verifikasi warga</a>
            @endif
        </div>
    </nav>

    <p class="mt-4 max-w-2xl text-sm leading-6 text-gray-600">Gunakan Nasabah untuk identitas, kartu, QR, dan data wilayah. Gunakan Pengguna &amp; peran untuk akun dan akses. Verifikasi warga hanya berisi pendaftaran yang menunggu keputusan.</p>
</x-filament-panels::page>
