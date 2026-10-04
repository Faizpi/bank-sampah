# Deployment Provider-Neutral Laravel Hosting

## 1. Ruang lingkup

Panduan ini menetapkan capability contract untuk hosting Laravel yang menyediakan PHP web/CLI yang kompatibel, SSH/SFTP atau upload artefak, Composer 2, MySQL 8.0.30-compatible, HTTPS, document root yang dapat diarahkan ke `public/`, writable runtime directories, dan cron/scheduler bila dibutuhkan. Tidak mengasumsikan root, Supervisor, Redis, Horizon, WebSocket, daemon, konfigurasi Nginx/Apache global, atau Node.js produksi.

Nilai path, domain, user, binary PHP, dan kemampuan scheduler harus dikonfirmasi pada provider yang dipilih. Secret tidak ditulis dalam dokumentasi atau command history. Jika provider tidak dapat menyajikan hanya direktori `public/` atau menjaga media privat di luar public exposure, hasilnya **HOST INCOMPATIBLE / NO-GO**.

## 2. Topologi aman

Document root domain harus menunjuk **hanya** ke direktori Laravel `public/`.

```text
/home/<account>/domains/<domain>/
├── app-current/                  # source aplikasi, bukan web root
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── resources/
│   ├── routes/
│   ├── storage/                  # privat
│   ├── vendor/
│   ├── artisan
│   ├── composer.json
│   └── .env                      # privat
└── public-root -> app-current/public/  # atau document root provider yang setara
```

Gunakan pengaturan document root provider atau symlink yang didukung paket. Bila symlink tidak didukung, pilih layout provider yang tetap menyajikan hanya isi `public/` dan menyesuaikan bootstrap path secara terkontrol. Jangan menaruh `.env`, `vendor`, `storage` privat, database dump, backup, `.git`, `app/`, atau source lain dalam public root.

### Fallback shared hosting dengan `public_html` yang terkunci

Jika provider mengunci document root domain utama ke `/home/<account>/public_html`, simpan **seluruh** source Laravel pada `/home/<account>/bank-sampah/` dan salin **hanya isi** folder `bank-sampah/public/` (termasuk `.htaccess`, `build/`, `index.php`, dan `deploy.php` bila console memang digunakan) ke `/home/<account>/public_html/`.

`public/index.php` dan `public/deploy.php` proyek ini mendukung layout tersebut: saat dijalankan dari `public_html`, keduanya lebih dulu mencari source privat pada sibling `../bank-sampah`, lalu tetap memakai layout Laravel standar saat berjalan dari `public/`. Jangan menyalin `.env`, `vendor`, `storage`, `app`, `bootstrap`, `config`, `database`, `resources`, atau `routes` ke `public_html`.

## 3. Capability prerequisites

1. Domain aktif dan sertifikat SSL valid; paksa HTTPS setelah pengujian.
2. PHP web dan CLI kompatibel dengan `composer.json` (`^8.3`) dan versinya selaras. Target local/CI saat ini adalah PHP 8.5, tetapi PHP 8.5 bukan minimum Composer. Verifikasi ekstensi yang diwajibkan dependency terkunci dan fitur aplikasi yang dipilih: OpenSSL, PDO MySQL, Mbstring, Tokenizer, XML, Ctype, JSON, Fileinfo, Intl, dan Zip; GD/Imagick serta BCMath bersifat kondisional bila fitur atau pemeriksaan platform dependency yang dipilih membutuhkannya.
3. Composer 2 tersedia dan memakai PHP CLI yang sama dengan runtime target; verifikasi `php -v` dan `composer --version`.
4. Database MySQL 8.0.30-compatible, user least-privilege, host, port, TLS policy bila diwajibkan, `utf8mb4`, dan InnoDB tersedia. SQLite evidence tidak membuktikan engine compatibility.
5. Document root dapat diarahkan tepat ke `public/` (atau layout front-controller setara yang membuktikan source tidak terekspos).
6. PHP process dapat menulis `storage/` dan `bootstrap/cache/`; `storage/app/media` tetap privat dan tidak menjadi target public storage link.
7. Cron/scheduler tersedia bila schedule dijalankan; timezone provider/cron diketahui. Aplikasi menggunakan `Asia/Jakarta`.
8. Kapasitas disk, inode, memory, execution time, upload size, dan backup plan diperiksa.
9. Email/SMTP dan object storage opsional diverifikasi bila digunakan.

