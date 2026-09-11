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

    <p id="directory-navigation-help" class="mt-6 text-sm leading-6 text-text-secondary">Gunakan tombol Tab untuk memfokuskan navigasi, lalu geser secara horizontal bila seluruh bagian belum terlihat.</p>
    <nav class="mt-2 overflow-x-auto border-b border-border focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2" aria-label="Bagian direktori" aria-describedby="directory-navigation-help" tabindex="0">
        <div class="flex min-w-max gap-2 sm:gap-4">
            @if ($canViewCustomers)
                <a href="{{ \App\Filament\Resources\Identity\Models\Customers\CustomerResource::getUrl('index') }}" @class(['inline-flex min-h-12 items-center gap-2 border-b-2 px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2', 'border-primary-600 bg-primary-50 text-primary-800' => request()->routeIs('filament.backoffice.resources.identity.models.customers.*'), 'border-transparent text-text-secondary hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800' => ! request()->routeIs('filament.backoffice.resources.identity.models.customers.*')]) @if (request()->routeIs('filament.backoffice.resources.identity.models.customers.*')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-users" class="size-5 shrink-0" aria-hidden="true" /><span>Nasabah</span></a>
            @endif
            @if ($canViewUsers)
                <a href="{{ \App\Filament\Resources\Identity\Models\Users\UserResource::getUrl('index') }}" @class(['inline-flex min-h-12 items-center gap-2 border-b-2 px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2', 'border-primary-600 bg-primary-50 text-primary-800' => request()->routeIs('filament.backoffice.resources.identity.models.users.*'), 'border-transparent text-text-secondary hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800' => ! request()->routeIs('filament.backoffice.resources.identity.models.users.*')]) @if (request()->routeIs('filament.backoffice.resources.identity.models.users.*')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-user-circle" class="size-5 shrink-0" aria-hidden="true" /><span>Pengguna &amp; peran</span></a>
            @endif
            @if ($canVerifyCitizens)
                <a href="{{ \App\Filament\Resources\Identity\Models\CitizenVerifications\CitizenVerificationResource::getUrl('index') }}" @class(['inline-flex min-h-12 items-center gap-2 border-b-2 px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2', 'border-primary-600 bg-primary-50 text-primary-800' => request()->routeIs('filament.backoffice.resources.identity.models.citizen-verifications.*'), 'border-transparent text-text-secondary hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800' => ! request()->routeIs('filament.backoffice.resources.identity.models.citizen-verifications.*')]) @if (request()->routeIs('filament.backoffice.resources.identity.models.citizen-verifications.*')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-check-badge" class="size-5 shrink-0" aria-hidden="true" /><span>Verifikasi warga</span></a>
            @endif
        </div>
    </nav>

    <div class="mt-4 flex items-start gap-3 rounded-lg border border-info-200 bg-info-50 px-4 py-3 text-sm leading-6 text-info-950">
        <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
        <p>Gunakan Nasabah untuk identitas, kartu, QR, dan data wilayah. Gunakan Pengguna &amp; peran untuk akun dan akses. Verifikasi warga hanya berisi pendaftaran yang menunggu keputusan.</p>
    </div>
</x-filament-panels::page>
