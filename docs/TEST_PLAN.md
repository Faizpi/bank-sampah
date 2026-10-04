# Rencana Pengujian

## 1. Sasaran

Pengujian membuktikan seluruh baseline memenuhi [REQUIREMENTS.md](REQUIREMENTS.md), invariant [BUSINESS_RULES.md](BUSINESS_RULES.md), permission, validasi, keamanan, desain, deployment, dan SOP. Pest 4 adalah runner utama. Browser/E2E dipakai selektif untuk alur berisiko dan integrasi Livewire/Filament.

Prioritas: P0 finansial/otorisasi/privasi; P1 operasional utama; P2 informasi/presentasi. Jalur gagal wajib dibuktikan tidak meneruskan jalur sukses.

## 2. Tingkat pengujian

| Tingkat | Fokus | Contoh |
|---|---|---|
| Unit | Value object, rumus, pembulatan, status, policy helper | Money, Weight, AvailableBalance, transitions |
| Feature/integration | HTTP/Livewire/action, DB transaction, policy, file, notification | finalisasi setoran, hold, pay, correction |
| Browser/E2E | Alur nyata, JS/Livewire/Filament, fokus, upload, navigation | registrasi, setoran, pencairan, warga berbantuan |
| Security | Auth, IDOR, CSRF/XSS/SQLi, upload, rate limit, secret, QR/publik | lintas warga, file privat, formula Excel |
| Accessibility | WCAG AA otomatis+manual | keyboard, screen reader, focus, status |
| Responsive | 360/390/768/1280 | shell, bottom nav, tabel, dialog/sheet |
| UAT | Kesesuaian proses desa dan bahasa | warga, petugas, bendahara, admin |
| Deployment/operations | Hostinger, PHP, cron, backup, rollback | scheduler timezone, restore drill |

## 3. Environment dan data

- Unit/feature dan quality gate implementasi harian memakai SQLite `:memory:` sebagai database test terisolasi normatif.
- Runtime lokal memakai Laragon dengan MySQL 8.0.30 dan InnoDB. Laragon adalah stack lokal, bukan engine database; bukti runtime ini terpisah dari suite SQLite.
- Suite kritis untuk MySQL 8.0.30 tetap berupa release-validation terjadwal pada environment disposable sebelum UAT/production, dengan evidence environment/runner/waktu/skenario terpisah. Hasil SQLite tidak membuktikan perilaku MySQL production, termasuk locking, constraint, transaction, atau trigger khusus engine.
- Browser test memakai environment nonproduksi dengan storage/mail/queue fake atau sandbox terkontrol.
- Clock dapat dibekukan pada `Asia/Jakarta` untuk expiry, periode harga, target, dan laporan.
- Factory menyediakan lima role, beberapa RT/RW, warga lintas scope, harga lama/baru, saldo/hold, status terminal, file privat, dan record koreksi/reversal.
- Tidak memakai data pribadi produksi. Data finansial test memiliki expected ledger eksplisit.
- Test paralel tidak berbagi akun, idempotency key, nomor bisnis, atau file.

## 4. Target coverage

Coverage adalah indikator, bukan pengganti assertion:

- Domain kritis Ledger, Deposits, Withdrawals, Groceries, Corrections, Permissions: minimal 90% line dan 85% branch pada service/value object inti.
- Modul bisnis lain: minimal 80% line pada action/policy/rule.
- UI presentasional: tidak dipaksa coverage tinggi; diuji melalui component/browser/accessibility.
- Setiap requirement memiliki minimal satu test positif dan satu test gagal yang relevan.
- Mutation testing dapat diterapkan pada rumus saldo/pembulatan/state machine bila tool kompatibel.

Release diblokir oleh kegagalan P0/P1, test flaky yang menyentuh kritis, atau requirement tanpa traceability.

## 5. Kasus Given/When/Then kritis

### Saldo dan transaksi ganda

**TC-BAL-001 — Rumus saldo tersedia (P0)**  
**Given** ledger masuk Rp100.000, keluar Rp25.000, dan hold aktif Rp30.000  
**When** saldo tersedia dihitung  
**Then** hasil Rp45.000 dan sama pada dashboard, policy pengajuan, serta laporan.

**TC-DEP-003 — Klik ganda finalisasi (P0)**  
**Given** satu draf valid dan satu idempotency key  
**When** dua request finalisasi bersamaan dikirim  
**Then** hanya satu transaksi final, satu mutasi masuk, satu nomor bukti, dan request lain mendapat hasil yang sama.

**TC-BAL-002 — Hold bersamaan (P0)**  
**Given** saldo tersedia Rp50.000  
**When** dua pengajuan Rp40.000 diproses bersamaan  
**Then** hanya satu hold berhasil dan saldo tidak negatif.

