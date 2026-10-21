# Update hosting lewat File Manager

Panduan ini untuk hosting dummy lama yang belum dipakai, sesuai izin pemilik. Belum ada upload atau reset hosting yang dilakukan dari workspace lokal. `deploy.php` hanya pengganti terminal sementara; hapus setelah selesai.

## 1. Backup dan persiapan

- Ekspor database hosting melalui phpMyAdmin, lalu unduh backup kode, `.env`, dan `storage/`. Simpan di komputer atau lokasi privat, bukan `public_html`.
- Pastikan domain memakai HTTPS dan PHP **8.3 atau lebih baru**, dengan ekstensi termasuk `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `xml`, `dom`, `xmlreader`, `intl`, `zip`, `ctype`, `curl`, dan **GD**. Jangan memakai permission 777; umumnya folder 755, file 644, `.env` 600/640 sesuai hosting.
- Saat mengganti berkas, tutup sementara website menggunakan menu maintenance lama atau fasilitas hosting. Jika versi lama tidak bisa menjalankannya, gunakan fasilitas maintenance hosting sampai upload lengkap. Jangan reset sebelum semua berkas baru tersedia.
- Catat nama database **yang benar dari `.env` hosting/cPanel**, bukan `bank_sampah` lokal. Akun database hosting perlu izin membuat/menghapus tabel untuk reset ini.

## 2. Folder yang diupload

Sumber lokal: `C:\Projek Website\Bank-Sampah-Sindangheula`.
Screenshot menunjukkan `/home/smssinde/bank-sampah` dan `/home/smssinde/public_html`; cocokkan kembali dengan akun hosting aktual.

| Dari lokal | Tujuan hosting |
|---|---|
| `app/`, `config/`, `database/`, `lang/`, `resources/`, `routes/` | Folder dengan nama sama di `/home/smssinde/bank-sampah/` |
| `bootstrap/app.php`, `bootstrap/providers.php` | `/home/smssinde/bank-sampah/bootstrap/` |
| `artisan`, `composer.json`, `composer.lock` | `/home/smssinde/bank-sampah/` |
| `artifacts/hosting-production-2026-10-20/vendor/` yang sudah disiapkan | `/home/smssinde/bank-sampah/vendor/`; **jangan pertahankan vendor versi hosting lama** |
| `public/` beserta isi dan build terbarunya | `/home/smssinde/bank-sampah/public/` |
| **Isi** `public/` yang sama | Langsung ke `/home/smssinde/public_html/`, **bukan** ke `public_html/public/` |
| `database/data/master-data.json` privat | `/home/smssinde/bank-sampah/database/data/master-data.json` |

Isi publik yang dibutuhkan: `build/`, `css/`, `fonts/`, `icons/`, `images/`, `js/`, `.htaccess`, `index.php`, `favicon.ico`, `manifest.webmanifest`, `sw.js`, dan `deploy.php` sementara. Folder `images/` mencakup foto hero terbaru. `public/bank-sampah-maskot/` adalah bahan kerja lokal yang diabaikan Git; tidak perlu diupload.

**Mengapa dua salinan public?** `public_html` melayani browser, sedangkan Laravel membaca manifest dan aset paket dari `bank-sampah/public`. Keduanya harus berasal dari build yang sama. `index.php` sudah mendukung struktur dua folder ini.

Untuk folder kode dan `vendor`, ganti folder versi lama sesudah backup agar tidak menyisakan kelas lama; jangan menghapus seluruh akun hosting atau `public_html`. Pertahankan `.well-known/`, `cgi-bin/`, direktori domain lain seperti `a.sms-sindangheula.id/`, dan aturan hosting khusus. Saat mengganti `.htaccess`, pertahankan blok handler PHP/HTTPS buatan cPanel yang masih diperlukan.

**Jangan upload:** `.env` lokal, `.git`, `node_modules`, `tests`, `artifacts`, dump SQL, backup, dokumen skripsi, `public/hot`, atau log/cache/sesi dari komputer. Jangan menimpa `storage/` hosting dengan storage lokal.

Pastikan direktori runtime berikut ada dan writable oleh PHP:
`bootstrap/cache`, `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `storage/logs`, `storage/app/private`, `storage/app/public`, `storage/app/media`, dan `storage/app/waste-catalog`.

Sebelum membuka konsol baru, hapus **hanya file cache PHP hasil generate** di `bank-sampah/bootstrap/cache/` (misalnya `config.php`, `packages.php`, `services.php`, `routes-v7.php`). Jangan hapus folder, `.gitignore`, atau file sumber bootstrap. Ini menghindari konfigurasi/path/provider versi lama saat aplikasi pertama kali dimuat.

### Vendor tanpa terminal hosting

Untuk versi ini, folder **`artifacts/hosting-production-2026-10-20/vendor/` sudah disiapkan** dari `composer.lock` dengan `--no-dev --optimize-autoloader --no-scripts`; cukup upload isi folder `vendor` tersebut sebagai `bank-sampah/vendor`. Tidak ada ZIP yang dibuat. Folder ini lokal/diabaikan Git; source lain tetap ambil dari root proyek terbaru, bukan dari staging artifacts.

Jika dependensi berubah pada rilis berikutnya, siapkan ulang di komputer menggunakan **salinan proyek terpisah** dan PHP 8.3+, lalu `composer install --no-dev --optimize-autoloader --no-scripts` pada salinan tersebut. Jangan mengurangi dependensi workspace pengembangan yang masih dipakai untuk tes. Laravel membangun ulang package discovery/cache saat aplikasi dimuat; jangan upload cache bootstrap hasil komputer.

