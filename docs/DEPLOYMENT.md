# Deployment provider-neutral Laravel hosting

Panduan ini menetapkan kontrak deployment Laravel pada hosting terkelola. Provider harus menyediakan PHP dan Composer yang kompatibel, MySQL 8.0.30-compatible, HTTPS, document root aman, storage privat, dan cron bila pekerjaan terjadwal digunakan.

## 1. Batas hosting

Deployment tidak mengasumsikan akses root, Supervisor, Redis, Horizon, WebSocket, daemon, konfigurasi web server global, atau Node.js pada production. Jika provider tidak dapat menyajikan hanya direktori `public/` atau menjaga media privat di luar akses publik, hasil pemeriksaan adalah **HOST INCOMPATIBLE / NO-GO**.

## 2. Topologi aman

Document root domain hanya boleh menyajikan direktori Laravel `public/`:

```text
/home/account/domains/application/
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
└── public-root -> app-current/public/
```

Gunakan pengaturan document root atau symlink yang didukung provider. Jangan menaruh `.env`, `vendor`, storage privat, database dump, `.git`, `app/`, atau source lain dalam public root.

Jika provider mengunci document root ke `public_html`, simpan source Laravel di luar direktori tersebut. Salin hanya isi `public/` dan sesuaikan bootstrap path secara terkontrol.

## 3. Prasyarat

1. Domain aktif dan sertifikat Transport Layer Security (TLS) valid
2. PHP web dan command-line interface (CLI) memenuhi `composer.json` (`^8.3`) dan memakai versi yang selaras
3. Composer 2 memakai binary PHP CLI yang memenuhi kontrak
4. MySQL 8.0.30-compatible menyediakan InnoDB dan `utf8mb4`
5. Document root hanya menyajikan `public/`
6. Proses PHP dapat menulis `storage/` dan `bootstrap/cache/`
7. Cron tersedia bila scheduler digunakan dan timezone diketahui
8. Kapasitas disk, inode, memory, execution time, serta upload size memenuhi kebutuhan

Verifikasi extension sesuai dependency terkunci. Extension yang umum diperlukan meliputi OpenSSL, PDO MySQL, Mbstring, Tokenizer, XML, Ctype, JSON, Fileinfo, Intl, dan Zip.

## 4. Persiapan artefak

Sebelum mengunggah release:

1. Pilih commit atau tag yang disetujui
2. Gunakan PHP 8.3 atau lebih baru dan Composer 2
3. Jalankan test, static analysis, formatter, dan audit dependency yang dikonfigurasi
4. Bangun aset Vite atau Tailwind dari lockfile
5. Pastikan `public/build/manifest.json` tersedia
6. Keluarkan `.env`, cache lokal, log, database lokal, `node_modules`, test artifact, dan secret dari artefak
7. Catat commit, waktu build, checksum artefak, serta versi toolchain

Node.js hanya diperlukan untuk build lokal atau continuous integration (CI), bukan pada server production.

## 5. Environment production

Gunakan nilai environment privat yang sesuai deployment:

```dotenv
APP_ENV=production
APP_KEY=your_stable_application_key_here
APP_DEBUG=false
APP_URL=https://your_application_domain_here
APP_TIMEZONE=Asia/Jakarta
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=your_managed_database_host_here
DB_PORT=3306
DB_DATABASE=your_application_database_here
DB_USERNAME=your_least_privilege_user_here
DB_PASSWORD=your_database_secret_here
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SCHEDULER_TOPOLOGY=cron
```

Simpan secret melalui panel environment atau file privat berpermission ketat. Setelah environment final tersedia, jalankan lifecycle cache dari root aplikasi:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Queue aktif menggunakan `sync`. Deployment tidak mengaktifkan worker permanen atau queue database.

## 6. Prosedur deployment

### Upload source

1. Unggah artefak ke direktori nonpublik melalui mekanisme provider
2. Arahkan document root ke `public/`
3. Pastikan URL `/.env`, `/vendor/`, `/storage/`, `/composer.json`, `/.git/`, `/app/`, `/bootstrap/`, dan `/config/` menghasilkan 404 atau 403 tanpa isi

### Install dependency

Jalankan Composer dari root aplikasi:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

Jangan menjalankan `composer update` pada production.

### Permission filesystem

- Source dan `.env` tidak boleh writable oleh publik
- Hanya `storage/` dan `bootstrap/cache/` yang writable oleh proses PHP
- Jangan memakai permission `777`
- Jangan memasukkan bukti atau media privat ke public storage link

### Database dan cache

1. Periksa status migration dengan `php artisan migrate:status`
2. Nilai kompatibilitas schema dan rencana rollback kode
3. Jalankan `php artisan migrate --force`
4. Bangun ulang cache konfigurasi, route, dan view
5. Verifikasi database, storage, Health, serta aset
6. Jalankan smoke test internal sebelum membuka trafik release

