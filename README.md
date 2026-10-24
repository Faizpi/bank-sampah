<p align="center">
  <img src="./public/images/landing/mascot-6.png" width="160" alt="Maskot Bank Sampah Digital Sindangheula">
</p>

# Bank Sampah Digital Sindangheula

Website mobile-first untuk setoran, saldo rupiah, penjemputan, pencairan tunai, dan penukaran sembako Desa Sindangheula.

[Workflow](docs/WEB_WORKFLOW.md) · [Panduan pengguna](docs/USER_FLOWS.md) · [Dokumentasi](docs/README.md) · [Pengujian](docs/TEST_PLAN.md) · [Validasi cutover](docs/VALIDATION_CUTOVER_2026-10-05.md)

## Empat peran dan tugas yang jelas

| Peran | Tugas utama |
| --- | --- |
| Warga | Menggunakan akun/smartphone sendiri; saldo, kartu, riwayat, dan pengajuan layanan. |
| Petugas | Setoran, pickup, layanan keliling, sembako pada area tugas aktif, serta riwayat pekerjaan sendiri. |
| Bendahara | Mengambil dan mencatat pembayaran tunai searea; laporan pembayaran sendiri. |
| Admin | Akun, wilayah, master, laporan, audit, koreksi resmi, dan pengecualian. |

Satu akun hanya satu peran. Pengurus tidak merangkap tugas. Admin bukan pelaksana fisik rutin.

## Alur yang ringkas

Landing menampilkan ringkasan seluruh desa dan jenis sampah beserta harga aktif. Foto dikelola lewat **Admin → Data Master → Katalog Sampah → Jenis Sampah → Foto**; perubahan sumber yang sama tampil pada landing, katalog, dan harga saat halaman dimuat ulang. Jenis baru memerlukan foto; data lama tanpa foto ditandai sampai dilengkapi. [Panduan foto dan statistik](docs/LANDING_CATALOG_2026-10-09.md).

- Semua setoran langsung final setelah ringkasan berat/nilai diperiksa.
- Petugas/Bendahara mengambil tugas sendiri, tanpa persetujuan Admin rutin.
- Form penjemputan satu layar; foto, catatan, dan perkiraan tambahan dapat dilipat. Lampiran rutin opsional, tervalidasi, dan privat.
- Antrean Petugas/Bendahara dipaginasi dan memisahkan Menunggu/Tugas saya. Menu Layanan Warga memuat seluruh pilihan layanan.
- Menu Riwayat Petugas memisahkan pekerjaan selesai dari antrean aktif, dengan pencarian dan rincian hanya-baca. Tautan sembako selesai tetap dapat dibuka oleh pelaksananya.
- Pencatatan tunai/sembako menampilkan satu konfirmasi nama/nominal. Periksa lagi tidak mengubah saldo; foto tetap opsional dan tidak ada persetujuan Admin rutin.
- Admin membuat akun pengurus beserta tugas secara atomik. Pemeriksaan saldo selesai dengan satu Admin; selisih dicatat tanpa otomatis mengubah saldo.
- Form edit mempertahankan data tersimpan; bukti publik selalu diturunkan dari transaksi server, termasuk invalidasi QR.
- Pembayaran hanya tunai penuh, diambil ketika uang fisik tersedia. Tidak ada transfer, pembayaran sebagian, atau tombol tunda kas.
- Sembako dapat langsung diserahkan; Paket siap diambil adalah pilihan, bukan tahap wajib.
- Hold belum merupakan pengeluaran. Pelepasan klaim mempertahankan hold; pembatalan sah melepasnya. Setelah klaim, permintaan batal mengunci penyerahan sampai serah fisik dipastikan.
- Tidak ada expiry otomatis pengajuan layanan.

Claim mengikat pelaksana sebelum uang/barang berpindah. Setelah serah fisik, satu pencatatan atomik membuat mutasi keluar dan kuitansi. Respons hilang tidak boleh memicu serah fisik kedua; ketidakpastian masuk jalur exception Admin beralasan dan terautentikasi ulang.

Bot Telegram belum diimplementasikan dan WhatsApp tidak digunakan. Penimbangan serta serah-terima sembako tetap melalui web. Tes otomatis belum menggantikan UAT peserta desa nyata. Penyempurnaan UI 8 Oktober 2026 dilacak di [catatan pelaksanaan](docs/IMPLEMENTATION_UI_2026-10-08.md).

