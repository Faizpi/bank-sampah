<x-slot:title>Layanan</x-slot:title>
<section class="grid gap-6" aria-labelledby="services-title">
    <x-ui.page-heading id="services-title" title="Layanan untuk Anda" description="Pilih layanan yang Anda perlukan." mascot="3" />
    <div class="grid gap-3 sm:grid-cols-2">
        @if (\App\Domain\Payouts\Services\PayoutAccess::configured())<x-ui.action-card :href="route('citizen.payout.create')" icon="banknote" title="Pencairan digital (simulasi)" description="Proses otomatis melalui Xendit Test Mode." />@endif
        @foreach ([['citizen.pickup.create', 'truck', 'Minta penjemputan', 'Atur tanggal dan lokasi penjemputan.'], ['citizen.withdrawal.create', 'banknote', 'Cairkan saldo tunai', 'Ambil uang tunai dari tabungan sampah.'], ['citizen.grocery.create', 'package-open', 'Tukar saldo dengan sembako', 'Pilih paket sesuai saldo Anda.'], ['citizen.estimate', 'scale', 'Hitung perkiraan nilai', 'Cek perkiraan nilai sampah Anda.']] as [$destination, $icon, $label, $description])
            <x-ui.action-card :href="route($destination)" :icon="$icon" :title="$label" :description="$description" />
        @endforeach
    </div>
    <div class="grid gap-3 border-t border-border pt-6 sm:grid-cols-2">
        <x-ui.action-card :href="route('citizen.dashboard').'#pengajuan-aktif'" icon="clipboard-check" title="Lihat pengajuan aktif" />
        <x-ui.action-card :href="route('public.mobile-schedule')" icon="calendar-days" title="Jadwal layanan keliling" />
    </div>
</section>
