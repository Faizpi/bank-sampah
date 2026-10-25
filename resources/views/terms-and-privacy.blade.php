@extends('layouts.public')

@section('title', 'Ketentuan Operasional dan Kebijakan Privasi | Bank Sampah Digital Sindangheula')
@section('description', 'Ketentuan Operasional v2.0 dan Kebijakan Privasi Ringkas v2.0 Bank Sampah Digital Sindangheula, termasuk kanal opsional Telegram.')

@section('content')
    <div class="public-canvas">
        <section class="border-b border-deep-green bg-deep-green text-surface" aria-labelledby="terms-title">
            <div class="public-container grid items-center gap-8 section-space lg:grid-cols-[1fr_auto]">
                <div>
                <p class="text-label font-semibold tracking-wide text-surface/80">Dokumen publik</p>
                <h1 id="terms-title" class="mt-2 max-w-3xl text-h1 lg:text-h1-lg text-surface">Ketentuan Operasional v2.0 dan Kebijakan Privasi Ringkas v2.0</h1>
                <p class="mt-3 max-w-2xl text-body text-surface/80">Dokumen ini tersedia untuk dibaca sebelum pendaftaran dan berlaku untuk penggunaan layanan Bank Sampah Digital Sindangheula, termasuk kanal opsional Telegram.</p>
                </div>
                <img src="{{ asset('images/landing/mascot-10.png') }}" alt="Maskot badak sedang berpikir saat warga membaca ketentuan layanan" class="mx-auto h-40 w-44 object-contain lg:h-48 lg:w-52">
            </div>
        </section>

        <section class="public-section-canvas">
            <div class="public-container section-space">
                <x-public.section-header
                    id="terms-content-title"
                    eyebrow="Baca sebelum menggunakan layanan"
                    title="Ketentuan dan privasi"
                    description="Pahami cara pendaftaran, penggunaan data, batas keterbukaan, dan perubahan versi dokumen."
                />

                <article class="mx-auto mt-10 max-w-3xl rounded-lg border border-border bg-surface p-5 sm:p-7" aria-label="Isi ketentuan operasional dan kebijakan privasi">
                    <div class="space-y-8 text-body leading-relaxed text-text-primary">
                        <section aria-labelledby="terms-registration">
                            <h2 id="terms-registration" class="text-h2 text-deep-green">Pendaftaran dan penerimaan ketentuan</h2>
                            <ol class="mt-3 list-decimal space-y-2 pl-5">
                                <li>Pendaftar wajib menyetujui Ketentuan Operasional v2.0 dan Kebijakan Privasi Ringkas v2.0 saat membuat akun.</li>
                                <li>Sistem menyimpan versi ketentuan, waktu persetujuan, serta riwayat persetujuan setiap pengguna.</li>
                                <li>Menyetujui ketentuan tidak memverifikasi domisili, mencegah duplikasi akun, mengaktifkan akun, atau memasukkan Anda secara otomatis.</li>
                                <li>Setelah pendaftaran valid, akun tetap berstatus menunggu verifikasi. Hanya keputusan verifikasi admin yang dapat mengaktifkan akun.</li>
                            </ol>
                        </section>
                        <section aria-labelledby="privacy-data">
                            <h2 id="privacy-data" class="text-h2 text-deep-green">Data dan tujuan penggunaan</h2>
                            <p class="mt-3">Sistem menggunakan data yang diperlukan untuk verifikasi akun dan pencegahan duplikasi, penyediaan layanan bank sampah, pemberitahuan dalam aplikasi serta kanal Telegram yang ditautkan dan diizinkan pengguna, keamanan, dan pencatatan aktivitas layanan.</p>
                        </section>
                        <section aria-labelledby="terms-telegram">
                            <h2 id="terms-telegram" class="text-h2 text-deep-green">Kanal Telegram opsional</h2>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>Bot Telegram hanya aktif setelah penautan yang dimulai dari halaman Profil di website dengan kata sandi akun; sistem tidak pernah meminta kredensial melalui chat.</li>
                                <li>Satu akun web tertaut paling banyak satu akun Telegram dan sebaliknya; penggantian menuntut pemutusan tautan sebelumnya melalui akun pemiliknya.</li>
                                <li>Izin tindakan melalui bot berjangka (Warga 30 hari; Petugas/Bendahara 8 jam atau 60 menit tanpa aktivitas) dan diperbarui dari web. Berakhirnya izin tidak menghentikan notifikasi yang diizinkan.</li>
                                <li>Pengguna dapat mengakhiri izin dengan perintah keluar pada bot, memutus tautan dari Profil di website, atau meminta pencabutan oleh Admin yang berwenang.</li>
                            </ul>
                        </section>
                        <section aria-labelledby="privacy-telegram">
                            <h2 id="privacy-telegram" class="text-h2 text-deep-green">Data dan kiriman Telegram</h2>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>Yang dikirim hanya milik akun tertaut: nama dan peran akun, isi yang diminta sendiri, notifikasi yang diizinkan, ringkasan, pengumuman yang dilanggan, laporan pembayaran milik Bendahara sendiri, dan foto opsional yang dikirim pengguna.</li>
                                <li>Semua pilihan kiriman mati secara default dan hanya aktif setelah dipilih; dapat diubah atau dihentikan kapan saja dari website maupun bot.</li>
                                <li>Pesan yang sudah terkirim berada pada riwayat Telegram pengguna dan tidak dapat ditarik sistem. Antrean kiriman di server dihapus paling lama tujuh hari dan berkas laporan paling lama 24 jam.</li>
                                <li>Pemutusan tautan dari Profil atau pencabutan Admin membatalkan seluruh kiriman tertunda.</li>
                            </ul>
                        </section>
                        <section aria-labelledby="privacy-access">
                            <h2 id="privacy-access" class="text-h2 text-deep-green">Akses dan keterbukaan terbatas</h2>
                            <p class="mt-3">Akses data ditentukan oleh peran dan data yang boleh diakses. Halaman publik tidak membuka nama, alamat, telepon, saldo, foto privat, atau riwayat individu warga.</p>
                        </section>
                        <section aria-labelledby="terms-changes">
                            <h2 id="terms-changes" class="text-h2 text-deep-green">Perubahan dokumen</h2>
                            <p class="mt-3">Versi baru diterbitkan secara prospektif melalui proses yang disetujui dan didokumentasikan. Riwayat penerimaan yang telah tersimpan tidak dihapus atau ditimpa.</p>
                        </section>
                    </div>
                </article>

                <div class="mx-auto mt-8 flex max-w-3xl flex-wrap gap-2">
                    <a href="{{ route('register') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-forest-600 px-5 text-label text-surface transition duration-180 ease-standard hover:bg-forest-700 active:translate-y-px">
                        Lanjut ke pendaftaran
                        <x-public.icon name="arrow-right" size="size-5" />
                    </a>
                    <a href="{{ route('home') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-border bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:border-forest-600 hover:bg-success-bg active:translate-y-px">
                        Kembali ke beranda
                    </a>
                </div>
            </div>
        </section>
    </div>
@endsection