Provider-neutral gate: capability yang gagal tidak boleh ditutup dengan perubahan aplikasi. Tandai **HOST INCOMPATIBLE / NO-GO** dan minta remediation atau provider lain.

## 4. Persiapan lokal atau CI

Sebelum upload:

1. Checkout commit/tag release yang disetujui dan pastikan worktree bersih.
2. Gunakan PHP 8.5 sebagai target local/CI yang saat ini diuji, bersama Composer 2. PHP 8.5 adalah target operasional saat ini, bukan batas minimum Composer; setiap provider tetap harus memenuhi `^8.3` dan menyamakan versi web/CLI.
3. Jalankan install/test dengan lockfile, static analysis/format yang dikonfigurasi, dan Pest 4.
4. Jalankan dependency audit Composer dan frontend.
5. Install dependency frontend dari lockfile dan jalankan build produksi Vite/Tailwind.
6. Pastikan `public/build/manifest.json` dan aset build lengkap.
7. Jangan memasukkan `.env`, test artifact, cache lokal, log, database lokal, `node_modules`, atau secret.
8. Buat artefak release dengan checksum dan catat commit, waktu, versi PHP/Composer/Node build.

Node.js tidak diperlukan di server produksi. Semua aset Vite/Tailwind dihasilkan di lokal/CI.

## 5. Konfigurasi environment produksi

Nilai minimum:

```dotenv
APP_ENV=production
APP_KEY=<stable-generated-key-stored-out-of-band>
APP_DEBUG=false
APP_URL=https://<application-domain>
APP_TIMEZONE=Asia/Jakarta
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=<managed-database-host>
DB_PORT=3306
DB_DATABASE=<application-database>
DB_USERNAME=<least-privilege-application-user>
DB_PASSWORD=<database-secret>
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_ENGINE=InnoDB
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
OPERATIONS_QUEUE_WORKER_MODE=none
SCHEDULER_TOPOLOGY=cron
```

`APP_KEY` dibuat satu kali secara aman dan dicadangkan; jangan menggantinya sembarang karena memengaruhi data terenkripsi. Isi `<...>` hanyalah placeholder dan harus diganti melalui secret manager, panel environment, atau file privat berpermission ketat. Jangan menaruh secret di repository, dokumentasi, command history, atau evidence. Gunakan database-backed session/cache yang kompatibel dengan database produksi. `QUEUE_CONNECTION=sync` adalah topology first release; `QUEUE_CONNECTION=database` hanya diaktifkan setelah migration, jobs/failed_jobs, cron worker terbatas, monitoring, retry, dan SOP gagal tersedia.

Verifikasi nama environment key terhadap config Laravel aktual sebelum deploy. Setelah environment privat dipasang, jalankan cache lifecycle pada root aplikasi: `php artisan optimize:clear`, `php artisan config:cache`, `php artisan route:cache`, dan `php artisan view:cache`. Cache yang dibangun harus berasal dari environment final dan tidak boleh menyimpan nilai secret dalam artefak evidence.

### 5.1 Console deployment web (opsional)

`public/deploy.php` adalah console terbatas untuk keadaan ketika SSH tidak praktis. Console ini **nonaktif secara default** dan tidak boleh diperlakukan sebagai terminal web umum.

