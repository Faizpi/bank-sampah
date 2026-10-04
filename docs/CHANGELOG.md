# Changelog

Semua perubahan penting terhadap baseline disimpan pada dokumen ini.

## Unreleased

### Added
- Halaman Filament back-office **Laporan** pada grup **Pengawasan**, aman berdasarkan `report.view` dan memakai ekspor XLSX privat yang sama dengan alur operasional.
- Klarifikasi audit-gap implementation: pengaturan teknis non-secret melalui UI tetap termasuk scope; eksekusi artefak backup/restore tetap deployment/SOP-only. Password mandiri setelah login tetap tersedia, dan reset berbantuan admin atau superadmin hanya melalui alur PasswordAssistance tervalidasi untuk pengguna yang terkunci karena lupa kata sandi.
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
- Perubahan kontrak produk yang disetujui untuk AUTH-003: alur kata sandi disahkan menjadi dua jalur tertutup, yaitu perubahan mandiri dari profil oleh pengguna terautentikasi dengan kata sandi saat ini, kata sandi baru, dan konfirmasi, serta perubahan berbantuan langsung melalui alur PasswordAssistance oleh admin atau superadmin berizin hanya bagi pengguna yang benar-benar lupa kata sandi dan tidak dapat login. Jalur berbantuan wajib verifikasi `tatap_muka`/`callback_nomor_terdaftar`, alasan 10–1000 karakter, non-self-target, dan pencabutan seluruh sesi target. Jalur mandiri mencabut sesi lain sambil mempertahankan sesi saat ini bila mungkin, atau meminta autentikasi ulang. Keduanya mengaudit aktor/metode/alasan/hasil tanpa secret. Reset berbasis token, permintaan publik, email, SMS, WhatsApp, dan delivery token tidak ada.
- W10 menambahkan ikon PWA, guard logout, guard offline tanpa antrean bisnis, lifecycle backup database+media atomik dengan row lock dan kontrak audit UUID, command aman untuk metadata backup-pair serta restore verification, private operational health, in-process smoke `/health`, dan scheduler heartbeat berbasis cache.
- Bootstrap admin keseluruhan env-gated idempotent `Database\Seeders\InitialAdminSeeder` (`updateOrCreate` pada email, role `admin`), dipanggil dari `DatabaseSeeder`; menjalankan dengan kunci config baru `app.initial_admin_email`/`app.initial_admin_password` (env `APP_INITIAL_ADMIN_EMAIL`/`APP_INITIAL_ADMIN_PASSWORD`), terkait AUTH-002/IMP-019 admission back-office.
- Seeder kredensial development non-produksi `Database\Seeders\DeveloperUsersSeeder`: membuat satu akun per role baseline (`warga`, `petugas`, `bendahara`, `admin`, `superadmin`) dengan telepon/email unik, profil sesuai role, role assignment, dan kata sandi development yang dikonfigurasi untuk environment non-production. Kredensial tidak dicatat dalam dokumentasi atau commit. Seeder hanya berjalan saat non-`production` (guard ganda di seeder dan `DatabaseSeeder`). Yang memegang `backoffice.access` adalah `admin` dan `superadmin` (ADR-002); keduanya dapat membuka panel teknis, dengan superadmin mewarisi seluruh hak admin plus permission teknis.
- Test form login back-office nyata(`BackofficeLoginFormTest`): Login email+password+`backoffice.access` sukses; password salah, akun phone-only tanpa email, dan akun tanpa `backoffice.access` ditolak lewat Livewire `Login` Filament tanpa mengautentikasi.
- Custom back-office login branded `App\Filament\Auth\BackofficeLogin` (+ view `filament/backoffice/auth/login`), terdaftar via `->login(BackofficeLogin::class)` di `BackofficePanelProvider`, tanpa mengedit vendor. Memuat form email+password Filament asli, maskot/token tema, dan tombol demo fill (admin/superadmin) yang hanya dirender/mengisi di non-produksi. `superadmin` kini dapat membuka panel teknis (dibedakan scope teknis vs `admin`).
- Resource back-office `RoleResource` + `PermissionResource` (Data Master): `role.view` untuk melihat, `role.manage` untuk mutasi via `\App\Domain\Identity\Actions\ManageRoles` yang mengisi pivot `granted_by`/`reason` dan melindungi role sistem (`warga`, `petugas`, `bendahara`, `admin`, `superadmin`) dari penghapusan; `PermissionResource` read-only.

### Changed

