# Catatan Keputusan Arsitektur

## Konvensi

Status: `Accepted`, `Superseded`, atau `Deprecated`. Semua keputusan di bawah berstatus `Accepted` dan mengikat baseline. Perubahan memerlukan change request, analisis dampak, persetujuan pengelola, pembaruan dokumen terkait, dan entri [CHANGELOG.md](CHANGELOG.md).

## ADR-001 — Laravel, Blade, dan Livewire tanpa React

- **Status:** Accepted
- **Konteks:** Tim membutuhkan aplikasi web mobile-first dengan server-rendered UI, interaksi cukup kaya, satu stack utama, dan operasi sederhana di shared hosting.
- **Keputusan:** Laravel 13 dengan PHP 8.3 atau lebih baru sesuai `composer.json`, Blade, Livewire 4, Alpine.js bawaan Livewire, dan Tailwind CSS 4.1+. React tidak digunakan.
- **Konsekuensi:** Domain, UI, auth, validasi, dan deploy lebih terpadu. Komponen Livewire harus menjaga boundary dan performa. Alpine tidak boleh dimuat dua kali.
- **Alternatif ditolak:** React SPA karena menambah toolchain, duplikasi state/API, dan kompleksitas deploy yang tidak diperlukan.
- **Referensi:** [ARCHITECTURE.md](ARCHITECTURE.md), [DESIGN.md](DESIGN.md).

## ADR-002 — Filament hanya untuk back-office

- **Status:** Accepted
- **Konteks:** Admin memerlukan CRUD/tabel/action data-dense, sedangkan warga dan petugas memerlukan pengalaman khusus yang sederhana dan lapangan-first.
- **Keputusan:** Filament 5 dipakai hanya untuk panel teknis back-office yang mensyaratkan `backoffice.access`. Baseline permission ini diberikan kepada `admin` dan `superadmin`; keduanya dapat memasuki `/backoffice` dan masing-masing dibedakan oleh scope teknis (lihat [PERMISSIONS.md](PERMISSIONS.md)). UI publik, warga, petugas, dan bendahara operasional dibuat khusus dengan Blade/Livewire. Filament diberi custom theme Sindangheula Green Ledger.
- **Konsekuensi:** `backoffice.access` hanya mengizinkan admission ke panel teknis, bukan permission domain atau bisnis dan bukan bypass role, policy, action, record scope, atau separation of duties. `superadmin` mewarisi seluruh hak `admin`, lalu mendapat `role.manage` dan Health. Mutasi tetap melewati policy atau action dan dicatat.
- **Alternatif ditolak:** seluruh UI memakai Filament karena tidak memenuhi saldo-first/task-first dan kontrol navigasi mobile.
- **Referensi:** [DESIGN.md](DESIGN.md), [PERMISSIONS.md](PERMISSIONS.md).

### Fondasi teknis IMP-019

IMP-019 menetapkan fondasi teknis back-office Filament 5 sesuai ADR ini: instalasi paket, custom theme Sindangheula Green Ledger, sidebar dengan enam kelompok, dan enforcement admission `backoffice.access` untuk `admin` dan `superadmin`. Fondasi ini tidak membuat resource bisnis, tidak mengubah policy atau permission domain, dan tidak menjadi bypass authorization. Resource, action, serta editor role/permission tetap mengikuti dependency dan audit masing-masing.

## ADR-003 — Hostinger shared hosting

- **Status:** Accepted
- **Konteks:** Infrastruktur final adalah Hostinger Web Hosting Premium/Business dengan hPanel, SSH/SFTP, Composer 2, MySQL 8.0.30-compatible, dan cron.
- **Keputusan:** Arsitektur dan deployment mengikuti batas shared hosting. Aset Vite dibangun lokal atau CI. Queue menggunakan `sync`. PHP web dan CLI wajib memenuhi `^8.3` serta memakai versi yang selaras.
- **Konsekuensi:** Tidak ada root, Supervisor, Redis, Horizon, WebSocket, worker permanen, queue database, atau konfigurasi Nginx sendiri. Dokumentasi tidak menjanjikan pemrosesan asinkron maupun retry otomatis.
- **Alternatif ditolak:** asumsi VPS karena tidak sesuai hosting final.
- **Referensi:** [DEPLOYMENT.md](DEPLOYMENT.md), [OPERATIONS.md](OPERATIONS.md).

