# Deployment dan rollback

Kode deployment tersedia, tetapi pengujian lokal bukan bukti hosting sudah diperbarui. Jangan menjalankan reset demo pada data nyata. Untuk update hosting dummy lama melalui File Manager dan konsol sementara, ikuti [panduan cPanel](HOSTING_CPANEL_2026-10-20.md).

Jika baseline sudah terpasang dan masalahnya foto hero/tombol login/resource Role-Permission lama, gunakan [patch hosting 21 Oktober](HOSTING_UI_FIX_2026-10-21.md). Patch tersebut tidak memerlukan reset database atau seed ulang.

## Persiapan rilis

Validasikan PHP 8.3+ dan GD, MySQL/InnoDB, root publik `public/`, storage privat, HTTPS, konfigurasi, build aset, backup, logging, dan scheduler. Layout cPanel sibling `bank-sampah`/`public_html` harus diaktifkan eksplisit dengan `DEPLOYMENT_LAYOUT=split`; front controller dan manifest kedua salinan publik harus cocok, tanpa source privat di document root. Jalankan tes pada database disposable; UAT dengan pengguna nyata tetap tahap tersendiri. Kredensial demo tidak boleh menjadi akun produksi.

## Cutover

Migration transaksi cutover sengaja menolak data transaksi yang tidak kosong. Untuk data nyata diperlukan rencana migrasi tersendiri, bukan reset. Command reset baseline lokal tetap terbatas ke lokal. Khusus hosting lama yang sudah dinyatakan dummy oleh pemilik, installer `app:install-hosting-demo` memerlukan izin sementara, sumber privat valid, kecocokan nama database konfigurasi/koneksi/ketikan operator, backup, serta frasa konfirmasi. Konsol tidak menerima kode Tinker/command bebas. Sumber divalidasi sebelum penghapusan dan maintenance dipertahankan sampai dibuka operator.

`db:seed` produksi biasa tetap hanya bootstrap Admin dari `APP_INITIAL_ADMIN_*`; tidak mengganti sandi, menaikkan role, atau mengaktifkan kembali akun yang ada. Installer hosting dummy adalah jalur eksplisit terpisah yang memakai importer data utama yang sama, bukan seeder aplikasi kedua. Hapus konsol dan matikan izin installer setelah selesai.

## Telegram (aktivasi terpisah)

Kode bot nonaktif secara default. Migration awal menambah tabel; pembaruan 28 Oktober menonaktifkan penanda waktu lama tanpa reset atau penghapusan tautan/transaksi. Jangan aktifkan pada hosting sebelum pemberitahuan privasi versi Telegram disetujui/dipublikasikan, backup tersedia, token disimpan privat, domain HTTPS dapat dijangkau Telegram, dan UAT disetujui. Admin tetap memakai panel web.

Setelah persetujuan, isi `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, dan secret acak minimal 32 karakter di `.env` server saja; set `TELEGRAM_ENABLED=true`, bersihkan config cache, jalankan migration yang additive, lalu `php artisan telegram:diagnose` dan `php artisan telegram:webhook`. Pastikan scheduler cron setiap menit tetap aktif agar update, pemberitahuan, dan retensi diproses. Jangan meletakkan token di URL, command argument, log, repo, atau chat. Untuk mematikan dengan tertib, jalankan `telegram:webhook --remove` saat konfigurasi masih tersedia, kemudian matikan fitur dan bersihkan cache konfigurasi. Langkah lengkap ada di [TELEGRAM_CHANNEL.md](TELEGRAM_CHANNEL.md).

## Update akses persisten 28 Oktober 2026

Update Telegram 28 Oktober 2026 memakai migration `2026_10_28_100000_make_telegram_link_access_persistent`. Backup, aktifkan maintenance, unggah kode/build yang cocok, atur `TERMS_VERSION=v2.1` bila dikunci di `.env`, lalu jalankan migration biasa dan bangun ulang cache. Tidak perlu reset/seed, mengganti APP_KEY, memasang ulang webhook, atau menautkan ulang akun lama. Pastikan tautan lama bisa membuka menu dan membuat pengajuan setelah update. Kolom penanda waktu lama dipertahankan nullable/tidak aktif agar rollback schema terpisah tetap tersedia; bukan alasan untuk me-rollback migration cutover yang irreversible.

## Rollback

Cutover schema bersifat irreversible. Pulihkan kode checkpoint dan backup database/media yang cocok bersama-sama; jangan rollback sebagian migration atau menghapus transaksi final. Backup lokal dan kredensial tidak di-commit.
