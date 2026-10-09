@extends('layouts.public')

@section('content')
    {{-- Hero Section --}}
    <section class="public-hero-grid relative isolate overflow-hidden border-b border-border/60 bg-surface" aria-labelledby="landing-title">
        <div class="public-container grid items-center gap-10 pb-12 pt-28 sm:pb-16 sm:pt-32 md:grid-cols-[minmax(0,1.15fr)_minmax(15rem,.85fr)] md:gap-8 md:pb-16 md:pt-36 lg:grid-cols-[1.05fr_.95fr] lg:gap-12 lg:pb-20 lg:pt-36">
            <div class="relative z-10 md:min-w-0 lg:max-w-2xl">
                <h1 id="landing-title" class="text-balance text-display font-extrabold tracking-tight text-deep-green lg:text-display-lg">
                    Sampah tercatat. <span class="text-forest-600">Nilai terjaga.</span> Desa bergerak bersama.
                </h1>
                <p class="mt-5 text-body leading-relaxed text-text-secondary lg:max-w-xl">
                    Layanan bank sampah Desa Sindangheula untuk setoran, saldo rupiah, penjemputan, dan program desa yang transparan.
                </p>

                <div class="mt-8 flex flex-col gap-3.5 sm:flex-row sm:flex-wrap sm:items-center">
                    @auth
                        @php
                            $heroDashboard = app(\App\Support\Auth\AuthenticatedUserRedirector::class)->dashboardUrl();
                        @endphp
                        <a href="{{ $heroDashboard }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-6 text-label font-bold text-surface shadow-sm transition duration-200 hover:bg-forest-700 hover:shadow-md active:translate-y-px focus-visible:ring-2 focus-visible:ring-focus">
                            <x-public.icon name="layout-dashboard" size="size-5" />
                            Buka Dashboard Saya
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-6 text-label font-bold text-surface shadow-sm transition duration-200 hover:bg-forest-700 hover:shadow-md active:translate-y-px focus-visible:ring-2 focus-visible:ring-focus">
                            Akses Akun Saya
                            <x-public.icon name="arrow-right" size="size-5" />
                        </a>
                        <a href="{{ route('register') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl border-2 border-forest-600 bg-surface px-6 text-label font-bold text-forest-700 shadow-xs transition duration-200 hover:bg-success-bg active:translate-y-px focus-visible:ring-2 focus-visible:ring-focus">
                            Daftar Sebagai Warga
                        </a>
                        <a href="#cara-kerja" class="inline-flex min-h-touch items-center justify-center gap-1.5 px-4 text-label font-bold text-forest-600 transition duration-180 hover:text-deep-green">
                            Lihat Cara Kerja
                            <x-public.icon name="chevron-right" size="size-4" />
                        </a>
                    @endauth
                </div>

                <dl class="mt-10 grid grid-cols-3 gap-3 border-t border-border pt-6 lg:max-w-xl">
                    <div class="rounded-lg border border-border bg-warm-canvas p-3">
                        <dt class="text-caption font-medium text-text-secondary">Saldo</dt>
                        <dd class="mt-0.5 text-label font-bold text-deep-green">Rupiah Langsung</dd>
                    </div>
                    <div class="rounded-lg border border-border bg-warm-canvas p-3">
                        <dt class="text-caption font-medium text-text-secondary">Bukti Digital</dt>
                        <dd class="mt-0.5 text-label font-bold text-deep-green">Terverifikasi</dd>
                    </div>
                    <div class="rounded-lg border border-border bg-warm-canvas p-3">
                        <dt class="text-caption font-medium text-text-secondary">Pelayanan</dt>
                        <dd class="mt-0.5 text-label font-bold text-deep-green">Mudah digunakan</dd>
                    </div>
                </dl>
            </div>

            <div class="relative z-10 flex justify-center md:justify-end">
                <x-ui.mascot
                    variant="6"
                    animate="true"
                    bubble="Yuk kelola sampah desa kita bersama!"
                    bubblePosition="top"
                    class="h-64 w-auto sm:h-80 md:h-72 lg:h-96"
                />
            </div>
        </div>
    </section>

    {{-- Kepala Desa Section --}}
    <section class="border-b border-border/60 bg-surface py-16 sm:py-20" aria-labelledby="village-head-title">
        <div class="public-container">
            <div class="grid items-start gap-10 lg:grid-cols-[minmax(17rem,.72fr)_minmax(0,1.28fr)] lg:gap-16">
                <figure class="mx-auto w-full max-w-sm lg:sticky lg:top-28">
                    <img
                        src="{{ asset('images/landing/kades.jpeg') }}"
                        alt="Suheli, Kepala Desa Sindangheula, mengenakan seragam kepala desa"
                        width="774"
                        height="900"
                        loading="lazy"
                        decoding="async"
                        class="aspect-[43/50] w-full rounded-2xl border-4 border-forest-600 bg-surface object-cover object-top shadow-xs"
                    >
                    <figcaption class="border-x border-b border-border bg-warm-canvas px-5 py-4 text-center">
                        <p class="text-title font-bold text-deep-green">Suheli, S.Kom.I., MM.</p>
                        <p class="mt-1 text-label font-semibold text-forest-600">Kepala Desa Sindangheula</p>
                    </figcaption>
                </figure>

                <div class="lg:pt-4">
                    <p class="text-label font-bold text-forest-600">Mengenal Kepala Desa Sindangheula</p>
                    <h2 id="village-head-title" class="mt-3 text-balance text-h1 font-extrabold tracking-tight text-deep-green">
                        Dari warga, dikelola bersama, kembali untuk warga.
                    </h2>

                    <blockquote class="mt-8 border-l-4 border-forest-600 pl-5 text-title font-semibold leading-relaxed text-deep-green sm:pl-6">
                        “Sampah yang kita kelola hari ini bukan hanya menjaga kebersihan desa, tetapi juga menjadi nilai yang kembali memberikan manfaat bagi masyarakat.”
                    </blockquote>

                    <div class="mt-8 space-y-5 text-body leading-relaxed text-text-secondary">
                        <p>
                            Sebagai Kepala Desa Sindangheula, Suheli, S.Kom.I., MM. mendorong pengelolaan sampah yang melibatkan masyarakat secara langsung. Melalui Bank Sampah Digital, sampah yang dikumpulkan dari warga tidak berhenti sebagai limbah, tetapi dicatat, dikelola, dan dikonversi menjadi nilai ekonomi.
                        </p>
                        <p>
                            Setiap setoran warga menjadi bagian dari gerakan bersama untuk menjaga kebersihan sekaligus menciptakan manfaat yang dapat dirasakan kembali oleh masyarakat. Dengan dukungan teknologi, proses pencatatan dan pengelolaan menjadi lebih transparan, sehingga nilai dari sampah yang berasal dari warga dapat kembali memberikan manfaat kepada warga.
                        </p>
                        <p class="font-semibold text-deep-green">
                            Sampah dari warga. Nilainya untuk warga. Desa bergerak bersama.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="ringkasan" class="scroll-mt-24 border-b border-border/60 bg-warm-canvas py-12 sm:py-16" aria-labelledby="summary-title">
        <div class="public-container">
            <h2 id="summary-title" class="mb-7 text-h2 font-bold text-deep-green">Ringkasan bank sampah</h2>
            @php
                $weight = $summary['total_weight_kg'] === null ? null : rtrim(rtrim(number_format((float) $summary['total_weight_kg'], 3, ',', '.'), '0'), ',');
                $summaryCards = [
                    ['label' => 'Warga aktif', 'value' => $summary['active_customers'], 'icon' => 'users', 'unit' => ''],
                    ['label' => 'Petugas aktif', 'value' => $summary['active_officers'], 'icon' => 'clipboard-check', 'unit' => ''],
                    ['label' => 'Setoran tercatat', 'value' => $summary['deposit_count'], 'icon' => 'package-check', 'unit' => ''],
                    ['label' => 'Sampah terkumpul', 'value' => $weight, 'icon' => 'scale', 'unit' => 'kg'],
                ];
            @endphp
            <dl class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                @foreach ($summaryCards as $card)
                    <div class="flex min-w-0 flex-col items-center rounded-2xl bg-forest-600 px-4 py-7 text-center text-surface sm:px-6 sm:py-9">
                        <x-public.icon :name="$card['icon']" size="size-8" class="mb-5 text-surface/80" />
                        <dt class="order-2 mt-3 text-body-sm font-semibold sm:text-body">{{ $card['label'] }}</dt>
                        <dd class="order-1 max-w-full break-words text-h1 font-extrabold tabular-nums sm:text-display">
                            {{ $card['value'] === null ? '—' : (is_int($card['value']) ? number_format($card['value'], 0, ',', '.') : $card['value']) }}
                            @if ($card['value'] !== null && $card['unit'])<span class="text-label font-medium">{{ $card['unit'] }}</span>@endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section id="sampah-diterima" class="scroll-mt-24 border-b border-border/60 bg-surface py-16 sm:py-20" aria-labelledby="accepted-waste-title">
        <div class="public-container">
            <x-public.section-header id="accepted-waste-title" title="Sampah yang diterima">
                <x-slot:actions>
                    <a href="{{ route('public.catalog') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-lg bg-forest-600 px-5 text-label font-semibold text-surface hover:bg-forest-700 focus-visible:ring-2 focus-visible:ring-focus">Katalog lengkap <x-public.icon name="arrow-right" size="size-5" /></a>
                    <a href="{{ route('public.prices') }}" class="inline-flex min-h-touch items-center justify-center rounded-lg border border-border px-5 text-label font-semibold text-deep-green hover:bg-success-bg focus-visible:ring-2 focus-visible:ring-focus">Lihat semua harga</a>
                </x-slot:actions>
            </x-public.section-header>
            @if ($wasteTypes->isEmpty())
                <x-ui.empty-state class="mt-8" title="Katalog sedang disiapkan" description="Belum ada jenis sampah yang tersedia." />
            @else
                <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($wasteTypes as $wasteType)
                        <x-public.waste-card :waste-type="$wasteType" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Layanan Section --}}
    <section id="layanan" class="scroll-mt-24 border-b border-border/60 bg-warm-canvas py-16 sm:py-20" aria-labelledby="services-title">
        <div class="public-container">
            <div class="grid gap-10 lg:grid-cols-[.72fr_1.28fr] lg:gap-16">
                <x-public.section-header
                    id="services-title"
                    eyebrow="Layanan Transparan Warga"
                    title="Satu catatan untuk setiap langkah."
                    description="Setoran, penjemputan, saldo, dan program desa tercatat dalam satu layanan."
                    class="lg:sticky lg:top-28 lg:self-start"
                />

                <div class="space-y-4" aria-label="Daftar layanan">
                    <article class="group grid gap-5 rounded-2xl border border-border bg-surface p-5 shadow-xs transition-all duration-200 hover:border-forest-600/40 hover:shadow-sm sm:grid-cols-[96px_1fr_auto] sm:items-center sm:p-6">
                        <div class="flex justify-center">
                            <x-ui.mascot variant="4" class="h-20 w-auto" />
                        </div>
                        <div>
                            <h3 class="text-title font-bold text-deep-green transition-colors group-hover:text-forest-600">Setoran yang Dapat Ditelusuri</h3>
                            <p class="mt-1.5 text-body-sm leading-relaxed text-text-secondary">Berat, harga saat transaksi, nilai, petugas, dan bukti tersimpan dalam riwayat.</p>
                        </div>
                        <span class="inline-flex self-start justify-center rounded-full border border-border bg-success-bg px-3 py-1 text-xs font-bold text-forest-600 sm:self-center">Beragam jenis sampah</span>
                    </article>

                    <article class="group grid gap-5 rounded-2xl border border-border bg-surface p-5 shadow-xs transition-all duration-200 hover:border-forest-600/40 hover:shadow-sm sm:grid-cols-[96px_1fr_auto] sm:items-center sm:p-6">
                        <div class="flex justify-center">
                            <x-ui.mascot variant="8" class="h-20 w-auto" />
                        </div>
                        <div>
                            <h3 class="text-title font-bold text-deep-green transition-colors group-hover:text-forest-600">Penjemputan yang Terjadwal</h3>
                            <p class="mt-1.5 text-body-sm leading-relaxed text-text-secondary">Pilih tanggal penjemputan; foto dan catatan opsional. Nilai akhir mengikuti penimbangan aktual Petugas.</p>
                        </div>
                        <span class="inline-flex self-start justify-center rounded-full border border-info-bg bg-info-bg px-3 py-1 text-xs font-bold text-sky-blue sm:self-center">Sesuai Kapasitas</span>
                    </article>

                    <article class="group grid gap-5 rounded-2xl border border-border bg-surface p-5 shadow-xs transition-all duration-200 hover:border-forest-600/40 hover:shadow-sm sm:grid-cols-[96px_1fr_auto] sm:items-center sm:p-6">
                        <div class="flex justify-center">
                            <x-ui.mascot variant="5" class="h-20 w-auto" />
                        </div>
                        <div>
                            <h3 class="text-title font-bold text-deep-green transition-colors group-hover:text-forest-600">Saldo Rupiah yang Jelas</h3>
                            <p class="mt-1.5 text-body-sm leading-relaxed text-text-secondary">Periksa saldo tersedia, dana yang ditahan, pemasukan, pengeluaran, dan koreksi.</p>
                        </div>
                        <span class="inline-flex self-start justify-center rounded-full border border-border bg-warm-canvas px-3 py-1 text-xs font-bold text-deep-green sm:self-center">Perubahan saldo tercatat</span>
                    </article>
                </div>
            </div>
        </div>
    </section>

    {{-- Cara Kerja Section --}}
    <section id="cara-kerja" class="scroll-mt-24 border-b border-border/60 bg-surface py-16 sm:py-24" aria-labelledby="how-it-works-title">
        <div class="public-container">
            <x-public.section-header
                id="how-it-works-title"
                eyebrow="Alur Sederhana"
                title="Dari sampah terpilah menjadi saldo yang terbaca."
                    description="Setiap tahap tercatat sebelum saldo bertambah."
            />

            <ol class="mt-12 grid gap-6 md:grid-cols-3">
                <li class="relative flex flex-col items-center rounded-2xl border border-border bg-warm-canvas p-6 text-center shadow-xs sm:p-8">
                    <span class="absolute -top-3.5 rounded-full bg-forest-600 px-3.5 py-1 text-xs font-extrabold text-surface shadow-xs">Langkah 1</span>
                    <div class="mt-2 flex h-32 items-center justify-center">
                        <x-ui.mascot variant="3" bubble="Pilah sampahmu!" class="h-28 w-auto" />
                    </div>
                    <h3 class="mt-4 text-h3 font-bold text-deep-green">Bawa atau Ajukan</h3>
                    <p class="mt-2.5 text-body-sm leading-relaxed text-text-secondary">Datang ke titik layanan atau ajukan penjemputan dari rumah sesuai kapasitas.</p>
                </li>

                <li class="relative flex flex-col items-center rounded-2xl border border-border bg-warm-canvas p-6 text-center shadow-xs sm:p-8">
                    <span class="absolute -top-3.5 rounded-full bg-forest-600 px-3.5 py-1 text-xs font-extrabold text-surface shadow-xs">Langkah 2</span>
                    <div class="mt-2 flex h-32 items-center justify-center">
                        <x-ui.mascot variant="7" bubble="Timbang akurat!" class="h-28 w-auto" />
                    </div>
                    <h3 class="mt-4 text-h3 font-bold text-deep-green">Timbang &amp; Konfirmasi</h3>
                    <p class="mt-2.5 text-body-sm leading-relaxed text-text-secondary">Petugas mencatat berat aktual per jenis dengan harga pasar yang berlaku.</p>
                </li>

                <li class="relative flex flex-col items-center rounded-2xl border border-border bg-warm-canvas p-6 text-center shadow-xs sm:p-8">
                    <span class="absolute -top-3.5 rounded-full bg-forest-600 px-3.5 py-1 text-xs font-extrabold text-surface shadow-xs">Langkah 3</span>
                    <div class="mt-2 flex h-32 items-center justify-center">
                        <x-ui.mascot variant="12" bubble="Saldo bertambah!" class="h-28 w-auto" />
                    </div>
                    <h3 class="mt-4 text-h3 font-bold text-deep-green">Saldo &amp; Bukti Digital</h3>
                    <p class="mt-2.5 text-body-sm leading-relaxed text-text-secondary">Setelah transaksi sah, saldo bertambah dan bukti dapat diverifikasi melalui QR.</p>
                </li>
            </ol>
        </div>
    </section>

    {{-- Maskot Stories Section --}}
    <section class="border-b border-border/60 bg-warm-canvas py-16 sm:py-20" aria-labelledby="mascot-stories-title">
        <div class="public-container">
            <x-public.section-header
                id="mascot-stories-title"
                eyebrow="Warga dan Lingkungan"
                title="Kebiasaan kecil, dampak yang terlihat."
                    description="Bank Sampah Sindangheula mendampingi warga dalam mengelola sampah secara bertanggung jawab."
            />
            <div class="mt-12 grid gap-6 md:grid-cols-3">
                <article class="flex flex-col items-center rounded-2xl border border-border bg-surface p-6 text-center shadow-xs">
                    <x-ui.mascot variant="9" class="h-36 w-auto" />
                    <h3 class="mt-4 text-title font-bold text-deep-green">Pilih Langkah yang Tepat</h3>
                    <p class="mt-2 text-body-sm leading-relaxed text-text-secondary">Setor langsung ke lokasi bank sampah atau ajukan penjemputan sesuai kebutuhan.</p>
                </article>
                <article class="flex flex-col items-center rounded-2xl border border-border bg-surface p-6 text-center shadow-xs">
                    <x-ui.mascot variant="10" class="h-36 w-auto" />
                    <h3 class="mt-4 text-title font-bold text-deep-green">Pahami Sebelum Bertindak</h3>
                    <p class="mt-2 text-body-sm leading-relaxed text-text-secondary">Katalog harga terbaru, jadwal operasional, dan riwayat setoran tersedia.</p>
                </article>
                <article class="flex flex-col items-center rounded-2xl border border-border bg-surface p-6 text-center shadow-xs">
                    <x-ui.mascot variant="11" class="h-36 w-auto" />
                    <h3 class="mt-4 text-title font-bold text-deep-green">Sampah yang Bisa Dimanfaatkan</h3>
                    <p class="mt-2 text-body-sm leading-relaxed text-text-secondary">Setiap jenis sampah yang dapat didaur ulang diproses secara bertanggung jawab.</p>
                </article>
            </div>
        </div>
    </section>

    {{-- CTA Section —— light bg so it doesn't bleed into footer --}}
    <section class="border-t border-border bg-surface py-16 sm:py-20" aria-labelledby="account-cta-title">
        <div class="public-container flex flex-col gap-8 md:grid md:grid-cols-[minmax(0,1fr)_auto] md:items-center lg:flex lg:flex-row lg:justify-between">
            <div class="md:min-w-0 lg:max-w-2xl">
                <h2 id="account-cta-title" class="text-h2 font-extrabold text-deep-green lg:text-h1">Sudah punya akun?</h2>
                <p class="mt-3 text-body leading-relaxed text-text-secondary">Masuk ke layanan sesuai peran Anda: Warga, Petugas, Bendahara, atau Admin.</p>
            </div>
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center md:flex-col md:items-stretch lg:flex-row lg:items-center">
                <x-ui.mascot variant="13" bubble="Siap bantu kamu!" bubblePosition="top" class="h-36 w-auto self-center md:h-28 lg:h-36" />
                <div class="flex flex-col gap-3.5 sm:shrink-0">
                    @auth
                        @php
                            $ctaDashboard = app(\App\Support\Auth\AuthenticatedUserRedirector::class)->dashboardUrl();
                        @endphp
                        <a href="{{ $ctaDashboard }}" class="inline-flex min-h-touch shrink-0 items-center justify-center gap-2 rounded-xl bg-forest-600 px-6 text-label font-bold text-surface shadow-sm transition duration-200 hover:bg-forest-700 hover:shadow-md active:translate-y-px focus-visible:ring-2 focus-visible:ring-focus">
                            Buka Dashboard Saya
                            <x-public.icon name="arrow-right" size="size-5" />
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-touch shrink-0 items-center justify-center gap-2 rounded-xl bg-forest-600 px-6 text-label font-bold text-surface shadow-sm transition duration-200 hover:bg-forest-700 hover:shadow-md active:translate-y-px focus-visible:ring-2 focus-visible:ring-focus">
                            Masuk ke akun
                            <x-public.icon name="arrow-right" size="size-5" />
                        </a>
                        <a href="{{ route('register') }}" class="inline-flex min-h-touch shrink-0 items-center justify-center rounded-xl border-2 border-forest-600 bg-surface px-6 text-label font-bold text-forest-700 transition duration-200 hover:bg-success-bg active:translate-y-px focus-visible:ring-2 focus-visible:ring-focus">
                            Daftar Sebagai Warga
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </section>
@endsection
