# Changelog

Semua perubahan penting terhadap baseline disimpan pada dokumen ini.

## Unreleased

### Added
- Paket dokumentasi development lengkap: requirement, aturan bisnis, permission, validasi, 36 user flow, arsitektur, model data, keamanan, deployment, desain, test plan, operasi, dan ADR.
- Traceability seluruh baseline fitur, 36 flowchart, aturan bisnis, dan test case.
- Design system mobile-first tanpa emoji.
- WhatsApp manual melalui tautan `wa.me`.
- Pelayanan warga tanpa smartphone.
- Riwayat koreksi bagi warga.
- Tugas petugas hari ini.
- Target, layanan keliling, estimasi, QR verifikasi, statistik wilayah, statistik publik, dan kapasitas penjemputan sebagai bagian baseline fitur yang disetujui.
- Dokumen publik kanonis [Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0](TERMS_AND_PRIVACY.md), termasuk penerimaan afirmatif saat pendaftaran, batas status `menunggu_verifikasi`, dan pemisahan persetujuan layanan berbantuan.
- Change request kontrak fondasi yang disetujui: Filament 5 hanya untuk back-office (ADR-002); schema sampah dengan kondisi banyak-ke-banyak, harga jenis+kondisi, dan `kg` kanonik; serta gate penundaan media, editor permission, dan CMS landing (ADR-014; WST-001, PRC-001).
- Perubahan kontrak produk yang disetujui untuk AUTH-003: alur kata sandi disahkan menjadi dua jalur tertutup, yaitu perubahan mandiri dari profil oleh pengguna terautentikasi dengan kata sandi saat ini, kata sandi baru, dan konfirmasi, serta perubahan berbantuan langsung oleh admin berizin hanya bagi pengguna yang benar-benar lupa kata sandi dan tidak dapat login. Jalur admin wajib verifikasi `tatap_muka`/`callback_nomor_terdaftar`, alasan 10–1000 karakter, non-self-target, dan pencabutan seluruh sesi target. Jalur mandiri mencabut sesi lain sambil mempertahankan sesi saat ini bila mungkin, atau meminta autentikasi ulang. Keduanya mengaudit aktor/metode/alasan/hasil tanpa secret. Reset berbasis token, permintaan publik, email, SMS, WhatsApp, dan delivery token tidak ada.
- W10 menambahkan ikon PWA, guard logout, guard offline tanpa antrean bisnis, lifecycle backup database+media atomik dengan row lock dan kontrak audit UUID, command aman untuk metadata backup-pair serta restore verification, private operational health, in-process smoke `/health`, dan scheduler heartbeat berbasis cache.
- Bootstrap admin keseluruhan env-gated idempotent `Database\Seeders\InitialAdminSeeder` (`updateOrCreate` pada email, role `admin`), dipanggil dari `DatabaseSeeder`; menjalankan dengan kunci config baru `app.initial_admin_email`/`app.initial_admin_password` (env `APP_INITIAL_ADMIN_EMAIL`/`APP_INITIAL_ADMIN_PASSWORD`), terkait AUTH-002/IMP-019 admission back-office.
- Seeder kredensial development non-produksi `Database\Seeders\DeveloperUsersSeeder`: membuat satu akun per role baseline (`warga`, `petugas`, `bendahara`, `admin`, `superadmin`) dengan telepon/email unik, profil role-appropriate (warga=customer, petugas/bendahara=staff), role assignment, dan kata sandi bersama `Dev#Sindangheula2026`; hanya dijalankan saat non-`production` (guard ganda di seeder dan `DatabaseSeeder`). Yang memegang `backoffice.access` adalah `admin` dan `superadmin` (ADR-002); keduanya dapat membuka panel teknis, dengan superadmin mewarisi seluruh hak admin plus permission teknis.
- Test form login back-office nyata(`BackofficeLoginFormTest`): Login email+password+`backoffice.access` sukses; password salah, akun phone-only tanpa email, dan akun tanpa `backoffice.access` ditolak lewat Livewire `Login` Filament tanpa mengautentikasi.
- Custom back-office login branded `App\Filament\Auth\BackofficeLogin` (+ view `filament/backoffice/auth/login`), terdaftar via `->login(BackofficeLogin::class)` di `BackofficePanelProvider`, tanpa mengedit vendor. Memuat form email+password Filament asli, maskot/token tema, dan tombol demo fill (admin/superadmin) yang hanya dirender/mengisi di non-produksi. `superadmin` kini dapat membuka panel teknis (dibedakan scope teknis vs `admin`).
- Resource back-office `RoleResource` + `PermissionResource` (Data Master): `role.view` untuk melihat, `role.manage` untuk mutasi via `\App\Domain\Identity\Actions\ManageRoles` yang mengisi pivot `granted_by`/`reason` dan melindungi role sistem (`warga`, `petugas`, `bendahara`, `admin`, `superadmin`) dari penghapusan; `PermissionResource` read-only.

### Changed

- Database produksi menggunakan MariaDB terkelola yang kompatibel dengan MySQL.
- Deployment sistem menggunakan Hostinger Web Hosting Premium/Business.
- Frontend menggunakan Blade dan Livewire 4 tanpa React.
- Alpine.js menggunakan instance bawaan Livewire.
- Queue sistem menggunakan `sync` atau database queue berbatas waktu melalui cron.
- W10 menyelaraskan smoke check ke `/health`, bukan `/up`; `/health` publik tetap generik, sedangkan `/operations/health` privat. Rollback sekarang fail-closed bila eligibility retensi, status selesai, metadata artifact, atau restore verification terisolasi tidak terpenuhi.

### Security

- W10 mempertahankan cache allowlist dan network-only untuk route privat, autentikasi, Livewire, QR, media, ekspor, finansial, serta seluruh mutasi. Health operasional privat dan audit backup memakai kontrak UUID tersanitasi.

### Fixed

- Temuan review code-level W10 pada duplicate execution lifecycle backup dan smoke route sudah diperbaiki. Verifikasi terbaru: `php artisan test tests/Feature/Wave10 --no-ansi` PASS, 75 tests dan 499 assertions; targeted production PHPStan pada file operations/health/smoke yang berubah 0 errors; Pint, PHP syntax checks, dan `npm run build` PASS. Temuan pre-existing pada `ScheduledOperationsService` dan test PHPStan tetap terpisah.
- Rehearsal MySQL/MariaDB disposable terverifikasi sebagai release-validation: `migrate:fresh` sukses 33 migrasi, 60 tabel, kedua trigger `audit_logs_prevent_update`/`audit_logs_prevent_delete` aktif (`SHOW TRIGGERS`), dan bootstrap admin awal `InitialAdminSeeder` membuat pengguna `admin` aktif/verified dengan role `admin`. Ini adalah bukti engine MySQL, bukan klaim dari SQLite.
- Gate browser/a11y/responsive, MariaDB, deployment-host, backup/restore nyata, RPO/RTO, UAT, monitoring, dan approval masih terbuka. W10 tetap `in_progress` dan belum merupakan klaim release.

### Removed

- Asumsi VPS Ubuntu/Nginx yang dikelola sendiri.
- Queue worker permanen pada shared hosting.
- WhatsApp otomatis dari baseline fitur yang disetujui.

## Aturan Entri

Gunakan kategori `Added`, `Changed`, `Fixed`, `Security`, `Deprecated`, dan `Removed`. Setiap perubahan fitur harus merujuk requirement, keputusan, dan change request terkait.