1. Pastikan domain sudah HTTPS dan document root tetap mengarah hanya ke `public/`.
2. Buat token acak yang kuat lalu simpan secara privat pada environment production sebagai `DEPLOY_CONSOLE_TOKEN`; jangan masukkan token ke repository, dokumentasi, atau screenshot.
3. Bila IP operator stabil, isi `DEPLOY_CONSOLE_ALLOWED_IPS` dengan daftar IP publik yang dipisahkan koma untuk membatasi akses lebih lanjut.
4. Buka `/deploy.php`, masukkan token, lalu pilih salah satu aksi allowlist: pemeriksaan status migrasi, deployment (migrasi `--force` → clear cache → cache ulang), seed admin awal, seed data uji lengkap, cache ulang, atau baca 200 baris log Laravel terbaru. Urutan ini memungkinkan deployment pertama dengan cache driver database, saat tabel `cache` belum ada.
5. Untuk database yang memang masih baru dan belum berisi data, tersedia aksi **Fresh deployment + seed**. Aksi ini sengaja mewajibkan konfirmasi `RESET DATABASE`, menjalankan `migrate:fresh --seed --force`, lalu membangun cache. Jangan gunakan pada database yang sudah memiliki data operasional.
6. Data uji di production bersifat sementara dan harus dinyalakan secara eksplisit: isi `APP_DEMO_MODE=true` dan `APP_DEMO_PASSWORD` unik dengan minimal 16 karakter di `.env`, lalu bangun ulang cache dan pilih **Isi data uji lengkap** dengan konfirmasi `SEED DATA UJI`. Aksi ini membuat akun uji, wilayah, transaksi, saldo, pencairan, sembako, pengumuman, dan statistik. Setelah pengujian, set `APP_DEMO_MODE=false`, hapus password demo, ganti/nonaktifkan akun uji, lalu bangun ulang cache.
7. Console tidak menjalankan custom command, tidak dapat menghapus log, dan tidak membuat storage link. Tetap lakukan backup pra-deploy serta gunakan SSH untuk `composer install` dan operasi di luar allowlist.
8. Setelah kebutuhan sementara selesai, kosongkan `DEPLOY_CONSOLE_TOKEN` atau hapus file console dari release berikutnya untuk menonaktifkannya kembali.

Token diproses hanya melalui POST dan seluruh output memakai `no-store`; namun log tetap dapat memuat informasi operasional. Batasi token hanya kepada operator berwenang dan jangan membagikan hasil log ke kanal publik.

## 6. Deployment pertama

### 6.1 Upload source

- Upload melalui SFTP, Git deployment yang didukung, atau artefak release ke direktori nonpublik.
- Pastikan file tersembunyi yang diperlukan tersedia kecuali secret; `.git` tidak perlu di produksi.
- Arahkan document root ke `public/`; jika tidak mungkin, hentikan release dengan status **HOST INCOMPATIBLE / NO-GO**.
- Periksa bahwa URL ke `/.env`, `/vendor/`, `/storage/`, `/composer.json`, `/.git/`, `/app/`, `/bootstrap/`, dan `/config/` menghasilkan 404/403 tanpa isi. HTTP probe ini dilakukan pada host non-produksi/produksi sesuai otorisasi, bukan digantikan oleh in-process check.

### 6.2 Composer melalui SSH

Dari root aplikasi dengan PHP CLI yang memenuhi `composer.json`:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

Jangan menjalankan `composer update` di produksi. Jika provider memerlukan path PHP eksplisit, jalankan Composer melalui binary PHP yang memenuhi kontrak runtime provider.

### 6.3 Permission

- Source dan `.env` tidak writable oleh publik.
- Hanya `storage/` dan `bootstrap/cache/` writable oleh proses PHP sesuai mekanisme provider.
- Jangan memakai permission `777`.
- Storage link hanya untuk file yang memang publik. Bukti dan file privat tidak dimasukkan ke `storage:link` publik.

### 6.4 Aplikasi dan database