## Teknologi

Antarmuka akun memakai satu gaya Green Ledger: kartu layanan berikon, maskot lokal, contoh isian form, serta feedback hover/fokus dengan dukungan reduced motion. Prinsip hierarki dan interaksi Appllama diadaptasi untuk Blade/Livewire; tidak menambah framework atau mengubah aturan saldo. Pengujian responsif tiga peran tersedia di `tests/browser/app-ui-polish.mjs` dan hanya memakai database uji terpisah.

Laravel 13, PHP 8.3+, MySQL 8.0.30-compatible/InnoDB, Livewire 4, Filament 5, Tailwind CSS, dan Vite. Node.js diperlukan untuk build/pengujian, bukan runtime web produksi.

## Menjalankan lokal

1. Siapkan PHP, Composer, Node.js/npm dan MySQL lokal, misalnya Laragon.
2. Jalankan `composer install` dan `npm ci --ignore-scripts`.
3. Salin `.env.example` ke `.env`; isi koneksi lokal dan URL, lalu jalankan `php artisan key:generate`.
4. Siapkan data utama privat dari SQL pemilik mengikuti [panduan seeder](docs/SEEDING.md). Tanpa sumber ini seeder lokal berhenti; tidak menggantinya dengan akun buatan.
5. Pada database lokal kosong, jalankan `php artisan migrate --seed`, kemudian `npm run build`.
6. Document root Laragon harus `public/`; alternatifnya `php artisan serve`.

Hanya ada satu seeder aplikasi: `DatabaseSeeder`. Sumber lokal terbaru memuat 26 akun (20 Warga, 3 Petugas, 1 Bendahara, 2 Admin), 5 RW, 19 RT, penugasan, 4 jenis sampah, dan 4 harga. Superadmin lama menjadi Admin; username dan hash kata sandi sumber dipertahankan. QR dibuat ulang dengan kunci lokal. Transaksi, saldo, sesi, notifikasi, lampiran, dan paket sembako lama tidak diimpor.

Data akun/alamat dan hash kata sandi disimpan privat di `database/data/master-data.json`, diabaikan Git. Akun contoh untuk tes hanya berada di `tests/Fixtures`; tidak dipanggil seeder aplikasi. Tombol isi cepat demo hanya muncul untuk akun sintetis yang benar-benar cocok, bukan semua akun hasil impor. Di production, seeder biasa hanya bootstrap Admin awal sesuai konfigurasi. Update **hosting dummy lama** melalui File Manager menggunakan installer sementara yang diaktifkan eksplisit; lihat [panduan cPanel dan daftar upload](docs/HOSTING_CPANEL_2026-10-20.md). Installer bukan jalur reset untuk data operasional.

### Reset baseline dummy yang sudah ada

Simpan backup terlebih dahulu. Command ini hanya menerima local/testing, host loopback, tepat database `bank_sampah`, dan root media workspace.

```bash
php artisan app:reset-demo-baseline --confirm-reset-demo --dry-run
php artisan app:reset-demo-baseline --confirm-reset-demo
```

Tanpa dry-run data dan media dummy dihapus. Jangan dipakai untuk data nyata; migration cutover transaksi menolak baseline transaksi yang tidak kosong. Cutover schema tidak dapat di-rollback sebagian. [Panduan rilis/rollback](docs/DEPLOYMENT.md).

## Pengujian

```bash
composer test
composer analyse
php vendor/bin/pint --test
npm run build
npm run test:browser-guard
```

Unit/feature memakai SQLite memori dan storage/cache/sesi terpisah. MySQL/browser hanya memakai database disposable `bank_sampah_test` atau suffix-nya, URL loopback, dan token preflight yang sesuai. Runner browser: `npm run test:browser`; skenario locking nyata: `tests/mysql/cutover-concurrency.php`. Lihat [rencana tes](docs/TEST_PLAN.md) untuk batas bukti.

Proposal dan sembilan flowchart ada di [documents](documents/). Kuitansi/audit adalah jejak sistem, bukan bukti independen serah fisik. [UAT](docs/UAT_EVIDENCE.md) belum boleh diklaim selesai tanpa peserta nyata.

Jangan mengunggah `.env`, token, backup/database, log, atau kredensial nyata ke GitHub. Lisensi metadata package MIT; distribusi mengikuti kebijakan proyek.