## ADR-004 — MySQL 8.0.30 melalui Laragon

- **Status:** Accepted
- **Konteks:** Pengembangan lokal memakai Laragon dengan MySQL 8.0.30 dan aplikasi memerlukan transaksi ACID, FK, index, serta locking.
- **Keputusan:** Gunakan MySQL 8.0.30/InnoDB melalui driver MySQL Laravel. SQLite `:memory:` adalah runtime test normatif untuk gate implementasi harian; migration dan query diuji terhadap MySQL aktual melalui release-validation disposable terjadwal sebelum UAT/production. Perilaku engine-specific, termasuk trigger IMP-107, tetap membutuhkan bukti MySQL tersendiri dan tidak dibuktikan oleh SQLite.
- **Konsekuensi:** Dapat memakai transaction/row lock/constraint, tetapi fitur khusus MySQL yang tidak kompatibel harus dihindari atau diuji. Rupiah BIGINT, berat DECIMAL.
- **Alternatif ditolak:** database embedded atau layanan terpisah yang menambah operasi.
- **Referensi:** [DATA_MODEL.md](DATA_MODEL.md).

## ADR-005 — WhatsApp manual melalui wa.me

- **Status:** Accepted
- **Konteks:** Pengguna membutuhkan komunikasi WhatsApp tanpa gateway otomatis dan tanpa klaim palsu status pengiriman.
- **Keputusan:** Aplikasi membentuk tautan `wa.me` dan template terenkode; pengguna membuka, meninjau, lalu mengirim di WhatsApp.
- **Konsekuensi:** Tidak ada webhook, delivery receipt, retry otomatis, atau status “terkirim”. UI memakai “Buka WhatsApp”. Kegagalan membuka tidak mengubah status bisnis.
- **Alternatif ditolak:** gateway WhatsApp otomatis karena berada di luar baseline dan menambah biaya/compliance/integrasi.
- **Referensi:** WA-001 di [REQUIREMENTS.md](REQUIREMENTS.md), BR-WA-*.

## ADR-006 — Ledger sebagai sumber kebenaran saldo

- **Status:** Accepted
- **Konteks:** Saldo harus transparan, tidak negatif, dapat diperiksa melalui ledger/laporan, dan tahan koreksi/transaksi ganda.
- **Keputusan:** Saldo bersumber dari ledger append-only dan hold aktif. Rumus: total masuk dikurangi total keluar dan saldo tertahan. Koreksi/reversal membuat mutasi baru; tidak ada edit saldo langsung.
- **Konsekuensi:** Semua operasi finansial membutuhkan transaction, lock, idempotensi, unique source, audit, dan laporan yang dapat ditelusuri. Query lebih disiplin, tetapi integritas dapat dibuktikan.
- **Alternatif ditolak:** satu kolom saldo yang bebas diperbarui karena sulit diaudit dan rentan race condition.
- **Referensi:** [BUSINESS_RULES.md](BUSINESS_RULES.md), [DATA_MODEL.md](DATA_MODEL.md).

## ADR-007 — Modular monolith

- **Status:** Accepted
- **Konteks:** Baseline luas tetapi dikelola satu aplikasi/tim dan ditempatkan pada shared hosting.
- **Keputusan:** Satu deployment Laravel dengan modul domain terpisah, application action, policy, value object, event, query, dan model.
- **Konsekuensi:** Transaction lintas domain tetap sederhana dan operasi minim. Boundary harus ditegakkan agar tidak menjadi monolith tanpa struktur.
- **Alternatif ditolak:** microservices karena menambah jaringan, observability, deployment, dan konsistensi terdistribusi tanpa kebutuhan.
- **Referensi:** [ARCHITECTURE.md](ARCHITECTURE.md).

## ADR-008 — File privat di storage, bukan blob/public

- **Status:** Accepted
- **Konteks:** Sistem menyimpan foto warga, bukti transaksi atau pembayaran atau penyerahan, dan ekspor.
- **Keputusan:** File berada pada filesystem privat atau object storage kompatibel S3; database hanya menyimpan metadata, path, atau key. Akses melalui route terotorisasi atau signed URL singkat. Hanya turunan atau aset yang aman berada di publik.
- **Konsekuensi:** Authorization file wajib dan document root hanya menyajikan `public/`. Database tidak membengkak karena blob.
- **Alternatif ditolak:** blob database dan storage publik langsung karena performa dan privasi.
- **Referensi:** [SECURITY.md](SECURITY.md), [DEPLOYMENT.md](DEPLOYMENT.md).