1. Aktifkan maintenance mode bila sistem sudah menerima trafik.
2. Buat backup database/media sebelum migration.
3. Jalankan pemeriksaan migration: `php artisan migrate:status`.
4. Jalankan `php artisan migrate --force` hanya setelah backup dan rollback dinilai.
5. Jalankan seeder produksi hanya jika bersifat idempotent dan disebut pada runbook release; jangan menjalankan seed data demo. Satu-satunya seeder yang diizinkan adalah bootstrap admin awal melalui `Database\Seeders\InitialAdminSeeder` yang idempotent (`updateOrCreate` pada email) dan ter-gate env: set `APP_INITIAL_ADMIN_EMAIL` dan `APP_INITIAL_ADMIN_PASSWORD` **sementara** bersama `php artisan db:seed --class=DatabaseSeeder --force`, lalu setelah sukses kosongkan nilai pada environment privat dan rotasi kata sandi menjadi kredensial pilihan operator. Idempotensi `updateOrCreate` memastikan menjalankan ulang seeder tidak membuat admin ganda.
6. Trigger `audit_logs_prevent_update`/`audit_logs_prevent_delete` dibuat non-fatal (try/catch + `LOG` warning) pada migration `create_audit_logs_table`; buktikan pada MySQL 8.0.30 bahwa kedua trigger aktif melalui `SHOW TRIGGERS`, dan pastikan database user memiliki hak yang diperlukan (mis. `SUPER`/`SET_USER_ID` atau `log_bin_trust_function_creators=1` bila binary logging aktif) agar tercipta sungguhan. Jika trigger tidak aktif, audit immutability IMP-107 tidak terjaga dan harus diselesaikan sebelum go-live.
7. Bersihkan cache lama, lalu jalankan cache produksi yang kompatibel:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jika route cache gagal karena route tidak kompatibel, perbaiki release; jangan mengabaikan tanpa keputusan. Jalankan `event:cache` hanya bila aplikasi mendukung dan diuji.

8. Verifikasi storage, DB, mail, dan health.
9. Nonaktifkan maintenance mode setelah smoke test internal siap.

## 7. Deployment release berikutnya

Urutan aman:

1. Catat release ID/commit dan change set migration.
2. Verifikasi backup terbaru; buat backup pra-deploy.
3. Upload release baru ke direktori terpisah bila kapasitas memungkinkan.
4. Install Composer production dan pastikan aset build tersedia.
5. Jalankan test/smoke command yang tidak mengubah data.
6. Aktifkan maintenance mode untuk migration yang tidak backward-compatible.
7. Jalankan migration `--force`.
8. Bangun cache.
9. Alihkan `current`/document root secara atomik bila topology memungkinkan, atau sinkronkan artefak dengan downtime terkontrol.
10. Jalankan verifikasi pascadeploy.
11. Buka maintenance mode.
12. Pantau error, cron, transaksi, ledger, dan storage.
13. Pertahankan release lama sampai masa observasi selesai, lalu hapus sesuai retensi.

Shared hosting mungkin membatasi symlink/atomic release. Bila tidak tersedia, jadwalkan downtime dan jangan mencampur source lama/baru saat traffic aktif.

## 8. Cron dan scheduler

Gunakan cron/scheduler capability provider untuk memanggil Laravel Scheduler dengan binary PHP CLI yang memenuhi kontrak dan path absolut, misalnya secara konseptual:

```cron
* * * * * /path/to/php /path/to/app-current/artisan schedule:run >> /path/to/scheduler.log 2>&1
```

Interval minimum dan kemampuan log mengikuti provider. Jika satu menit tidak tersedia, catat **HOST INCOMPATIBLE / NO-GO** untuk schedule yang mensyaratkan interval tersebut atau minta owner menyetujui perubahan operasi yang teruji; jangan mengasumsikan interval provider.

### Timezone

