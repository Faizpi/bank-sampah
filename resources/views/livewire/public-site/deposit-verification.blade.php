<x-slot:title>Verifikasi bukti setoran</x-slot:title>

<div class="public-canvas">
    <section class="border-b border-deep-green bg-deep-green text-surface" aria-labelledby="verification-title">
        <div class="public-container grid items-center gap-8 section-space lg:grid-cols-[1fr_auto]">
            <div>
            <p class="text-label font-semibold tracking-wide text-surface/80">Verifikasi publik</p>
            <h1 id="verification-title" class="mt-2 max-w-3xl text-h1 lg:text-h1-lg text-surface">Bukti setoran valid</h1>
            <p class="mt-3 max-w-2xl text-body text-surface/80">Informasi publik dibatasi pada ringkasan transaksi untuk menjaga privasi warga.</p>
            </div>
            <img src="{{ asset('images/landing/mascot-13.png') }}" alt="Maskot badak menyambut pemeriksaan bukti setoran" class="mx-auto h-40 w-44 object-contain lg:h-48 lg:w-52">
        </div>
    </section>

    <section class="public-section-canvas">
        <div class="public-container section-space max-w-2xl">
            <x-public.section-header
                id="verification-summary-title"
                eyebrow="Bukti publik"
                title="Ringkasan transaksi terverifikasi"
                description="Nomor bukti, waktu, berat, nilai, dan status tersedia tanpa membuka saldo atau data pribadi."
            />

            <div class="mt-8 space-y-6">
                <x-ui.success-state
                    title="Setoran terverifikasi"
                    :reference="$receipt['number']"
                    :value="'Rp '.number_format($receipt['value'], 0, ',', '.')"
                    :time="\Illuminate\Support\Carbon::parse($receipt['date'])->translatedFormat('d F Y, H:i')"
                    status="success"
                />
                <x-ui.panel title="Ringkasan bukti" description="Tidak ada saldo atau data pribadi pada halaman ini.">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-body-sm text-text-secondary">Nomor bukti</dt><dd class="mt-1 text-title text-deep-green">{{ $receipt['number'] }}</dd></div>
                        <div><dt class="text-body-sm text-text-secondary">Tanggal</dt><dd class="mt-1 text-title text-deep-green">{{ \Illuminate\Support\Carbon::parse($receipt['date'])->translatedFormat('d F Y, H:i') }}</dd></div>
                        <div><dt class="text-body-sm text-text-secondary">Berat</dt><dd class="mt-1 amount-tabular text-title text-deep-green">{{ \App\Support\WeightFormatter::format($receipt['weight_kg']) }} kg</dd></div>
                        <div><dt class="text-body-sm text-text-secondary">Nilai</dt><dd class="mt-1 amount-tabular text-title text-deep-green">Rp {{ number_format($receipt['value'], 0, ',', '.') }}</dd></div>
                    </dl>
                </x-ui.panel>
            </div>

            <div class="mt-8 flex flex-wrap gap-2">
                <a href="{{ route('login') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-forest-600 px-5 text-label text-surface transition duration-180 ease-standard hover:bg-forest-700 active:translate-y-px">
                    Masuk ke akun
                    <x-public.icon name="log-in" size="size-5" />
                </a>
                <a href="{{ route('home') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-border bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:border-forest-600 hover:bg-success-bg active:translate-y-px">
                    Kembali ke beranda
                </a>
            </div>
        </div>
    </section>
</div>
