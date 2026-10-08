<x-slot:title>Layanan</x-slot:title>
<x-slot:context>Pilih yang Anda perlukan</x-slot:context>
<section class="space-y-4" aria-labelledby="services-title">
    <h1 id="services-title" class="text-h1 font-bold text-deep-green">Apa yang ingin Anda lakukan?</h1>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ([['citizen.pickup.create', 'Minta penjemputan', 'Pilih tanggal, alamat, dan jenis sampah.'], ['citizen.withdrawal.create', 'Cairkan saldo tunai', 'Ajukan nominal untuk diambil dari Bendahara.'], ['citizen.grocery.create', 'Tukar saldo dengan sembako', 'Pilih paket sesuai saldo yang tersedia.'], ['citizen.estimate', 'Hitung perkiraan nilai', 'Lihat perkiraan sebelum sampah ditimbang.']] as [$destination, $label, $description])
            <a href="{{ route($destination) }}" class="min-h-touch rounded-xl border border-border bg-surface p-5 focus-visible:ring-2 focus-visible:ring-focus">
                <span class="block text-title font-bold text-deep-green">{{ $label }}</span><span class="mt-2 block">{{ $description }}</span>
            </a>
        @endforeach
    </div>
    <a href="{{ route('citizen.dashboard') }}#pengajuan-aktif" class="inline-flex min-h-touch items-center rounded-md border border-border px-4 font-semibold">Lihat pengajuan aktif</a>
    <a href="{{ route('public.mobile-schedule') }}" class="inline-flex min-h-touch items-center rounded-md border border-border px-4 font-semibold">Jadwal layanan keliling</a>
</section>