**TC-BAL-003 — Penolakan tidak membuat keluar (P0)**  
**Given** pencairan dengan hold aktif  
**When** admin menolak  
**Then** hold dilepas, saldo keluar tidak dibuat, status ditolak, alasan/audit/notifikasi tersimpan.

### Koreksi, reversal, harga lama

**TC-DEP-002 — Koreksi final (P0)**  
**Given** setoran final Rp20.000  
**When** admin berizin mengoreksi menjadi Rp17.000 dengan alasan/bukti  
**Then** transaksi asli tetap ada, penyesuaian Rp3.000 tercatat, warga melihat sebelum/sesudah/alasan/dampak, dan audit lengkap.

**TC-DEP-004 — Batal koreksi (P0)**  
**Given** form koreksi telah menghitung dampak  
**When** pengguna membatalkan konfirmasi  
**Then** tidak ada correction, mutasi, perubahan transaksi, audit sukses, atau notifikasi koreksi.

**TC-PRC-002 — Harga snapshot lama (P0)**  
**Given** transaksi final memakai Rp3.000/kg  
**When** harga aktif diubah menjadi Rp3.500/kg  
**Then** transaksi/bukti lama tetap Rp3.000/kg dan transaksi baru memakai Rp3.500/kg.

**TC-PRC-003 — Periode tumpang tindih (P1)**  
**Given** periode harga aktif  
**When** admin membuat periode overlap  
**Then** validasi gagal dan harga lama tidak ditutup.

### Permission dan privasi

**TC-PERM-001 — IDOR warga (P0)**  
**Given** dua warga berbeda  
**When** warga A membuka URL/Livewire action transaksi, saldo, pickup, file, atau export warga B  
**Then** akses ditolak tanpa membocorkan data.

**TC-PERM-002 — Superadmin tidak mengoreksi saldo (P0)**  
**Given** superadmin tanpa `ledger.adjust`/`transaction.correct`  
**When** mencoba aksi melalui menu, URL, Filament action, atau service boundary  
**Then** seluruh jalur ditolak dan tidak ada perubahan ledger.

**TC-PERM-005 — Admission panel teknis back-office eksplisit (P0)**  
**Given** admin dan superadmin dengan `backoffice.access`, serta pengguna non-admin tanpa permission tersebut  
**When** mereka membuka panel teknis Filament, lalu pendatang yang diterima mencoba resource, action, atau record domain tanpa permission terkait  
**Then** hanya admin dan superadmin diterima ke panel; pengguna tanpa `backoffice.access` ditolak tanpa auto-grant atau bypass; dan admission panel tidak memberi akses domain/bisnis, action, atau record di luar permission dan scope yang tetap diuji.

**TC-PERM-003 — Approve terpisah dari pay (P0)**  
**Given** admin hanya `withdrawal.approve` dan bendahara hanya `withdrawal.pay`  
**When** masing-masing mencoba tindakan lawan  
**Then** ditolak; alur sah berhasil hanya dalam urutan approve lalu pay.

**TC-PERM-004 — Approve terpisah dari handover (P0)**  
**Given** admin approver dan petugas handover  
**When** permission silang diuji  
**Then** aksi silang ditolak dan selesai hanya setelah bukti handover.

### QR, publik, WhatsApp, inklusi

**TC-QRV-001 — QR publik terbatas (P0)**  
**Given** token bukti final valid  
**When** publik membuka halaman verifikasi  
**Then** hanya nomor, tanggal, berat, nilai, status tampil; saldo, alamat, telepon, identitas, foto privat tidak ada.

**TC-QRV-002 — Token invalid/nonfinal (P0)**  
**Given** token acak salah atau transaksi draf/dibalik  
**When** diverifikasi  
**Then** tidak dinyatakan sah dan respons tidak membantu enumerasi.

**TC-PUB-002 — Statistik publik (P0)**  
**Given** transaksi dari kelompok kecil dan beberapa RT  
**When** statistik publik diminta  
**Then** hanya metrik allowlist/agregat aman tampil, ambang privasi diterapkan, dan tidak ada drill-down individu.

**TC-WA-001 — WhatsApp manual (P1)**  
**Given** nomor/template valid  
**When** tombol dipilih  
**Then** URL `wa.me` terenkode dibuka, teks UI menyatakan “Buka WhatsApp”, dan database tidak mencatat pesan otomatis terkirim.

