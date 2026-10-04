<p align="center">
  <img src="./public/images/landing/mascot-6.png" width="220" alt="Maskot Bank Sampah Digital Sindangheula dengan ilustrasi pengelolaan sampah berkelanjutan">
</p>

<h1 align="center">Bank Sampah Digital Sindangheula</h1>

<p align="center">
  Sistem Informasi Bank Sampah Digital Desa Sindangheula untuk layanan yang mudah diakses, transparan, dan dapat ditelusuri.
</p>

<p align="center">
  <a href="docs/README.md">Dokumentasi</a> |
  <a href="docs/PRODUCT.md">Kontrak produk</a> |
  <a href="docs/ARCHITECTURE.md">Arsitektur</a> |
  <a href="docs/TEST_PLAN.md">Rencana pengujian</a>
</p>

## Tentang proyek

Bank Sampah Digital Sindangheula adalah aplikasi web mobile-first dan Progressive Web App yang dapat dipasang. Aplikasi ini mendukung layanan bank sampah di Desa Sindangheula, Kecamatan Pabuaran, Kabupaten Serang, Provinsi Banten.

Fokusnya adalah menghubungkan informasi publik, pelayanan warga, pekerjaan lapangan, pencatatan setoran, saldo, penjemputan, pencairan, penukaran sembako, pelaporan, dan audit dalam satu alur yang terdokumentasi. Warga tanpa smartphone tetap dapat menerima pelayanan berbantuan dari petugas melalui nomor atau kartu nasabah dan bukti transaksi.

> **Status:** baseline pengembangan dengan ruang lingkup dan kontrak teknis yang terdokumentasi. Status tiap alur mengikuti hasil implementasi, pengujian, UAT, dan validasi deployment yang tercatat pada dokumentasi proyek.

## Kapabilitas berdasarkan pengguna

| Pengguna | Kapabilitas utama |
| --- | --- |
| **Publik** | Melihat katalog sampah, harga, pengumuman, jadwal Bank Sampah Keliling, target dan statistik agregat, serta memverifikasi bukti setoran melalui QR. |
| **Warga** | Registrasi dan login, melihat saldo serta riwayat setoran, membuat estimasi nilai, mengajukan penjemputan, mengajukan pencairan, melihat bukti, mengajukan dan memantau penukaran sembako, melihat kartu nasabah, serta menerima notifikasi dan pengingat dasar. |
| **Petugas** | Mengakses dashboard tugas, mengidentifikasi nasabah melalui pencarian nomor atau kartu, mencatat setoran multi-item, mengelola tugas penjemputan, menyiapkan penukaran sembako, dan menjalankan layanan keliling. |
| **Bendahara** | Melihat dashboard pencairan, memeriksa pengajuan yang telah disetujui, memverifikasi penerima, mencatat pembayaran tunai, menerbitkan bukti, dan mengakses laporan sesuai permission. |
| **Back-office** | Admin mengelola verifikasi warga, bantuan password, sesi, role dan permission, wilayah, pickup dan kapasitas, master sampah dan harga, pencairan, paket dan penukaran sembako, audit log, serta rekonsiliasi. Superadmin mendapat tooling operasional untuk pemeliharaan, backup, dan kontrol teknis tanpa mengubah saldo di luar mekanisme koreksi. |

## Nilai produk

- Transparansi saldo rupiah, riwayat, status, dan koreksi transaksi.
- Akses layanan melalui HP dan laptop dengan antarmuka yang mobile-first.
- Pencatatan setoran dengan snapshot harga, bukti, saldo masuk, dan jejak audit.
- Alur kerja petugas yang task-first untuk setoran, penjemputan, penukaran, dan layanan keliling.
- Pelayanan berbantuan untuk warga yang tidak memiliki smartphone.
- Data agregat untuk evaluasi partisipasi desa, pengumpulan sampah, dan program Bank Sampah Keliling.

## Stack teknologi

| Lapisan | Teknologi atau keputusan |
| --- | --- |
| Framework | Laravel 13 |
| Runtime minimum | PHP 8.3+ sesuai kontrak Composer (`^8.3`) |
| Target lokal dan CI | PHP 8.5 |
| UI publik, warga, dan petugas | Blade dan Livewire 4 |
| Interaksi ringan | Alpine.js bawaan Livewire |
| Back-office | Filament 5 dengan tema khusus |
| Styling | Tailwind CSS 4.1+ |
| Build aset | Vite dan npm, dijalankan di lokal atau CI |
| Database | MariaDB terkelola yang kompatibel dengan MySQL |
| Media | Storage privat untuk bukti, foto, dan file sensitif |
| Pengujian | Pest 4 |
| Target hosting | Hostinger Web Hosting Premium atau Business, dengan capability check deployment |

