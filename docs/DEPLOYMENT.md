# Deployment dan rollback

Kode deployment tersedia, tetapi pengujian lokal bukan bukti hosting sudah diperbarui. Jangan menjalankan reset demo pada data nyata. Untuk update hosting dummy lama melalui File Manager dan konsol sementara, ikuti [panduan cPanel](HOSTING_CPANEL_2026-10-20.md).

Jika baseline sudah terpasang dan masalahnya foto hero/tombol login/resource Role-Permission lama, gunakan [patch hosting 21 Oktober](HOSTING_UI_FIX_2026-10-21.md). Patch tersebut tidak memerlukan reset database atau seed ulang.

## Persiapan rilis

Validasikan PHP 8.3+ dan GD, MySQL/InnoDB, root publik `public/`, storage privat, HTTPS, konfigurasi, build aset, backup, logging, dan scheduler. Layout cPanel sibling `bank-sampah`/`public_html` harus diaktifkan eksplisit dengan `DEPLOYMENT_LAYOUT=split`; front controller dan manifest kedua salinan publik harus cocok, tanpa source privat di document root. Jalankan tes pada database disposable; UAT dengan pengguna nyata tetap tahap tersendiri. Kredensial demo tidak boleh menjadi akun produksi.

## Cutover

Migration transaksi cutover sengaja menolak data transaksi yang tidak kosong. Untuk data nyata diperlukan rencana migrasi tersendiri, bukan reset. Command reset baseline lokal tetap terbatas ke lokal. Khusus hosting lama yang sudah dinyatakan dummy oleh pemilik, installer `app:install-hosting-demo` memerlukan izin sementara, sumber privat valid, kecocokan nama database konfigurasi/koneksi/ketikan operator, backup, serta frasa konfirmasi. Konsol tidak menerima kode Tinker/command bebas. Sumber divalidasi sebelum penghapusan dan maintenance dipertahankan sampai dibuka operator.

`db:seed` produksi biasa tetap hanya bootstrap Admin dari `APP_INITIAL_ADMIN_*`; tidak mengganti sandi, menaikkan role, atau mengaktifkan kembali akun yang ada. Installer hosting dummy adalah jalur eksplisit terpisah yang memakai importer data utama yang sama, bukan seeder aplikasi kedua. Hapus konsol dan matikan izin installer setelah selesai.

## Rollback

Cutover schema bersifat irreversible. Pulihkan kode checkpoint dan backup database/media yang cocok bersama-sama; jangan rollback sebagian migration atau menghapus transaksi final. Backup lokal dan kredensial tidak di-commit.