**TC-CST-002 — Warga tanpa smartphone (P0)**  
**Given** warga memberi persetujuan dan identitas valid  
**When** petugas membantu akun/setoran  
**Then** warga adalah pemilik transaksi, petugas pelaksana, kata sandi tidak diambil alih, dan bukti cetak/saldo tersedia.

### Penjemputan, pencairan, sembako

**TC-PUP-001 — Foto wajib dan kapasitas (P1)**  
**Given** tanggal kapasitas penuh  
**When** warga submit dengan foto valid  
**Then** tanggal tidak dipesan dan alternatif ditawarkan; tanpa foto submit ditolak.

**TC-PUP-002 — Penolakan berhenti (P0)**  
**Given** pengajuan tidak layak  
**When** petugas menolak beralasan  
**Then** tidak ada jadwal, status menuju lokasi, transaksi, atau saldo masuk.

**TC-WDR-001 — Pencairan lengkap (P0)**  
**Given** saldo cukup dan nominal valid  
**When** warga mengajukan, admin approve, bendahara bayar dengan bukti  
**Then** hold dibuat lalu dikonversi satu kali menjadi saldo keluar setelah uang diserahkan.

**TC-WDR-002 — Kedaluwarsa (P0)**  
**Given** pencairan siap diambil melewati batas  
**When** scheduler berjalan dua kali  
**Then** status kedaluwarsa dan hold dilepas tepat sekali tanpa saldo keluar.

**TC-GRC-001 — Sembako tidak tersedia (P0)**  
**Given** hold aktif dan cek manual menyatakan tidak tersedia  
**When** admin menolak  
**Then** hold dilepas dan jalur prepare/handover/saldo keluar tidak terjadi.

**TC-GRC-002 — Bantuan gratis (P1)**  
**Given** penyerahan diklasifikasikan bantuan gratis  
**When** dicatat  
**Then** tidak ada hold atau ledger keluar.

## 6. Suite domain lain

| ID | Given/When/Then ringkas | Requirement |
|---|---|---|
| TC-AUTH-001 | Dokumen publik v1.0 tersedia → kontrol persetujuan awal kosong → terima afirmatif → register menyimpan `users.terms_version`, `users.terms_accepted_at`, dan satu riwayat append-only pengguna/versi; status tetap `menunggu_verifikasi`; tanpa persetujuan muncul error berlabel; approve mengaktifkan, reject menyimpan alasan tanpa aktivasi; versi lama pada riwayat tetap ada saat versi baru diterbitkan prospektif; persetujuan ulang hanya melalui proses yang disetujui/didokumentasikan, menambah riwayat versi baru tanpa mengubah sesi atau status; penerimaan tidak membuat sesi/aktivasi | AUTH-001 |
| TC-AUTH-002 | Kredensial/status bervariasi → login/logout → sesi aman/rate limit | AUTH-002 |
| TC-AUTH-003 | Jalur A: admin dengan `user.reset-password` dan `session.revoke` memilih target lain dalam scope yang benar-benar lupa kata sandi dan tidak dapat login → verifikasi `tatap_muka`/`callback_nomor_terdaftar`, alasan 10–1000 karakter, kata sandi baru dan konfirmasi valid → hash, pencabutan seluruh sesi target, serta audit aktor/metode/alasan/hasil terjadi atomik tanpa kata sandi; self-target, kondisi lupa/tidak dapat login, permission/scope, metode/alasan, konfirmasi, minimum, dan password umum invalid ditolak tanpa perubahan. Jalur B: pengguna terautentikasi dari profil sendiri → kata sandi saat ini, kata sandi baru, dan konfirmasi valid → hash, sesi lain dicabut dengan sesi saat ini dipertahankan bila mungkin atau seluruh sesi dicabut dan autentikasi ulang diwajibkan, serta audit aktor/metode `mandiri_profil`/alasan sistem/hasil tanpa kata sandi; kata sandi saat ini salah atau validasi gagal ditolak tanpa perubahan. Tidak ada reset publik, token, email, SMS, WhatsApp, atau permintaan/rate limit reset. | AUTH-003 |
| TC-USR-001 | User bereferensi → nonaktif → histori tetap, login ditolak; `user.view` tanpa scope hanya self; area memakai StaffProfile efektif hari ini + area/RT aktif dan hanya nasabah aktif; all memakai `user.view` + `user.view.all`; detail out-of-scope 404 | USR-001 |
| TC-USR-002 | Tiap role login → dashboard → widget/action sesuai izin | USR-002 |
| TC-CST-001 | Nasabah aktif → QR/nomor → unik, scan perlu konfirmasi | CST-001 |
| TC-REG-001 | Parent/duplikasi/status diuji → simpan wilayah → hanya hierarki valid | REG-001 |
| TC-WST-001 | Kategori, satuan, jenis, dan beberapa kondisi aktif → pivot jenis/kondisi valid → katalog/transaksi; satuan berat ke `kg` hanya bila faktor ada, satuan non-berat tidak auto-convert; histori tetap, jenis baru nonaktif dibatasi | WST-001 |
| TC-PRC-001 | Harga valid → aktivasi → satu periode aktif dan audit | PRC-001 |
| TC-DEP-001 | Multi-item+3 desimal → final → subtotal half-up/total/ledger benar | DEP-001 |
| TC-NOT-001 | Event commit/notifikasi gagal → transaksi tetap, retry dedupe | NOT-001 |
| TC-ANN-001 | Audiens/periode/XSS → publish → hanya konten aman/aktif tampil | ANN-001 |
| TC-TGT-001 | Final/koreksi/reversal → progres → nilai bersih dan tutup periode | TGT-001 |
| TC-MOB-001 | Jadwal bentrok/buka → layanan → bentrok ditolak, setoran sah saat buka | MOB-001 |
| TC-EST-001 | Harga+berat → estimasi → disclaimer, tanpa transaksi/hold | EST-001 |
| TC-PUB-001 | Filter RT/RW → agregasi → transaksi final bersih dan scope izin | PUB-001 |
| TC-RPT-001 | Filter hari ini/minggu/bulan/custom → web dan XLSX → angka sama, export privat/audit | RPT-001 |
| TC-AUD-001 | Aksi penting → audit → lengkap, append-only, tanpa secret | AUD-001 |
| TC-PWA-001 | Online/offline → navigasi → umum cached; privat/financial network-only | PWA-001 |

