# Validasi persiapan hosting — 20 Oktober 2026

## Lingkup dan batas

Persiapan kode/konsol sementara, dependensi produksi, build publik, dan panduan upload. Tidak ada autentikasi cPanel, upload hosting, reset database hosting, deployment, commit atau push pada pekerjaan ini. Tidak ada ZIP yang dibuat. Telegram tidak diimplementasikan.

Database utama lokal `bank_sampah` tidak diubah. Pemeriksaan SELECT saja menunjukkan 26 akun (20 Warga/3 Petugas/1 Bendahara/2 Admin), 19 RT/5 RW, 4 jenis sampah/4 harga, dan 0 metadata foto katalog.

## Perubahan

- Konsol menampilkan empat pilihan utama: kesiapan, reset+isi dummy, selesaikan deployment, buka website. Diagnostik/cache/migrasi biasa berada di menu tambahan.
- Token privat minimal 32 karakter, HTTPS di hosting, session CSRF dan allowlist aksi; tidak ada input Tinker/kode/command bebas. Pembacaan `.env` mengikuti repository lingkungan Laravel, sehingga tidak menimpa environment server yang sudah ditetapkan.
- Installer hosting dummy memerlukan izin sementara, nama database konfigurasi/koneksi/ketikan yang identik, sumber privat valid, pernyataan backup, dan frasa konfirmasi. Pemeriksaan sumber mendahului penghapusan. Maintenance tetap aktif setelah pemasangan; APP_KEY/media tidak dihapus.
- `DatabaseSeeder` tetap satu-satunya seeder aplikasi. Importer data utama digunakan bersama; bootstrap produksi biasa tetap hanya Admin awal. Reset hosting bukan clone transaksi/database lokal.
- Topologi sibling `bank-sampah`/`public_html` hanya diterima jika diaktifkan eksplisit dan source privat tidak terekspos. Front controller/manifest kedua salinan harus sama; readiness juga mencocokkan byte aset build yang dirujuk.

## Bukti pengujian lokal

| Pemeriksaan | Hasil |
|---|---|
| Seluruh suite Pest | 1.231 test: 1.230 lulus, 1 skipped; 5.780 assertions |
| Regresi terfokus konsol/seeding/topologi/operasi | 65 lulus; 322 assertions |
| MySQL installer | Lulus pada `bank_sampah_test_hosting_20261020`, bukan DB utama |
| Browser konsol | Lulus pada server loopback milik runner dan runtime terpisah |
| PHPStan | 0 error |
| Pint | Lulus |
| PHP syntax `public/deploy.php` | Lulus |
| `npm run build` | Lulus; manifest dan aset terbaru tersedia di `public/build` |
| Composer produksi | Install lockfile `--no-dev --optimize-autoloader --no-scripts` pada staging terpisah; cek platform lokal dan autoload lulus; PHPUnit tidak terpasang di vendor produksi |

MySQL smoke memeriksa penolakan izin mati/nama DB salah/environment local, dry-run tanpa perubahan, kewajiban backup/frasa, penolakan sumber hilang sebelum wipe, hilangnya marker dummy lama sesudah reset sah, jumlah data, hash sandi utuh, hanya empat peran, QR terenkripsi yang valid, transaksi kosong, APP_KEY utuh, maintenance dipertahankan, dan penolakan seed berulang tanpa reset.

Database disposable `bank_sampah_test_hosting_20261020` dihapus setelah seluruh uji MySQL/browser selesai; database utama tidak ikut dibersihkan.

Browser smoke memeriksa empat menu terlihat, menu tambahan tertutup, tidak ada overflow pada lebar 390/1280, input konfirmasi kondisional, respons token salah 403, CSRF salah 419, readiness runtime, preview sah tanpa maintenance, serta tidak ada error JavaScript. Browser tidak menjalankan reset; penghapusan diuji oleh runner MySQL terpisah.

Runner regresi: `tests/Feature/DeploymentConsoleTest.php`, `tests/Feature/Seeding/HostingDemoGuardTest.php`, `tests/Feature/SplitHostingTopologyTest.php`, `tests/mysql/hosting-demo-smoke.php`, dan `tests/browser/hosting-console-smoke.mjs`. Runner MySQL/browser wajib memakai database disposable dengan nama yang diizinkan; jangan arahkan ke hosting atau `bank_sampah` utama.

Vendor siap upload berada di `artifacts/hosting-production-2026-10-20/vendor/` (lokal/ignored). Source aplikasi tetap diambil dari root proyek terbaru, bukan dari staging. Sumber privat `database/data/master-data.json` tetap diabaikan Git dan tidak dicetak dalam laporan.

## Belum dibuktikan

Versi PHP/ekstensi dan izin filesystem hosting, kredensial/nama DB hosting aktual, domain/document root aktif, batas waktu request provider, cron hosting, pengiriman email, dan hasil klik empat peran setelah upload. Uji lokal bukan bukti UAT desa atau keberhasilan deployment. Ikuti [panduan cPanel](HOSTING_CPANEL_2026-10-20.md), backup dahulu, lalu hapus konsol setelah selesai.