PHP 8.3+ adalah batas minimum dependency aplikasi. Dokumentasi proyek saat ini menetapkan PHP 8.5 sebagai target lokal dan CI. Versi PHP web dan CLI pada hosting harus memenuhi kontrak yang sama.

## Batas arsitektur

Aplikasi dibangun sebagai **modular monolith Laravel**. Boundary ini menjaga transaksi, permission, dan ledger tetap berada di sisi server tanpa menambah layanan yang tidak diperlukan untuk target shared hosting.

```text
UI publik, warga, petugas, dan Filament back-office
    -> validasi Livewire atau request
    -> policy, permission, dan scope record
    -> application action atau use case
    -> domain service, value object, dan state machine
    -> model Eloquent dan query object
    -> MariaDB

Application action
    -> transaction, lock, idempotency, dan audit
    -> event setelah commit
    -> notifikasi, ekspor, atau pengingat
    -> filesystem abstraction untuk media privat
```

Batas penting:

- UI tidak menulis model finansial secara langsung. Action atau use case menjadi boundary untuk alur kritis.
- Harga, berat, rupiah, status, pembulatan, dan transisi dikelola oleh domain service atau value object.
- Operasi ledger menggunakan transaction, row lock, dan unique source agar perubahan tidak tercatat ganda.
- Bukti transaksi, foto penjemputan, bukti pembayaran atau penyerahan, ekspor, dan backup tidak berada pada disk publik.
- Media privat diakses melalui route yang terotorisasi atau signed temporary URL.
- Back-office memakai Filament 5, tetapi resource dan action tetap menggunakan policy serta application action yang sama.
- WhatsApp hanya dibentuk sebagai tautan `wa.me` untuk dikirim manual oleh pengguna.

### PWA dengan cache terbatas

Manifest aplikasi menyediakan instalasi PWA. Service worker hanya meng-cache halaman informasi umum yang masuk allowlist, aset build yang terversi, dan ikon publik. Halaman publik menggunakan network-first dengan fallback cache terbatas.

Autentikasi, profil, saldo, transaksi, pengajuan, notifikasi, Livewire update, file privat, dan signed URL menggunakan network-only. Tidak ada antrean offline, sinkronisasi transaksi setelah kembali online, atau transaksi finansial offline.

## Menyiapkan lingkungan lokal

Prasyarat:

- PHP 8.3+ dengan PHP 8.5 sebagai target lokal dan CI.
- Composer 2.
- Node.js dan npm untuk memasang dependency frontend serta membangun aset lokal.
- MariaDB atau database yang kompatibel dengan MySQL untuk alur yang mengikuti target deployment.

Siapkan `.env` lokal dari `.env.example` dan isi koneksi database sesuai lingkungan pengembangan. Jangan menaruh secret, password, atau token pada repository, README, issue, atau command history.

### Instalasi cepat

Script berikut menjalankan instalasi Composer, menyediakan `.env` jika belum ada, membuat application key, menjalankan migrasi, memasang dependency frontend tanpa lifecycle script, lalu membangun aset Vite.

```bash
composer setup
```

### Mode pengembangan

`composer dev` menjalankan server aplikasi, Laravel Pail, dan Vite secara bersamaan untuk pengembangan lokal.

```bash
composer dev
```

Node.js diperlukan untuk build aset di lokal atau CI. Node.js bukan runtime yang diperlukan pada server produksi sesuai kontrak deployment.

## Kebijakan seeder pengembang

`DeveloperUsersSeeder` hanya untuk pengujian lokal atau lingkungan non-produksi. Seeder ini membuat akun pengembangan untuk role baseline, role dan permission, serta profil dan wilayah pengembangan yang diperlukan untuk menguji alur secara menyeluruh.

Jalankan hanya ketika membutuhkan data akun pengembangan:

```bash
php artisan db:seed --class='Database\Seeders\DeveloperUsersSeeder'
```

Seeder dilewati pada environment production. Kata sandi bersama dan rincian kredensial tidak ditulis di README ini. Jangan menyalin kredensial pengembangan ke production atau membagikannya melalui log, issue, dokumentasi publik, atau chat. Kebijakan seed production mengikuti [panduan deployment](docs/DEPLOYMENT.md) dan tidak menggunakan data demo.