## 7. Security test

- CSRF seluruh state-changing endpoint dan Livewire action.
- XSS tersimpan/reflected pada nama, alasan, edukasi, pengumuman, filename, filter.
- SQL injection pada search/sort/filter/report allowlist.
- Auth brute force/rate limit/session fixation/logout/revocation/cache header.
- IDOR seluruh record dan file; role escalation; mass assignment.
- Upload MIME mismatch, executable, SVG, path traversal, oversize, polyglot, unauthorized download.
- QR brute force/rate limit/token rotation; statistik differencing/kelompok kecil.
- Excel formula injection dan export expiry.
- Secret scan source, build, log, exception, audit.
- Transaction rollback pada injected failure setiap langkah kritis.
- Concurrency untuk finalisasi, hold, pay, handover, expiry.

Temuan diklasifikasikan berdasarkan dampak nyata. P0 security harus selesai sebelum rilis.

## 8. Accessibility dan responsive

### Otomatis

Jalankan axe atau alat ekuivalen pada halaman publik, auth, dashboard, form setoran, pickup, pencairan, sembako, admin table/dialog, QR, error, dan success. Tidak ada critical/serious violation yang belum diputuskan.

### Manual

- Keyboard-only: skip link, menu, bottom nav, form, upload, dialog/sheet, table/filter, toast.
- Screen reader: heading/landmark, label, error summary, status, amount, timeline, chart alternative, QR explanation.
- Kontras, focus visible, target 48 px, zoom 200%, text resize, reduced motion.
- Status dibuktikan teks+ikon+warna.
- Tidak ada karakter emoji aktual; Lucide SVG memiliki accessible name bila bermakna.

### Matriks viewport

| 360 | 390 | 768 | 1280 |
|---|---|---|---|
| warga/petugas satu kolom, nav 5 item | nilai panjang dan quick actions | form/grid/sheet-dialog | sidebar admin/tabel/filter |

Uji portrait/landscape relevan, teks panjang, error, loading, empty, success, keyboard virtual, dan safe area.

## 9. Browser/E2E wajib

1. Registrasi → admin approve/reject → login.
2. Setoran multi-item → bukti → saldo/riwayat/QR.
3. Klik ganda/retry UI pada finalisasi.
4. Pickup foto → kapasitas alternatif → execute → setoran.
5. Pencairan approve terpisah pay → bukti → ledger.
6. Sembako approve terpisah handover → bukti → ledger.
7. Koreksi → tampilan warga → laporan memperlihatkan hasil resmi.
8. Warga tanpa smartphone → kartu/nomor → bukti cetak.
9. Statistik publik/QR tanpa data privat.
10. WhatsApp manual membuka URL tanpa status otomatis.
11. PWA installability dan cache allowlist; transaksi offline ditolak jelas.
12. Admin sidebar/action sesuai permission.

Browser target: Chrome Android/desktop, Edge, Safari mobile, Firefox versi dukungan vendor. Smoke otomatis utama boleh Chromium; cross-browser kritis sebelum penerimaan.