## ADR-009 — PWA installable dengan cache terbatas

- **Status:** Accepted
- **Konteks:** Pengguna menginginkan pemasangan pada home screen dan akses informasi umum saat koneksi tidak stabil, tetapi transaksi finansial tidak boleh tersinkron ganda.
- **Keputusan:** Manifest/service worker menyediakan installability serta cache aset dan halaman informasi umum allowlist. Data privat dan seluruh aksi perubahan/keuangan adalah network-only.
- **Konsekuensi:** Offline memiliki fallback informasi, bukan transaksi offline. Cache harus terversi, dipisahkan dari respons privat, dan dibersihkan saat update/logout.
- **Alternatif ditolak:** offline transaction queue karena risiko konflik, idempotensi, dan transparansi saldo.
- **Referensi:** PWA-001, BR-PWA-*.

## ADR-010 — Paving block di luar proses produksi sistem

- **Status:** Accepted
- **Konteks:** Proposal menyebut data sampah plastik dapat mendukung rencana pemanfaatan lanjutan menjadi paving block.
- **Keputusan:** Sistem hanya menyediakan agregat jumlah/jenis plastik sebagai data pendukung. Produksi paving block tidak dikelola.
- **Konsekuensi:** Tidak ada formula, batch produksi, suhu, uji kuat tekan, stok produk jadi, distribusi, atau biaya produksi. Penambahan proses tersebut memerlukan change request baru.
- **Alternatif ditolak:** memasukkan modul produksi karena mengubah produk bank sampah menjadi sistem manufaktur dan melampaui proposal.
- **Referensi:** [PRODUCT.md](PRODUCT.md), BR-SCP-*.

## ADR-011 — Separation of duties untuk pencairan dan sembako

- **Status:** Accepted
- **Konteks:** Persetujuan dan penyerahan manfaat memiliki risiko fraud dan kesalahan bila menjadi satu aksi/permission.
- **Keputusan:** Pencairan memisahkan `withdrawal.approve` dan `withdrawal.pay`; sembako memisahkan `grocery.approve` dan `grocery.handover`.
- **Konsekuensi:** State machine, UI, policy, assignment, audit, dan test harus membedakan tindakan. Pengecualian personel memerlukan permission eksplisit serta audit tambahan, bukan default.
- **Alternatif ditolak:** satu permission “process” karena tidak memberi kontrol dan traceability memadai.
- **Referensi:** [PERMISSIONS.md](PERMISSIONS.md).

## ADR-012 — Satu baseline fitur terkunci

- **Status:** Accepted
- **Konteks:** Proposal disetujui dan seluruh fitur adalah satu baseline. Roadmap hanya urutan implementasi teknis.
- **Keputusan:** Semua requirement wajib selesai dalam ruang lingkup yang sama. Tidak ada klasifikasi subset produk atau penundaan ruang lingkup berdasarkan urutan roadmap.
- **Konsekuensi:** Acceptance/UAT mengukur seluruh baseline; perubahan memerlukan change request. Urutan teknis boleh menyesuaikan dependency tanpa menghapus fitur.
- **Alternatif ditolak:** pembagian ruang lingkup berdasarkan urutan roadmap karena bertentangan dengan persetujuan proyek.
- **Referensi:** [ROADMAP.md](ROADMAP.md), [REQUIREMENTS.md](REQUIREMENTS.md).

## ADR-013 — Klarifikasi kontrak penerimaan ketentuan dan privasi