## Pengujian dan quality gate

Perintah yang tersedia di `composer.json`:

| Perintah | Pemeriksaan |
| --- | --- |
| `composer test` | Membersihkan konfigurasi lalu menjalankan `php artisan test`. |
| `composer analyse` | Menjalankan PHPStan melalui konfigurasi Larastan dengan batas memori 512 MB. |
| `composer check` | Menjalankan Pint dalam mode test, static analysis, dan seluruh test. |
| `npm run build` | Membuat build aset produksi melalui Vite. |

Jalankan quality gate utama dengan:

```bash
composer check
npm run build
```

Validasi MariaDB disposable, pemeriksaan browser atau E2E, UAT, deployment rehearsal, dan bukti operasional mengikuti [TEST_PLAN.md](docs/TEST_PLAN.md) serta [DEPLOYMENT.md](docs/DEPLOYMENT.md). Hasil SQLite lokal tidak dengan sendirinya membuktikan kompatibilitas MariaDB.

## Dokumentasi

- [Pusat dokumentasi](docs/README.md), status baseline, aturan penggunaan dokumen, dan peta seluruh kontrak.
- [Definisi produk](docs/PRODUCT.md), pengguna, nilai, baseline fitur, dan batas ruang lingkup.
- [Arsitektur sistem](docs/ARCHITECTURE.md), modular monolith, boundary modul, storage, PWA, queue, cron, dan quality gate.
- [Deployment](docs/DEPLOYMENT.md), capability hosting, document root, konfigurasi deployment, backup, rollback, dan checklist go-live.
- [Keamanan](docs/SECURITY.md), kontrol aplikasi, data, file, QR, publik, backup, dan insiden.
- [Ketentuan dan privasi](docs/TERMS_AND_PRIVACY.md), Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0.
- [Design system](docs/DESIGN.md), token, shell, komponen, responsive behavior, dan aksesibilitas.
- [Rencana pengujian](docs/TEST_PLAN.md), strategi test, UAT, coverage, dan traceability.

## Batasan yang perlu diketahui

- Aplikasi ini bukan aplikasi native Android atau iOS.
- PWA tidak menyediakan transaksi offline atau sinkronisasi finansial setelah perangkat kembali online.
- WhatsApp menggunakan tautan manual. Gateway pengiriman otomatis dan status pengiriman bukan bagian dari baseline.
- Fitur sembako menggunakan paket deskriptif dan penukaran sederhana, bukan pengelolaan stok terperinci.
- Sistem menyediakan data agregat untuk mendukung evaluasi program desa, tetapi tidak mengelola produksi, formula, kualitas, stok, distribusi, atau biaya paving block.
- Sistem bukan akuntansi desa menyeluruh.
- Ketersediaan deployment bergantung pada kemampuan provider untuk menyediakan PHP yang kompatibel, database MariaDB atau MySQL-compatible, document root yang hanya menyajikan `public/`, storage privat, permission runtime, HTTPS, dan cron bila dibutuhkan.

## Catatan keamanan

- Data dan action yang dilindungi menggunakan autentikasi, policy, permission granular, scope record, validasi, rate limit, dan session fresh sesuai alurnya.
- Operasi finansial memakai transaction, idempotency, lock, dan audit. Saldo tidak boleh diedit sebagai angka bebas di luar mekanisme koreksi.
- Data publik menggunakan allowlist dan agregasi. Verifikasi QR hanya menampilkan data bukti yang terbatas.
- Media privat tidak disajikan sebagai aset publik. Route file memeriksa sesi dan otorisasi sebelum memberikan akses.
- `.env`, `vendor`, `storage` privat, backup, dan source aplikasi harus berada di luar document root publik.
- Log tidak boleh memuat password, cookie, token, secret, isi file, identitas lengkap, atau payload finansial sensitif.

Detail kontrol dan prosedur insiden tersedia di [docs/SECURITY.md](docs/SECURITY.md).

## Status dan lisensi

**Status:** baseline pengembangan Bank Sampah Digital Sindangheula. README ini tidak menyatakan bahwa seluruh fitur sudah selesai, seluruh pengujian sudah lulus, atau aplikasi sudah siap menerima trafik produksi.

**Lisensi:** metadata package pada `composer.json` mencantumkan `MIT`. README ini tidak menambahkan berkas `LICENSE`; penetapan notice dan distribusi final mengikuti kebijakan lisensi proyek.