## 10. UAT

Peserta: minimal perwakilan warga smartphone, warga tanpa smartphone, petugas lapangan, bendahara, admin, superadmin, dan pemerintah desa untuk statistik.

Setiap skenario memuat data awal, langkah, expected result, hasil aktual, bukti, severity, dan tanda tangan/keputusan. UAT mencakup 36 flow pada [USER_FLOWS.md](USER_FLOWS.md), dengan fokus bahasa, kejelasan status, prosedur gagal, bukti, dan tugas nyata.

Eksekusi teknis browser untuk transaksi kritis sudah direkam di [UAT_EVIDENCE.md](UAT_EVIDENCE.md): 10/10 flow Chromium pada disposable Laragon MySQL lulus tanpa browser error. Hasil ini belum menggantikan observasi stakeholder, pemeriksaan laporan, atau approval tertulis.

Kriteria penerimaan:

- Tidak ada defect P0/P1 terbuka.
- Seluruh requirement memiliki hasil pass atau keputusan tertulis yang tetap memenuhi baseline.
- Saldo/ledger dan hasil laporan diperiksa pada data UAT.
- Permission dan privasi disetujui pemilik proses.
- Alur warga tanpa smartphone dapat dijalankan tanpa kata sandi diambil alih.
- Pengelola memahami bahwa WhatsApp manual dan PWA offline terbatas.

## 11. Deployment dan operasi test

- PHP web/CLI 8.5, Composer 2, extension, serta Laragon MySQL 8.0.30 untuk pemeriksaan runtime lokal.
- Release rehearsal MySQL 8.0.30 disposable: migration fresh dan upgrade dari snapshot bila relevan, durasi, rollback/remigrate/no-op/cleanup, dan bukti terpisah sebelum UAT/production; tidak dipenuhi oleh hasil SQLite atau smoke Laragon.
- Untuk IMP-107, rehearsal tersebut mencakup skenario trigger MySQL terisolasi yang membuktikan penegakan append-only/immutability; hasil SQLite tidak dapat menjadi penggantinya.
- Document root/exposure probe `.env`, vendor, storage, source.
- Scheduler heartbeat dan expiry pada timezone cron Hostinger.
- Queue sync/database one-shot, retry, failed job, no daemon.
- Backup checksum dan restore drill RPO/RTO.
- Release rollback kode dan skenario restore DB/media.
- Storage quota, permission, signed route, aset Vite, cache Laravel.

## 12. Traceability requirement ke test

| Requirement | Test utama |
|---|---|
| AUTH-001–003 | TC-AUTH-001–003 |
| USR-001–002 | TC-USR-001–002, TC-PERM-001–005 |
| CST-001–002 | TC-CST-001–002 |
| REG-001 | TC-REG-001 |
| WST-001 | TC-WST-001 |
| PRC-001–002 | TC-PRC-001–003 |
| DEP-001–003 | TC-DEP-001–004, TC-BAL-001–003 |
| BAL-001–002 | TC-BAL-001–003, TC-WDR-001–002, TC-GRC-001 |
| PUP-001 | TC-PUP-001–002 |
| WDR-001 | TC-WDR-001–002, TC-PERM-003 |
| GRC-001 | TC-GRC-001–002, TC-PERM-004 |
| NOT-001 | TC-NOT-001 |
| WA-001 | TC-WA-001 |
| ANN-001 | TC-ANN-001 |
| TGT-001 | TC-TGT-001 |
| MOB-001 | TC-MOB-001 |
| EST-001 | TC-EST-001 |
| QRV-001 | TC-QRV-001–002 |
| PUB-001–002 | TC-PUB-001–002 |
| RPT-001 | TC-RPT-001 |
| AUD-001 | TC-AUD-001 |
| PWA-001 | TC-PWA-001 |

## 13. Exit criteria dan bukti

Sebelum release:

- Seluruh suite wajib hijau dan test flaky diselesaikan.
- Coverage domain kritis mencapai target.
- Test P0 concurrency, permission, QR/publik, file, dan ledger lulus pada MySQL 8.0.30 sebagai satu suite release-validation disposable yang tercatat terpisah; untuk IMP-107, trigger append-only/immutability juga terbukti pada MySQL. Hasil SQLite `:memory:` dan runtime MySQL Laragon tetap evidence harian, bukan proof deployment production.
- Browser, accessibility, responsive, security, UAT, deployment, backup/restore lulus.
- Defect residual memiliki owner, severity, mitigasi, dan tidak melanggar baseline/acceptance criteria.
- Laporan test mencatat commit, environment, waktu, runner, hasil, coverage, artefak, dan approval.
