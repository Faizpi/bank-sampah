# Perbaikan UI dan resource lama pada hosting

## Bukti dan batas

Pemeriksaan HTTP publik pada 21 Oktober 2026: homepage 200, ketujuh URL `/images/landing/1.jpg` sampai `/images/landing/7.jpg` 404, sementara maskot, foto Kepala Desa, dan satu foto katalog yang ditampilkan berhasil dimuat. Ketujuh JPEG tersedia dan terbaca di source lokal; URL pada Blade sesuai. Ini menunjukkan berkas belum tersedia melalui document root hosting, bukan kebutuhan membuat foto/URL baru.

Local cutover menghapus tabel dan resource CRUD `roles`/`permissions` sejak commit `8771f54`; peran tetap dikelola melalui Pengguna. Error 500 pada dua URL lama dilaporkan pemilik, tetapi log privat hosting belum dibaca. Dugaan utama adalah file resource atau cache lama tertinggal saat upload. Tidak ada login Admin hosting, upload, penghapusan server atau reset database yang dilakukan oleh pemeriksaan ini.

Pemeriksaan GET tanpa login pada kedua URL hosting memberikan 302 ke login backoffice. Karena itu, exception 500 setelah autentikasi belum diamati langsung dan tidak dianggap terbukti selesai di server. Patch lokal diuji dengan autentikasi Admin serta database yang tidak mempunyai tabel role/permission dinamis.

## Perubahan kode

- Tombol Akses Panel Admin pada login menjadi selebar tombol Masuk ke Akun, dengan latar putih, teks hijau dan border hijau, memakai token Green Ledger yang ada.
- Panel menggunakan daftar eksplisit 26 resource aktif, bukan pemindaian semua PHP yang tertinggal pada server. Tambahkan resource baru ke `App\Filament\BackofficeResources` secara sadar; tes memeriksa registry sama dengan source saat ini.
- Dua URL lama `/backoffice/identity/models/roles` dan `/backoffice/identity/models/permissions` diarahkan ke Pengguna untuk Admin. Pengunjung harus login; Warga/Petugas/Bendahara tetap ditolak. Tidak ada tabel role/permission yang dibuat kembali.
- Selesaikan deployment juga membersihkan cache komponen Filament. Kesiapan runtime memeriksa tujuh foto hero di kedua salinan publik, bukan hanya build Vite.

## Upload yang diperlukan — tanpa reset/seed

1. Upload **ketujuh file JPEG** dari `public/images/landing/1.jpg`–`7.jpg` ke **dua** folder:
   - `/home/smssinde/public_html/images/landing/`
   - `/home/smssinde/bank-sampah/public/images/landing/`
   Nama file harus persis huruf kecil `.jpg`, bukan `.JPG`, `.jpeg`, atau di folder `landing/landing/`.
2. Upload file backend terbaru:
   - `app/Filament/BackofficeResources.php` (baru).
   - `app/Providers/Filament/BackofficePanelProvider.php`.
   - `routes/web.php`.
   - `resources/views/livewire/auth/login-form.blade.php`.
3. Upload seluruh build terbaru ke `public_html/build/` dan `bank-sampah/public/build/`. Tidak ada dependensi baru; vendor produksi kemarin tidak perlu dibangun ulang untuk patch ini.
4. Jika konsol sementara masih digunakan, upload `public/deploy.php` terbaru ke `public_html/deploy.php` dan `bank-sampah/public/deploy.php`.
5. Setelah backup berkas, pindahkan **hanya jika masih ada** folder resource lama berikut ke lokasi privat di luar folder yang dipindai aplikasi, atau hapus salinan usangnya:
   - `/home/smssinde/bank-sampah/app/Filament/Resources/Identity/Models/Roles/`
   - `/home/smssinde/bank-sampah/app/Filament/Resources/Identity/Models/Permissions/`
   Ini file PHP obsolete, bukan tabel database. Jangan menghapus folder `Users`, data akun, atau resource aktif lainnya. Registry eksplisit mencegah autodiscovery file ini; pembersihan fisik tetap dianjurkan agar versi server tidak tercampur.
6. Jika cache lama membuat bootstrap gagal, hapus **hanya cache generated** `bootstrap/cache/filament/panels/backoffice.php` dan cache route generated yang ada, bukan source bootstrap. Kemudian pilih **Selesaikan deployment**. Patch ini tidak memerlukan **Reset & isi data dummy**, `migrate:fresh`, atau seed; jangan jalankan opsi tersebut.
7. Jalankan **Periksa kesiapan runtime** dan **Validasi deployment**. Periksa langsung salah satu URL JPEG di browser, login, tampilan tombol pada HP/desktop, dan kedua URL lama saat login Admin. Keduanya harus berakhir pada halaman Pengguna tanpa menu role/permission dinamis.

Setelah berhasil, nonaktifkan izin installer, baca ulang/cache konfigurasi, dan hapus konsol sementara sesuai panduan deployment. `HOSTING_DEMO_INSTALL_ENABLED` tidak perlu dinyalakan untuk patch ini. Simpan `.env`, APP_KEY, sandi, token, database, storage unggahan, dan cron yang sudah berjalan.

## Hasil validasi lokal

- 173 tes Filament, cutover, landing, public shell dan konsol lulus: 1.431 assertions.
- Browser memakai server loopback sendiri dan MySQL disposable `bank_sampah_test_ui_20261021`; fixture sintetis terpisah dari seeder/data utama. Seluruh tujuh JPEG hero berhasil diunduh. Tombol diuji pada 360/390/768/1280 px: putih, teks/border hijau 2 px, tinggi minimal 48 px, lebar sama dengan tombol masuk, fokus keyboard, dan tanpa overflow.
- Login Admin dan GET kedua URL lama diuji setelah `route:cache`: redirect 302 ke Pengguna, halaman tujuan 200, tidak ada menu role/permission lama atau error JavaScript.
- PHPStan 0 error, Pint lulus, syntax konsol dan `git diff --check` lulus. `npm run build` selesai; `public/build` siap diupload.
- Screenshot diperiksa di `artifacts/hosting-ui-2026-10-21/`: hero desktop, login empat lebar, serta halaman Pengguna Admin. Ini bukti lokal, bukan UAT desa atau bukti patch telah terpasang di hosting.
- Konsol sementara diuji ulang pada server loopback: readiness termasuk foto hero dan preview read-only lulus. Database disposable dihapus setelah seluruh uji browser selesai; database utama tetap tidak diubah.
