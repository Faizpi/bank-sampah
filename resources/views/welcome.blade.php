@extends('layouts.public')

@section('content')
    {{-- Hero Section --}}
    <section class="public-hero-grid relative isolate overflow-hidden border-b border-border/60 bg-surface" aria-labelledby="landing-title">
        <div class="public-container grid items-center gap-10 pb-12 pt-28 sm:pb-16 sm:pt-32 lg:grid-cols-[1.05fr_.95fr] lg:gap-12 lg:pb-20 lg:pt-36">
            <div class="relative z-10 max-w-2xl">
                <h1 id="landing-title" class="text-balance text-display font-extrabold tracking-tight text-deep-green lg:text-display-lg">
                    Sampah tercatat. <span class="text-forest-600">Nilai terjaga.</span> Desa bergerak bersama.
                </h1>
                <p class="mt-5 max-w-xl text-body leading-relaxed text-text-secondary">
                    Layanan bank sampah Desa Sindangheula yang membuat setoran, saldo rupiah, penjemputan, dan perkembangan program desa lebih transparan &amp; mudah dipahami.
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

                <dl class="mt-10 grid max-w-xl grid-cols-3 gap-3 border-t border-border pt-6">
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
                        <dd class="mt-0.5 text-label font-bold text-deep-green">Digital &amp; Ramah</dd>
                    </div>
                </dl>
            </div>

            <div class="relative z-10 flex justify-center lg:justify-end">
                <x-ui.mascot
                    variant="6"
                    animate="true"
                    bubble="Yuk kelola sampah desa kita bersama!"
                    bubblePosition="top"
                    class="h-64 sm:h-80 md:h-96 w-auto"
                />
            </div>
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
                    description="Warga dapat menyetor langsung, mengajukan penjemputan, memeriksa saldo, dan mengikuti program desa dari satu layanan yang tertib."
                    class="lg:sticky lg:top-28 lg:self-start"
                />

                <div class="space-y-4" aria-label="Daftar layanan">
                    <article class="group grid gap-5 rounded-2xl border border-border bg-surface p-5 shadow-xs transition-all duration-200 hover:border-forest-600/40 hover:shadow-sm sm:grid-cols-[96px_1fr_auto] sm:items-center sm:p-6">
                        <div class="flex justify-center">
                            <x-ui.mascot variant="4" class="h-20 w-auto" />
                        </div>
                        <div>
                            <h3 class="text-title font-bold text-deep-green transition-colors group-hover:text-forest-600">Setoran yang Dapat Ditelusuri</h3>
                            <p class="mt-1.5 text-body-sm leading-relaxed text-text-secondary">Berat, harga saat transaksi, nilai, petugas, dan bukti tersimpan dalam satu riwayat terpercaya.</p>
                        </div>
                        <span class="inline-flex self-start justify-center rounded-full border border-border bg-success-bg px-3 py-1 text-xs font-bold text-forest-600 sm:self-center">Multi-jenis</span>
                    </article>

                    <article class="group grid gap-5 rounded-2xl border border-border bg-surface p-5 shadow-xs transition-all duration-200 hover:border-forest-600/40 hover:shadow-sm sm:grid-cols-[96px_1fr_auto] sm:items-center sm:p-6">
                        <div class="flex justify-center">
                            <x-ui.mascot variant="8" class="h-20 w-auto" />
                        </div>
                        <div>
                            <h3 class="text-title font-bold text-deep-green transition-colors group-hover:text-forest-600">Penjemputan yang Terjadwal</h3>
                            <p class="mt-1.5 text-body-sm leading-relaxed text-text-secondary">Ajukan dengan foto, pilih tanggal tersedia, lalu nilai akhir mengikuti penimbangan aktual petugas.</p>
                        </div>
                        <span class="inline-flex self-start justify-center rounded-full border border-info-bg bg-info-bg px-3 py-1 text-xs font-bold text-sky-blue sm:self-center">Sesuai Kapasitas</span>
                    </article>

                    <article class="group grid gap-5 rounded-2xl border border-border bg-surface p-5 shadow-xs transition-all duration-200 hover:border-forest-600/40 hover:shadow-sm sm:grid-cols-[96px_1fr_auto] sm:items-center sm:p-6">
                        <div class="flex justify-center">
                            <x-ui.mascot variant="5" class="h-20 w-auto" />
                        </div>
                        <div>
                            <h3 class="text-title font-bold text-deep-green transition-colors group-hover:text-forest-600">Saldo Rupiah yang Jelas</h3>
                            <p class="mt-1.5 text-body-sm leading-relaxed text-text-secondary">Saldo tersedia, tertahan, masuk, keluar, dan koreksi dapat diperiksa tanpa kebingungan poin.</p>
                        </div>
                        <span class="inline-flex self-start justify-center rounded-full border border-border bg-warm-canvas px-3 py-1 text-xs font-bold text-deep-green sm:self-center">Mutasi tercatat</span>
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
                description="Setiap tahap punya catatan yang jelas agar warga tahu apa yang terjadi sebelum saldo bertambah."
            />

            <ol class="mt-12 grid gap-6 md:grid-cols-3">
                <li class="relative flex flex-col items-center rounded-2xl border border-border bg-warm-canvas p-6 text-center shadow-xs sm:p-8">
                    <span class="absolute -top-3.5 rounded-full bg-forest-600 px-3.5 py-1 text-xs font-extrabold text-surface shadow-xs">Langkah 1</span>
                    <div class="mt-2 flex h-32 items-center justify-center">
                        <x-ui.mascot variant="3" bubble="Pilah sampahmu!" class="h-28 w-auto" />
                    </div>
                    <h3 class="mt-4 text-h3 font-bold text-deep-green">Bawa atau Ajukan</h3>
                    <p class="mt-2.5 text-body-sm leading-relaxed text-text-secondary">Datang ke titik layanan, layanan keliling, atau ajukan penjemputan dari rumah sesuai kapasitas.</p>
                </li>

                <li class="relative flex flex-col items-center rounded-2xl border border-border bg-warm-canvas p-6 text-center shadow-xs sm:p-8">
                    <span class="absolute -top-3.5 rounded-full bg-forest-600 px-3.5 py-1 text-xs font-extrabold text-surface shadow-xs">Langkah 2</span>
                    <div class="mt-2 flex h-32 items-center justify-center">
                        <x-ui.mascot variant="7" bubble="Timbang akurat!" class="h-28 w-auto" />
                    </div>
                    <h3 class="mt-4 text-h3 font-bold text-deep-green">Timbang &amp; Konfirmasi</h3>
                    <p class="mt-2.5 text-body-sm leading-relaxed text-text-secondary">Petugas mencatat berat aktual per jenis dan sistem mengambil harga pasar yang berlaku.</p>
                </li>

                <li class="relative flex flex-col items-center rounded-2xl border border-border bg-warm-canvas p-6 text-center shadow-xs sm:p-8">
                    <span class="absolute -top-3.5 rounded-full bg-forest-600 px-3.5 py-1 text-xs font-extrabold text-surface shadow-xs">Langkah 3</span>
                    <div class="mt-2 flex h-32 items-center justify-center">
                        <x-ui.mascot variant="12" bubble="Saldo bertambah!" class="h-28 w-auto" />
                    </div>
                    <h3 class="mt-4 text-h3 font-bold text-deep-green">Saldo &amp; Bukti Digital</h3>
                    <p class="mt-2.5 text-body-sm leading-relaxed text-text-secondary">Setelah transaksi sah, saldo bertambah dan bukti dapat diverifikasi kapan saja melalui QR.</p>
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
                description="Bank Sampah Sindangheula mendampingi warga di setiap langkah: memilih, memahami, dan mengelola sampah secara bertanggung jawab."
            />
            <div class="mt-12 grid gap-6 md:grid-cols-3">
                <article class="flex flex-col items-center rounded-2xl border border-border bg-surface p-6 text-center shadow-xs">
                    <x-ui.mascot variant="9" class="h-36 w-auto" />
                    <h3 class="mt-4 text-title font-bold text-deep-green">Pilih Langkah yang Tepat</h3>
                    <p class="mt-2 text-body-sm leading-relaxed text-text-secondary">Mulai dari setoran langsung ke lokasi bank sampah atau ajukan penjemputan sesuai kebutuhan rumah tangga.</p>
                </article>
                <article class="flex flex-col items-center rounded-2xl border border-border bg-surface p-6 text-center shadow-xs">
                    <x-ui.mascot variant="10" class="h-36 w-auto" />
                    <h3 class="mt-4 text-title font-bold text-deep-green">Pahami Sebelum Bertindak</h3>
                    <p class="mt-2 text-body-sm leading-relaxed text-text-secondary">Katalog harga terbaru, jadwal operasional, dan riwayat setoran transparan untuk kenyamanan Anda.</p>
                </article>
                <article class="flex flex-col items-center rounded-2xl border border-border bg-surface p-6 text-center shadow-xs">
                    <x-ui.mascot variant="11" class="h-36 w-auto" />
                    <h3 class="mt-4 text-title font-bold text-deep-green">Material Punya Tujuan</h3>
                    <p class="mt-2 text-body-sm leading-relaxed text-text-secondary">Sampah terpilah tidak berhenti di rumah; setiap jenis material daur ulang diproses secara bertanggung jawab.</p>
                </article>
            </div>
        </div>
    </section>

    {{-- CTA Section —— light bg so it doesn't bleed into footer --}}
    <section class="border-t border-border bg-surface py-16 sm:py-20" aria-labelledby="account-cta-title">
        <div class="public-container flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <span class="inline-flex rounded-full border border-forest-600 bg-success-bg px-3.5 py-1 text-xs font-semibold text-forest-700">Bergabung Sekarang</span>
                <h2 id="account-cta-title" class="mt-3 text-h2 font-extrabold text-deep-green lg:text-h1">Sudah menjadi warga atau petugas?</h2>
                <p class="mt-3 text-body leading-relaxed text-text-secondary">Masuk untuk melihat saldo, riwayat transaksi, pengajuan penjemputan, dan fitur lengkap Bank Sampah.</p>
            </div>
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                <x-ui.mascot variant="13" bubble="Siap bantu kamu!" bubblePosition="top" class="h-36 w-auto" />
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
                            Masuk Ke Sistem
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
