<p align="center">
  <img src="./public/images/landing/mascot-6.png" width="180" alt="Maskot Bank Sampah Digital Sindangheula">
</p>

<h1 align="center">Bank Sampah Digital Sindangheula</h1>

<p align="center">Sistem informasi layanan bank sampah Desa Sindangheula yang transparan dan dapat ditelusuri.</p>

<p align="center">
  <a href="docs/README.md">Dokumentasi</a> |
  <a href="docs/PRODUCT.md">Produk</a> |
  <a href="docs/DEPLOYMENT.md">Deployment</a> |
  <a href="docs/TEST_PLAN.md">Pengujian</a>
</p>

> **Status: belum siap produksi.** Proyek masih dalam baseline pengembangan. Kesiapan tiap alur bergantung pada implementasi, pengujian, UAT, dan validasi deployment yang terdokumentasi.

## Ringkasan

Aplikasi web mobile-first ini mendukung informasi publik, layanan warga, pekerjaan lapangan, setoran, saldo, penjemputan, pencairan, penukaran sembako, pelaporan, dan audit. Warga tanpa smartphone tetap dapat dilayani petugas melalui nomor atau kartu nasabah serta bukti transaksi.

## Fitur menurut peran

| Peran | Ringkasan fitur |
| --- | --- |
| Publik | Katalog dan harga sampah, pengumuman, jadwal layanan keliling, statistik agregat, verifikasi bukti QR. |
| Warga | Registrasi, saldo dan riwayat, estimasi, penjemputan, pencairan, bukti transaksi, kartu nasabah, penukaran sembako. |
| Petugas | Pencarian nasabah, setoran multi-item, tugas penjemputan, penukaran, dan layanan keliling. |
| Bendahara | Verifikasi dan pencatatan pencairan, bukti pembayaran, laporan sesuai permission. |
| Back-office | Verifikasi warga, bantuan kata sandi, role dan permission, wilayah, harga, pencairan, penukaran, audit, dan rekonsiliasi. Health hanya tersedia sebagai UI teknis untuk Superadmin. |

## Stack dan kebutuhan runtime

- Laravel 13, PHP `^8.3` atau PHP 8.3+.
- MySQL 8.0.30-compatible, InnoDB, dan `utf8mb4`.
- Blade, Livewire 4, Filament 5, Tailwind CSS, dan Vite.
- Media, bukti, dan file sensitif disimpan di storage privat.
- Hosting harus menjadikan `public/` sebagai document root dan memberi akses tulis hanya pada `storage/` serta `bootstrap/cache/`.
- Node.js hanya diperlukan untuk build Vite di lokal atau CI, bukan sebagai runtime production.

## Mulai cepat di lokal

Prasyarat: PHP 8.3+, Composer 2, Node.js dan npm, serta MySQL yang kompatibel. Salin `.env.example` menjadi `.env`, lalu isi koneksi database lokal.

```bash
composer setup
composer dev
```

`composer setup` menyiapkan dependency, environment lokal, key aplikasi, migrasi, dan aset. `composer dev` menjalankan lingkungan pengembangan lokal.

## Verifikasi penting

```bash
composer check
npm run build
php artisan migrate:status
```

Build Vite harus menghasilkan `public/build/manifest.json`. Pengujian SQLite tidak membuktikan kompatibilitas MySQL production, lakukan rehearsal pada MySQL 8.0.30-compatible sebelum rilis.

## Perubahan untuk upload hosting

Gunakan artefak release yang dibangun dari commit atau tag yang disetujui. Untuk setiap perubahan **kode aplikasi, konfigurasi, dependency, aset, dokumentasi, atau PDF**, unggah **seluruh artefak release**, bukan hanya file PDF. Ini menjaga source, `composer.lock`, hasil build Vite, dan dokumen release tetap sinkron.

### Checklist perubahan

- [ ] **Wajib unggah seluruh artefak release:** perubahan pada `app/`, `bootstrap/`, `config/`, `database/`, `resources/`, `routes/`, `public/`, `composer.json`, `composer.lock`, atau dependency/aset yang dibangun.
- [ ] **Wajib unggah seluruh artefak release:** perubahan pada `docs/` atau `documents/*.pdf`; jangan mengunggah PDF saja.
- [ ] **Dokumen atau PDF saja:** tetap buat artefak release lengkap dan unggah seluruhnya. Tidak perlu migration atau build ulang cache bila kode, dependency, aset, dan konfigurasi tidak berubah.
- [ ] Keluarkan `.env`, cache lokal, log, database lokal, `node_modules`, artefak test, dan secret dari artefak.
- [ ] Pastikan `public/build/manifest.json` sudah ada dari build lokal atau CI.

### Urutan deploy aman

1. Verifikasi PHP web dan CLI memenuhi `^8.3`, MySQL kompatibel, HTTPS aktif, storage privat tersedia, dan document root dapat diarahkan hanya ke `public/`.
2. Bangun serta verifikasi artefak, lalu unggah ke direktori nonpublik. Simpan `.env` production secara privat.
3. Jalankan `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` dari root aplikasi. Jangan menjalankan `composer update` di production.
4. Arahkan document root ke `public/`; pastikan file aplikasi dan data privat tidak dapat diakses dari web.
5. Jalankan `php artisan migrate:status`, tinjau kompatibilitas schema dan rencana rollback, lalu jalankan `php artisan migrate --force` hanya setelah review tersebut selesai.
6. Jalankan `php artisan optimize:clear`, `php artisan config:cache`, `php artisan route:cache`, dan `php artisan view:cache`.
7. Jika scheduler digunakan, pasang cron provider untuk `php artisan schedule:run`; kemudian verifikasi database, storage privat, Health, dan aset sebelum membuka trafik.

## Dokumentasi lengkap

- [Pusat dokumentasi](docs/README.md)
- [Kontrak produk](docs/PRODUCT.md)
- [Arsitektur](docs/ARCHITECTURE.md)
- [Panduan deployment dan rollback](docs/DEPLOYMENT.md)
- [Keamanan](docs/SECURITY.md)
- [Rencana pengujian](docs/TEST_PLAN.md)
- [Operasi](docs/OPERATIONS.md)

Lisensi pada metadata package adalah MIT. Distribusi dan notice final mengikuti kebijakan lisensi proyek.