- Superadmin kini ditegaskan sebagai superset role admin: seluruh permission baseline admin diwariskan, lalu ditambah permission teknis. Permission khusus ledger/koreksi tetap eksplisit.
- Database memakai MySQL 8.0.30; development berjalan melalui Laragon.
- Deployment sistem menggunakan Hostinger Web Hosting Premium/Business.
- Frontend menggunakan Blade dan Livewire 4 tanpa React.
- Alpine.js menggunakan instance bawaan Livewire.
- Queue sistem menggunakan `sync` atau database queue berbatas waktu melalui cron.
- W10 menyelaraskan smoke check ke `/health`, bukan `/up`; `/health` publik tetap generik, sedangkan `/operations/health` privat. Rollback sekarang fail-closed bila eligibility retensi, status selesai, metadata artifact, atau restore verification terisolasi tidak terpenuhi.
- Dokumentasi navigasi back-office kini mengikuti enam grup yang diterapkan: Operasional, Data Master, Program, Pengawasan, Keamanan & Akses, dan Administrasi sistem. Kontrak form serta disclosure juga mencatat ukuran kontrol, jarak label, dan indikator state yang dipakai antarmuka saat ini.
- Snapshot verifikasi lokal 12 Agustus 2026: `composer check` PASS (Pint, PHPStan 0 error, Pest 1.131 test: 1.130 lulus, 1 dilewati, 4.874 asersi) dan `npm run build` PASS. Snapshot ini tidak menggantikan browser/E2E, UAT, deployment, monitoring, atau approval rilis.

### Security

- W10 mempertahankan cache allowlist dan network-only untuk route privat, autentikasi, Livewire, QR, media, ekspor, finansial, serta seluruh mutasi. Health operasional privat dan audit backup memakai kontrak UUID tersanitasi.

### Fixed

- Temuan review code-level W10 pada duplicate execution lifecycle backup dan smoke route sudah diperbaiki. Verifikasi pada snapshot W10: `php artisan test tests/Feature/Wave10 --no-ansi` PASS (120 tests, 119 passed, 1 skipped, 727 assertions); full suite SQLite PASS (1124 tests, 1123 passed, 1 skipped, 4714 assertions); PHPStan, Pint, PHP syntax, `npm run build`, dan `npm audit` (0 vulnerabilities) PASS.
- Referensi arsip yang sudah tidak tersedia dihapus dari tracker implementasi; tautan relatif dokumentasi diperiksa kembali.
- Rehearsal MySQL 8.0.30 disposable terverifikasi sebagai release-validation: `migrate:fresh` sukses 36 migrasi, 61 tabel, 10 trigger append-only aktif, seed admin/role, no-op migration, rollback satu migration, dan remigrate. Backup/restore nyata disposable juga PASS: dump SQL 132.847 bytes, restore sekitar 4,4 detik lokal, source/restore identik, dan media archive-restore PASS. Database/grant/artefak rehearsal dibersihkan dan setting `log_bin_trust_function_creators` dikembalikan setelah uji. Ini adalah bukti engine/backup lokal terisolasi, bukan klaim deployment production atau RPO/RTO production.
- Browser smoke + axe 12 route/viewport lulus HTTP/HTTPS, console/request error, overflow, keyboard skip-link/focus, dan accessibility (0 violation) setelah token `text-sky-blue` diperbaiki. Service Worker `activated` pada Chromium dan Firefox HTTPS dengan certificate bypass test; auth role smoke enam flow Chromium/Firefox juga PASS. WebKit HTTPS masih terhalang certificate lokal Laragon. Gate browser critical transaction/cross-browser production, HTTPS certificate valid, deployment-host, RPO/RTO production, UAT, monitoring, dan approval masih terbuka. W10 tetap `in_progress` dan belum merupakan klaim release.
- Technical browser transaction UAT pada database disposable Laragon MySQL 8.0.30 lulus 10/10 flow Chromium tanpa browser error: pickup sampai setoran aktual, setoran sampai receipt/QR publik, pencairan sampai approval/payer/payment, sembako sampai handover, dan koreksi setoran melalui back-office. Bukti langkah dan lembar sign-off stakeholder dicatat di [UAT_EVIDENCE.md](UAT_EVIDENCE.md); hasil ini belum merupakan approval manusia atau klaim release.
- Formatter status pickup back-office diselaraskan untuk menerima `PickupStatus` enum dari Filament, dan komponen QR kini mengizinkan hanya strict base64 image data URI dari presenter QR lokal sehingga QR receipt benar-benar tampil tanpa membuka skema URL berbahaya.

### Removed

- Asumsi VPS Ubuntu/Nginx yang dikelola sendiri.
- Queue worker permanen pada shared hosting.
- WhatsApp otomatis dari baseline fitur yang disetujui.

## Aturan Entri

Gunakan kategori `Added`, `Changed`, `Fixed`, `Security`, `Deprecated`, dan `Removed`. Setiap perubahan fitur harus merujuk requirement, keputusan, dan change request terkait.