Seeder production hanya boleh dijalankan jika idempotent dan tercantum pada runbook release. Data demo tidak boleh masuk production.

## 7. Cron dan scheduler

Gunakan cron provider untuk memanggil Laravel Scheduler dengan path absolut:

```cron
* * * * * /path/to/php /path/to/app-current/artisan schedule:run >> /path/to/scheduler.log 2>&1
```

Catat timezone cron. Aplikasi menggunakan `Asia/Jakarta`; jadwal Laravel harus menyatakan timezone secara eksplisit bila server memakai timezone lain. Verifikasi heartbeat dan pekerjaan kedaluwarsa pada batas pergantian hari.

## 8. Validasi migration

Sebelum User Acceptance Testing (UAT) atau production, jalankan rehearsal MySQL 8.0.30 pada database disposable. Cakupan mencakup migration fresh, upgrade yang relevan, no-op, rollback schema yang aman, remigrate, constraint, lock, transaction, serta trigger audit bila digunakan.

SQLite tidak membuktikan kompatibilitas engine MySQL. Catat versi engine, migration, durasi, dan hasil rehearsal.

Gunakan pola perubahan schema yang aman:

- Tambahkan kolom nullable atau default aman sebelum mewajibkan nilainya
- Lakukan backfill dalam batch terbatas
- Hapus atau rename kolom hanya setelah kode lama tidak lagi menggunakannya
- Tinjau migration ledger, status, dan permission secara khusus

## 9. Rollback release

Rollback hanya mengembalikan kode atau schema melalui prosedur yang telah diuji. Jangan mengubah saldo atau record final secara langsung.

1. Hentikan trafik penulis melalui mekanisme provider yang disetujui
2. Nilai kompatibilitas kode lama terhadap schema saat ini
3. Alihkan ke artefak release sebelumnya jika schema tetap kompatibel
4. Jika schema perlu dikembalikan, jalankan migration balik yang telah diuji
5. Bangun ulang cache
6. Verifikasi Health, login, permission, ledger, hold, file privat, dan laporan
7. Buka trafik dan pantau error

Jangan menjalankan `migrate:rollback` secara otomatis tanpa meninjau dampak data.

## 10. Verifikasi pascadeploy

### Infrastruktur

- HTTPS, certificate, redirect, dan security header aktif
- Document root hanya menyajikan `public/`
- PHP web dan CLI memenuhi `^8.3` serta selaras
- `APP_ENV=production` dan debug mati
- Database, storage privat, write permission, dan aset tersedia
- Scheduler heartbeat dan timezone benar

### Fungsi kritis

- Login, logout, perubahan kata sandi mandiri, dan bantuan admin
- Record scope membatasi data setiap pengguna
- Harga aktif, estimasi, dan setoran uji
- Ledger, hold, pencairan, dan penukaran berbasis saldo
- Upload dan download privat
- QR bukti menampilkan data terbatas
- Statistik publik tetap agregat
- Ekspor kecil yang tersedia
- Blade, Livewire, Filament, dan aset Vite tampil tanpa error

### Observability

- Tidak ada exception baru pada log
- Audit terbentuk untuk tindakan smoke
- Scheduler berjalan tanpa error
- Disk, inode, memory, waktu respons, dan koneksi database berada dalam batas provider

## 11. Operasi release

- Uji pembaruan dependency di lokal atau CI sebelum deployment
- Tinjau disk, inode, log, scheduler heartbeat, TLS, dan kuota database
- Bersihkan file ekspor atau upload sementara melalui lifecycle inti yang tersedia
- Tinjau permission saat personel berubah
- Pertahankan laporan harian sebagai proses bisnis

## 12. Checklist go-live

- [ ] Domain dan document root aman
- [ ] File sensitif tidak terekspos
- [ ] PHP web dan CLI memenuhi `^8.3` serta selaras
- [ ] Composer 2 dan aset hasil build tersedia
- [ ] Rehearsal MySQL 8.0.30 lulus
- [ ] Cache production dibangun dan debug mati
- [ ] Storage privat serta route file diuji
- [ ] Permission, idempotensi, ledger, hold, QR, dan endpoint publik lulus
- [ ] Cron dan timezone diuji
- [ ] Queue menggunakan `sync`
- [ ] Health dan rollback release diuji
- [ ] Monitoring dan penanggung jawab insiden ditetapkan

## 13. Referensi

- [Arsitektur](ARCHITECTURE.md)
- [Keamanan](SECURITY.md)
- [Operasi](OPERATIONS.md)
- [Rencana pengujian](TEST_PLAN.md)
- [Keputusan arsitektur](DECISIONS.md)