## 3. Edit .env hosting, bukan menyalin .env lokal

Pertahankan **APP_KEY lama** dan kredensial DB hosting. Jangan memakai `key:generate`, `APP_ENV=local`, atau root MySQL lokal di hosting. Gabungkan nilai berikut ke `.env` yang sudah ada, tanpa membuat baris duplikat:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMAIN-WEBSITE-YANG-BENAR
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_DEMO_MODE=false
APP_MAINTENANCE_DRIVER=file
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
OPERATIONS_QUEUE_WORKER_MODE=none
SCHEDULER_TOPOLOGY=cron
DEPLOYMENT_LAYOUT=split
DEPLOYMENT_RELEASE_ROOT=/home/smssinde/bank-sampah
DEPLOYMENT_DOCUMENT_ROOT=/home/smssinde/public_html
MASTER_SEED_PATH=/home/smssinde/bank-sampah/database/data/master-data.json
HOSTING_DEMO_INSTALL_ENABLED=true
HOSTING_DEMO_DATABASE=NAMA_DATABASE_HOSTING_PERSIS
DEPLOY_CONSOLE_TOKEN=TOKEN_ACAK_PRIVAT_MINIMAL_32_KARAKTER
```

Ganti placeholder domain, database, dan token; token contoh bukan token siap pakai. `HOSTING_DEMO_DATABASE` harus persis sama dengan `DB_DATABASE` hosting dan database koneksi aktual. Pastikan `DB_URL` lama tidak mengarah ke database lain. Jika pernah mengatur `VITE_MANIFEST_PATH` atau path cache/storage absolut, sesuaikan/hapus override lama yang tidak relevan. Semua source data tetap di luar `public_html`.

## 4. Klik melalui deploy.php

Buka `https://DOMAIN-WEBSITE-YANG-BENAR/deploy.php`. Masukkan token untuk setiap aksi.

1. **Aksi tambahan → Baca ulang .env** jika konfigurasi pernah di-cache. Aksi berikutnya akan membaca konfigurasi baru.
2. **Periksa kesiapan runtime**. Koneksi, PHP, aset, dan direktori harus benar; tabel session/cache lama bisa belum ada sebelum reset. Sesudah reset seluruh pemeriksaan harus lulus. Jika ragu terhadap sumber, gunakan **Aksi tambahan → Cek data dummy sebelum dipasang**, lalu ketik nama database.
3. **Reset & isi data dummy**. Ketik nama database hosting, centang backup yang benar-benar sudah disimpan, lalu ketik `INSTALL HOSTING DUMMY`. Ini menghapus **seluruh tabel pada database tersebut**, membuat skema terbaru dan memasang data utama; **tidak perlu seed lagi**. Maintenance tetap aktif.
4. **Selesaikan deployment**. Tunggu migrasi dan seluruh cache selesai tanpa kegagalan.
5. Ulangi **Periksa kesiapan runtime** dan **Aksi tambahan → Validasi deployment**. Setelah lulus, **Buka website**.
6. Coba landing, katalog, harga, login keempat peran, serta unggah foto melalui Admin. Uji transaksi hanya pada baseline dummy ini. Jika tampilan masih lama, lakukan hard refresh/hapus cache situs pada browser.
7. Set `HOSTING_DEMO_INSTALL_ENABLED=false`, kosongkan `HOSTING_DEMO_DATABASE` dan jalankan **Baca ulang .env → Selesaikan deployment** lagi. Terakhir kosongkan token di `.env`, hapus `public_html/deploy.php` serta salinan `bank-sampah/public/deploy.php`. Jangan sertakan kembali konsol ini pada upload rutin berikutnya.

Jika salah satu aksi gagal, berhenti dan baca output/log. Jangan langsung membuka maintenance atau mengulang reset berulang kali. Pulihkan backup kode + database + media sebagai pasangan bila perlu; migration cutover bukan jalur rollback ke schema lama.

## 5. Data dan scheduler

JSON sumber saat ini memuat **26 akun** (20 Warga, 3 Petugas, 1 Bendahara, 2 Admin), **5 RW/19 RT**, wilayah/penugasan, **4 jenis sampah dan 4 harga**. Username/hash kata sandi dipertahankan; Superadmin sumber sudah menjadi Admin. QR baru menggunakan APP_KEY hosting; QR lama tidak berlaku.

Ini **bukan clone database lokal**: transaksi, saldo, paket sembako dan metadata foto yang ditambahkan lewat panel tidak ada di JSON. Foto hero/desain ikut upload publik. Foto katalog unggahan perlu dipasang kembali lewat Admin setelah reset; menyalin folder gambar saja tidak membuat metadata database-nya. Tidak ada transaksi fiktif yang dibuat oleh installer.

Tambahkan satu **cPanel Cron Job**, setiap menit, menggunakan executable PHP 8.3+ yang benar dari hosting:

```text
* * * * * /PATH/PHP-HOSTING /home/smssinde/bank-sampah/artisan schedule:run >> /dev/null 2>&1
```

Kolom jadwal di cPanel adalah `*` untuk menit/jam/hari/bulan/hari pekan; kolom Command hanya bagian `/PATH/PHP-HOSTING ...`. Minta provider path PHP bila tidak tersedia. Scheduler menjalankan kedaluwarsa layanan/hold dan pembersihan berkala; tidak perlu worker daemon karena queue memakai `sync`. Tombol scheduler sekali di konsol bukan pengganti cron berulang. Telegram belum dibuat pada tahap ini.
