<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="directory-title">
        <div class="flex items-center gap-4">
            <img src="{{ asset('images/landing/mascot-6.png') }}" alt="Maskot badak membantu menemukan data warga dan pengguna" class="h-20 w-20 shrink-0 object-contain sm:h-24 sm:w-24">
            <div>
        <p class="text-sm font-semibold text-forest-700">Data identitas</p>
        <h2 id="directory-title" class="mt-1 text-2xl font-bold text-deep-green">Satu pintu untuk warga dan pengguna</h2>
        <p class="mt-2 max-w-2xl text-sm text-text-secondary">Pilih sudut pandang yang sesuai. Data tetap memakai izin dan alur kerja masing-masing, tanpa menampilkan dua menu yang membingungkan.</p>
            </div>
        </div>
    </section>

    <nav class="mt-6 overflow-x-auto border-b border-border" aria-label="Bagian direktori">
        <div class="flex min-w-max gap-2 sm:gap-4">
            @if ($canViewCustomers)
                <a href="{{ \App\Filament\Resources\Identity\Models\Customers\CustomerResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-users" class="size-5 shrink-0" aria-hidden="true" /><span>Nasabah</span></a>
            @endif
            @if ($canViewUsers)
                <a href="{{ \App\Filament\Resources\Identity\Models\Users\UserResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-user-circle" class="size-5 shrink-0" aria-hidden="true" /><span>Pengguna &amp; peran</span></a>
            @endif
            @if ($canVerifyCitizens)
                <a href="{{ \App\Filament\Resources\Identity\Models\CitizenVerifications\CitizenVerificationResource::getUrl('index') }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-check-badge" class="size-5 shrink-0" aria-hidden="true" /><span>Verifikasi warga</span></a>
            @endif
        </div>
    </nav>

    <div class="mt-4 flex items-start gap-3 rounded-lg border border-info-200 bg-info-50 px-4 py-3 text-sm leading-6 text-info-950">
        <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
        <p>Gunakan Nasabah untuk identitas, kartu, QR, dan data wilayah. Gunakan Pengguna &amp; peran untuk akun dan akses. Verifikasi warga hanya berisi pendaftaran yang menunggu keputusan.</p>
    </div>
</x-filament-panels::page>