1. Catat hasil `date`, `php -r 'echo date_default_timezone_get();'`, dan waktu scheduler.
2. Aplikasi tetap `Asia/Jakarta`.
3. Periksa apakah cron memakai UTC, timezone server, atau timezone akun.
4. Jika cron bukan `Asia/Jakarta`, gunakan jadwal Laravel yang menyatakan timezone secara eksplisit dan uji pada pergantian hari.
5. Simpan heartbeat scheduler dan verifikasi task kedaluwarsa/pengingat tidak maju atau terlambat satu hari.

### Database queue terbatas

Jika owner menyetujui topology ini, scheduler menjalankan worker one-shot berbatas waktu, misalnya pola `queue:work --stop-when-empty --max-time=<batas> --tries=<batas>`. Nilai disesuaikan limit provider. Jangan membuat proses permanen. Pantau `failed_jobs` dan backlog; kegagalan berulang tidak boleh memblokir cron lain. Default first release tetap `QUEUE_CONNECTION=sync` dan `OPERATIONS_QUEUE_WORKER_MODE=none`.

## 9. Migration aman

Sebelum UAT/production, jalankan dan catat satu rehearsal MySQL 8.0.30 disposable terjadwal yang terisolasi dari database primer: migration fresh dan upgrade bila relevan, rollback/remigrate/no-op/cleanup, serta keputusan backup/restore. Ini adalah release-validation yang berdiri sendiri, bukan blocker quality gate harian per-IMP; hasil SQLite tidak membuktikan kompatibilitas MySQL. Bila IMP-107 berada pada baseline rilis, rehearsal mencakup proof trigger append-only/immutability MySQL terisolasi.

- Migration forward-only diutamakan; `down()` tidak dianggap satu-satunya rollback data.
- Hindari operasi tabel besar yang mengunci lama. Gunakan pola expand/migrate/contract lintas release bila diperlukan.
- Penambahan kolom: nullable/default aman → deploy kode kompatibel → backfill batch terbatas → constraint.
- Penghapusan/rename kolom hanya setelah kode lama tidak memakai dan backup teruji.
- Migration ledger/status/permission ditinjau khusus dan diuji pada salinan data.
- Catat durasi, row count, dan hasil migration.

## 10. Backup

### Jadwal minimum

- Database: harian, serta sebelum migration/release berisiko.
- Media privat: berkala sesuai perubahan dan setidaknya harian untuk bukti kritis bila kemampuan memungkinkan.
- `.env`/konfigurasi: salinan terenkripsi terbatas setiap perubahan.
- Release metadata: commit, checksum, migration list.

Backup disimpan terpisah dari akun hosting utama, dienkripsi/dilindungi, diverifikasi checksum, dan memiliki retensi. Backup provider dapat menjadi lapisan tambahan, bukan satu-satunya salinan. Uji restore berkala pada lingkungan terisolasi. Sasaran awal RPO ≤24 jam dan RTO ≤8 jam.

## 11. Rollback

### Aplikasi tanpa migration destruktif

1. Aktifkan maintenance mode bila diperlukan.
2. Alihkan kembali ke release sebelumnya atau upload artefak lama terverifikasi.
3. Jalankan `optimize:clear` dan bangun cache ulang.
4. Verifikasi health, login, permission, aset, dan transaksi baca.
5. Buka maintenance lalu pantau.

### Dengan migration

1. Hentikan traffic dan task cron yang dapat menulis.
2. Nilai apakah kode lama kompatibel dengan schema baru.
3. Jika kompatibel, rollback kode tanpa rollback data.
4. Jika tidak kompatibel, gunakan prosedur migration balik yang telah diuji atau restore database+media konsisten dari backup.
5. Jangan menjalankan `migrate:rollback` otomatis pada produksi tanpa meninjau dampak data.
6. Setelah restore, verifikasi ledger, hold, transaksi, file, audit, dan laporan; catat data setelah RPO yang harus dipulihkan/koreksi resmi.

## 12. Verifikasi pascadeploy