- **Status:** Accepted
- **Konteks:** AUTH-001 sudah mewajibkan persetujuan ketentuan dengan penyimpanan versi dan waktu, tetapi belum memiliki dokumen publik kanonis yang menjelaskan batas penerimaan, privasi ringkas, dan layanan berbantuan.
- **Keputusan:** [Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0](TERMS_AND_PRIVACY.md) menjadi dokumen publik kanonis. Pendaftaran memerlukan penerimaan afirmatif v1.0, menyimpan `terms_version` dan `terms_accepted_at`, serta tetap `menunggu_verifikasi`. Penerimaan tidak menjadi verifikasi, aktivasi, autentikasi, atau login otomatis. Persetujuan layanan berbantuan tetap terpisah.
- **Konsekuensi:** Dokumen, validasi, UI, flow, dan test AUTH-001 mengacu pada kontrak yang sama. Versi baru berlaku prospektif, riwayat penerimaan dipertahankan, dan persetujuan ulang hanya mengikuti proses produk yang disetujui serta didokumentasikan.
- **Alternatif ditolak:** Menganggap persetujuan sebagai fitur baru atau sebagai pengganti verifikasi, karena keduanya mengubah baseline AUTH-001.
- **Referensi:** AUTH-001, BR-AUTH-006, TC-AUTH-001, [TERMS_AND_PRIVACY.md](TERMS_AND_PRIVACY.md).

## ADR-014 — Deferred capability gates

- **Status:** Accepted
- **Konteks:** Beberapa kemampuan memerlukan kontrak authorization, audit, atau model konten yang belum diselesaikan.
- **Keputusan:** IMP-049 tetap ditunda sampai kebijakan attachable media menetapkan pemetaan setiap `attachable_type` ke policy dan scope bisnis, serta kontrak signed URL. Permission download media tidak ditambahkan sebelum kebijakan tersebut ada. Editor checkbox assignment permission ditunda sampai IMP-019 dan IMP-107 selesai, dengan audit nilai lama/baru, alasan, pelaku, dan scope. CMS landing terstruktur juga ditunda sampai model konten, workflow publikasi, authorization, validasi, dan auditnya disetujui melalui change request.
- **Konsekuensi:** Tidak ada bypass berdasarkan uploader, role, atau media attachment. Tidak ada UI checkbox permission atau CMS landing yang diimplementasikan atau diberi ID tracker baru hanya dari keputusan ini.
- **Referensi:** [SECURITY.md](SECURITY.md), [PERMISSIONS.md](PERMISSIONS.md), IMP-049, IMP-019, IMP-107.

## ADR-015 — Health sebagai batas administrasi teknis aktif

- **Status:** Accepted
- **Konteks:** Dokumentasi harus mengikuti UI administrasi yang aktif.
- **Keputusan:** Health baca-saja adalah satu-satunya administrasi teknis aktif pada aplikasi. Secret dan perubahan infrastruktur tetap berada pada environment atau proses deployment.
- **Konsekuensi:** Permission, navigasi, SOP, test, dan dokumentasi teknis hanya boleh menyebut Health sebagai UI administrasi teknis aktif. Prosedur infrastruktur tidak boleh ditulis sebagai kapabilitas aplikasi.
- **Referensi:** [PERMISSIONS.md](PERMISSIONS.md), [SECURITY.md](SECURITY.md), [OPERATIONS.md](OPERATIONS.md), [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md).

## ADR-016 — Navigasi warga memisahkan kartu, layanan, dan riwayat

- **Status:** Accepted
- **Konteks:** Riwayat warga kini mencakup setoran, pencairan, dan penukaran sembako. Menempatkan `Setoran` dan `Riwayat` sebagai dua tujuan bottom navigation yang membuka halaman sama membuat navigasi duplikat, sedangkan mengganti `Kartu Nasabah` menghilangkan akses utama ke identitas dan QR warga.
- **Keputusan:** Bottom navigation warga menggunakan urutan `Beranda`, `Kartu Nasabah`, `Layanan`, `Riwayat`, `Akun`. `Kartu Nasabah` membuka identitas/QR; `Layanan` menaungi pengajuan dan transaksi aktif; `Riwayat` menaungi arsip `Setoran`, `Pencairan`, dan `Sembako`. Label visual `Kartu Nasabah` boleh ditampilkan sebagai `Kartu` pada viewport sempit tanpa mengubah nama kontrak.
- **Konsekuensi:** Tidak ada tujuan bottom-nav `Setoran` yang menduplikasi `Riwayat`. Active state mengikuti fungsi halaman: kartu ke `Kartu Nasabah`, arsip dan bukti setoran ke `Riwayat`, pengajuan/detail transaksi aktif ke `Layanan`.
- **Alternatif ditolak:** mempertahankan `Setoran` dan `Riwayat` dengan destination yang sama, atau menghapus `Kartu Nasabah` dari navigasi utama.
- **Referensi:** [DESIGN.md](DESIGN.md), [CHANGELOG.md](CHANGELOG.md).
