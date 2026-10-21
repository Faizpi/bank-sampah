<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="directory-title">
        <div class="flex min-w-0 flex-col items-start gap-4 sm:flex-row sm:items-center">
            <img src="{{ asset('images/landing/mascot-6.png') }}" alt="Maskot badak membantu menemukan data warga dan pengguna" class="h-20 w-20 shrink-0 object-contain sm:h-24 sm:w-24">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-forest-700">Data identitas</p>
                <h2 id="directory-title" class="mt-1 text-2xl font-bold text-deep-green [overflow-wrap:anywhere]">Satu pintu untuk warga dan pengguna</h2>
                <p class="mt-2 max-w-2xl text-sm text-text-secondary [overflow-wrap:anywhere]">Pilih sudut pandang yang sesuai. Data tetap memakai izin dan alur kerja masing-masing, tanpa menampilkan dua menu yang membingungkan.</p>
            </div>
        </div>
    </section>

    <x-backoffice.section-navigation label="Bagian direktori">
        @if ($canViewCustomers)
            <x-backoffice.section-link :href="\App\Filament\Resources\Identity\Models\Customers\CustomerResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.identity.models.customers.*')" icon="heroicon-o-users">
                Nasabah
            </x-backoffice.section-link>
        @endif
        @if ($canViewUsers)
            <x-backoffice.section-link :href="\App\Filament\Resources\Identity\Models\Users\UserResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.identity.models.users.*')" icon="heroicon-o-user-circle">
                Pengguna &amp; peran
            </x-backoffice.section-link>
        @endif
        @if ($canVerifyCitizens)
            <x-backoffice.section-link :href="\App\Filament\Resources\Identity\Models\CitizenVerifications\CitizenVerificationResource::getUrl('index')" :active="request()->routeIs('filament.backoffice.resources.identity.models.citizen-verifications.*')" icon="heroicon-o-check-badge">
                Verifikasi warga
            </x-backoffice.section-link>
        @endif
    </x-backoffice.section-navigation>

    <div class="mt-4 flex items-start gap-3 rounded-lg border border-info-200 bg-info-50 px-4 py-3 text-sm leading-6 text-info-950">
        <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
        <p>Gunakan Nasabah untuk identitas, kartu, QR, dan data wilayah. Gunakan Pengguna &amp; peran untuk akun dan akses. Verifikasi warga hanya berisi pendaftaran yang menunggu keputusan.</p>
    </div>
</x-filament-panels::page>