### Infrastruktur

- Domain HTTPS, certificate, redirect, security header; `APP_URL` valid dan memakai `https://`.
- `SESSION_SECURE_COOKIE=true` dan HTTPS secure-cookie behavior terverifikasi pada target.
- Document root hanya `public/`; file sensitif tidak dapat diakses.
- PHP web dan CLI memenuhi `^8.3` dan selaras; PHP 8.5 adalah target local/CI saat ini; Composer 2.
- `APP_ENV=production`, debug mati, config cache memakai nilai benar.
- Database, storage privat, write permission, mail, dan object storage bila ada.
- Scheduler heartbeat dan timezone `Asia/Jakarta` benar.

### Fungsi kritis

- Login/logout, perubahan kata sandi mandiri dari profil, perubahan berbantuan langsung oleh admin, dan akses role.
- Warga hanya melihat data sendiri.
- Harga aktif dan estimasi.
- Draf setoran pada akun uji; finalisasi hanya jika prosedur data produksi mengizinkan, lalu verifikasi satu ledger entry.
- Hold pencairan/sembako pada akun uji dan pelepasan aman.
- Upload/download privat terotorisasi.
- QR bukti hanya data terbatas.
- Statistik publik agregat.
- Ekspor kecil; job queue terbatas bila aktif.
- Tampilan aset Vite/Livewire/Filament tanpa error; Alpine tidak dimuat ganda.

### Observability

- Tidak ada exception baru pada log.
- Audit terbentuk untuk smoke action.
- Cron/failed job normal.
- Disk/inode, memory, waktu respons, dan DB connection wajar.

## 13. Maintenance rutin

- Patch framework/dependency diuji di lokal/CI sebelum deploy.
- Tinjau disk, inode, log, failed job, scheduler heartbeat, backup recency, SSL, dan quota database.
- Bersihkan export/upload sementara melalui scheduler dan retensi.
- Uji restore dan prosedur insiden berkala.
- Tinjau permission saat pergantian petugas/admin.
- Laporan harian tetap proses bisnis, bukan digantikan backup.

## 14. Checklist go-live

- [ ] Domain/document root aman.
- [ ] File sensitif tidak terekspos.
- [ ] PHP web dan CLI memenuhi `^8.3` dan selaras; PHP 8.5 target local/CI; Composer 2.
- [ ] Build Vite/Tailwind dari lokal/CI tersedia.
- [ ] Rehearsal MySQL 8.0.30 disposable dan migration terverifikasi sebagai release-validation terpisah; jika berlaku, proof trigger IMP-107 tercatat. Bukti SQLite tidak diklaim sebagai bukti MySQL.
- [ ] Bootstrap admin awal via `InitialAdminSeeder` berhasil secara idempotent; nilai `APP_INITIAL_ADMIN_*` sudah dikosongkan dan kata sandi dirotasi operator.
- [ ] Kedua trigger `audit_logs_prevent_update`/`audit_logs_prevent_delete` aktif pada MySQL 8.0.30 (`SHOW TRIGGERS`); IMMUTABLE audit IMP-107 terjaga.
- [ ] Cache produksi dibangun; debug mati.
- [ ] Storage privat dan route file diuji.
- [ ] Permission, idempotensi, ledger, hold, QR, publik lulus.
- [ ] Cron provider, interval, dan timezone diuji.
- [ ] Queue sync/database terbatas sesuai konfigurasi; tidak ada worker permanen.
- [ ] Backup pra-go-live dan latihan rollback/restore tersedia.
- [ ] Monitoring dan penanggung jawab insiden ditetapkan.

## 15. Referensi

- [ARCHITECTURE.md](ARCHITECTURE.md)
- [SECURITY.md](SECURITY.md)
- [OPERATIONS.md](OPERATIONS.md)
- [TEST_PLAN.md](TEST_PLAN.md)
- [DECISIONS.md](DECISIONS.md)
