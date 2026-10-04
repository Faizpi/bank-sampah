# Keamanan

## 1. Sasaran dan klasifikasi

Keamanan melindungi saldo, transaksi, identitas warga, bukti privat, operasi petugas, dan administrasi. Kontrol berlaku pada Blade, Livewire, Filament, route file, ekspor, cron, dan query agregat.

| Klasifikasi | Contoh | Perlakuan |
|---|---|---|
| Publik | harga aktif, jadwal publik, agregat desa, pengumuman | Allowlist, rate limit, cache publik terpisah |
| Internal | tugas, kapasitas, laporan operasional | Auth, permission, scope, audit sesuai risiko |
| Pribadi | profil, alamat, telepon, saldo, riwayat | Least privilege, masking, akses record-level |
| Sensitif | password hash, token, bukti, backup, secret | Enkripsi/proteksi ketat, tidak masuk log/publik |
| Finansial kritis | ledger, hold, koreksi, pay/handover | Transaction, lock, idempotensi, separation of duties, audit |

## 2. Autentikasi dan password

- Password di-hash menggunakan driver Laravel yang direkomendasikan dan tersedia pada PHP 8.5, dengan Argon2id bila lingkungan mendukung secara stabil; parameter ditinjau dan rehash saat login bila kebijakan berubah.
- Password plaintext tidak disimpan, dicatat, dikirim kembali, atau diketahui admin.
- Minimal 10 karakter, konfirmasi, dan penolakan password umum mengikuti [VALIDATION.md](VALIDATION.md).
- Perubahan kata sandi hanya melalui dua jalur. Jalur berbantuan langsung hanya untuk pengguna yang benar-benar lupa kata sandi dan tidak dapat login: admin atau superadmin berizin melalui alur PasswordAssistance, dengan `user.reset-password` dan `session.revoke`, mengubah target lain dalam scope sah sesudah verifikasi `tatap_muka` atau `callback_nomor_terdaftar` dan alasan tervalidasi 10–1000 karakter. Aktor berizin tidak dapat menargetkan diri sendiri.
- Jalur mandiri hanya dari profil pengguna terautentikasi dengan verifikasi kata sandi saat ini, kata sandi baru, dan konfirmasi tervalidasi.
- Kata sandi tidak disimpan, diketahui admin, diaudit, atau dicatat pada log. Audit hanya mencatat aktor, metode, alasan, dan hasil.
- Jalur berbantuan menyimpan hash kata sandi baru, mencabut seluruh sesi aktif target, dan mengaudit secara atomik. Jalur mandiri menyimpan hash dan mencabut sesi aktif lain sambil mempertahankan sesi saat ini bila memungkinkan secara teknis, atau mencabut seluruh sesi dan meminta autentikasi ulang bila tidak memungkinkan. Tidak ada reset publik, token, masa berlaku, penyimpanan atau pengiriman token, email, SMS, WhatsApp, rate limit reset, maupun respons permintaan reset.
- Akun nonaktif/ditolak tidak dapat login. Perubahan status akun mencabut seluruh sesi aktif pengguna.
- Tidak ada akun bersama; setiap petugas/admin memiliki akun sendiri.

## 3. Keamanan sesi

- Produksi wajib HTTPS; cookie `Secure`, `HttpOnly`, dan `SameSite=Lax` atau lebih ketat setelah uji alur.
- Session ID diregenerasi saat login/perubahan privilege; logout menginvalidasi sesi dan token CSRF diregenerasi.
- Session driver database atau opsi Laravel yang kompatibel shared hosting; record sesi dapat dicabut.
- Idle timeout dan absolute timeout dibedakan: nilai awal 30 menit idle untuk admin/petugas dan 8 jam absolut; warga dapat disesuaikan berdasarkan UAT tanpa melemahkan tindakan keuangan.
- Aksi sensitif dapat meminta konfirmasi kata sandi ulang bila risiko menuntut.
- Halaman autentikasi dan privat memakai `Cache-Control: no-store`.

## 4. CSRF, XSS, SQL injection, dan request security

### CSRF

- Semua request state-changing, Livewire action, dan form memakai CSRF Laravel.
- Tidak ada perubahan data melalui GET.
- Endpoint publik QR/statistik hanya read-only.

### XSS

- Blade escaped output (`{{ }}`) adalah default; raw output hanya untuk konten yang disanitasi allowlist.
- Pengumuman, edukasi, alasan, catatan, nama file, dan data ekspor diperlakukan sebagai input tidak tepercaya.
- Content Security Policy diterapkan bertahap dan diuji terhadap Livewire/Filament; hindari inline script yang tidak diperlukan.
- SVG unggahan pengguna ditolak; ikon Lucide berasal dari aset tepercaya.

### SQL injection

- Eloquent/query builder binding digunakan; raw SQL hanya dengan binding dan review.
- Nama kolom sort/filter/report berasal dari allowlist, bukan input langsung.
- Database user produksi hanya memiliki hak yang diperlukan aplikasi; credential migrasi dapat dipisah bila hosting memungkinkan.

### Header

Aktifkan HSTS setelah HTTPS stabil, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, frame protection melalui CSP `frame-ancestors`, dan `Permissions-Policy` yang membatasi fitur browser tidak digunakan.

## 5. Rate limiting dan anti-abuse

| Endpoint/aksi | Strategi awal |
|---|---|
| Login/registrasi | Per IP dan identifier ternormalisasi; backoff; pesan generik |
| QR nasabah/bukti publik | Per IP/token prefix aman; deteksi enumeration |
| Statistik/harga publik | Per IP dan cache publik |
| Upload | Per user, jumlah, ukuran, dan concurrency |
| Ekspor | Per user/role, jumlah job aktif, rentang periode |
| Finalisasi/pembayaran/penyerahan | Idempotency key, lock, throttling UI/server |

Nilai numerik ditentukan setelah load test/UAT dan dicatat sebagai konfigurasi, bukan hard-code tersebar.

## 6. Authorization record-level

1. Setiap model utama memiliki policy atau pemeriksaan domain ekuivalen.
2. Query list menerapkan scope sebelum pagination/agregasi; action detail mengulang policy pada record.
3. Livewire method dan Filament action mengotorisasi setiap pemanggilan; visibility menu bukan kontrol.
4. File, ekspor, QR rotation, audit, dan dashboard turut diotorisasi.
5. Warga hanya record sendiri; petugas hanya assignment/area; bendahara hanya pembayaran/laporan terkait; admin dan superadmin mengikuti permission serta policy operasional, dengan superadmin mewarisi seluruh baseline admin dan permission teknis tambahan. Permission khusus ledger/koreksi tetap eksplisit. Admission panel teknis Filament memerlukan `backoffice.access` dan tidak menggantikan pemeriksaan permission, action, atau record scope.
6. IDOR diuji dengan mengganti ID warga, transaksi, file, pengajuan, dan export.
7. Approve/pay serta approve/handover memakai permission terpisah sebagaimana [PERMISSIONS.md](PERMISSIONS.md).

## 7. Ledger, idempotensi, dan database transaction

- Semua finalisasi setoran, hold, saldo keluar, koreksi, reversal, pay, dan handover memakai database transaction.
- Lock rekening/sumber/hold mencegah race condition; unique source constraint mencegah efek ganda.
- Idempotency key terikat actor, scope, dan hash payload. Retry payload sama mengembalikan hasil lama; payload berbeda ditolak.
- Saldo tersedia dihitung/diperiksa ulang di bawah lock dan tidak boleh negatif.
- Audit aksi finansial berada dalam transaction yang sama atau operasi gagal.
- Jalur gagal rollback penuh; notifikasi nonkritis diproses setelah commit.
- Laporan harian menampilkan transaksi, bukti, dan status yang dapat diekspor sesuai scope.

## 8. File privat dan upload

- Bukti, foto penjemputan/transaksi, ekspor, dan backup tidak ditempatkan pada document root.
- Database hanya menyimpan metadata/path acak; tidak menyimpan blob.
- Download memakai route yang memeriksa auth, permission, dan record scope, atau signed URL berumur pendek.
- Signed URL tidak ditulis ke log atau dikirim melalui template publik; expiration dan content disposition benar.
- Upload memeriksa ukuran, jumlah, MIME, file signature, ekstensi, dimensi, dan jenis yang dilarang; nama server acak.
- File executable, script, HTML, SVG pengguna, macro, arsip, dan path traversal ditolak.
- Gambar publik memakai turunan yang menghapus metadata sensitif.
- Header download menetapkan MIME aman dan `nosniff`; file berisiko dipaksa attachment.

## 9. QR dan statistik publik

### QR

- QR nasabah dan bukti hanya token acak berentropi tinggi, tidak memakai ID berurutan atau data pribadi.
- Token dapat dirotasi/dinonaktifkan; token disimpan sebagai hash bila lookup strategy mendukung.
- Halaman bukti hanya menampilkan nomor, tanggal, total berat, nilai, dan status yang diizinkan.
- Transaksi draf/dibalik tidak dinyatakan sah.
- Tidak ada saldo, alamat, telepon lengkap, nomor identitas, foto privat, atau catatan internal.

### Statistik

- Endpoint publik menggunakan query khusus allowlist, bukan serialisasi model internal.
- Data hanya agregat; tidak ada drill-down individu.
- Ambang minimum subjek mencegah identifikasi kelompok kecil; nilai kecil digabung/disembunyikan.
- Scope waktu/wilayah dibatasi; filter/sort allowlist; cache publik tidak pernah menerima respons privat.
- Data plastik hanya agregat pengumpulan untuk tujuan pemanfaatan lanjutan, bukan data produksi paving block.

## 10. Audit dan logging

Audit wajib untuk login penting, pengguna/akses, harga, transaksi, koreksi/reversal, ledger/hold, status, approve, pay, handover, ekspor, setting, backup/restore, dan retensi.

Audit memuat actor, action, object, old/new yang disanitasi, waktu, correlation ID, dan konteks aman. Audit tidak dapat diubah pengguna operasional.

Application log tidak boleh memuat:

- password/hash yang tidak perlu, token reset, cookie, session ID, secret/API key;
- URL signed lengkap;
- isi bukti/file;
- nomor identitas/telepon/alamat lengkap;
- dump request, model, atau SQL dengan data sensitif.

Gunakan incident ID dan masking. Akses ke log dibatasi pengelola teknis.

## 11. Secret dan konfigurasi

- Pengaturan teknis non-secret dapat dikelola melalui UI terotorisasi dengan `system.settings.manage`, validasi server, scope teknis, dan audit. UI ini tidak menerima, menampilkan, atau menyimpan secret.
- Secret hanya melalui `.env`/secret store CI/hosting; `.env` tidak di-commit dan tidak berada di document root.
- `APP_KEY`, credential DB/mail/S3, token deployment, dan credential backup berbeda antar-environment.
- `APP_DEBUG=false` di produksi.
- Rotasi secret memiliki prosedur dampak: credential DB/mail/S3 diganti; session/token yang terdampak diinvali­dasi; `APP_KEY` tidak diganti tanpa rencana re-enkripsi.
- Secret tidak disalin ke dokumentasi, tiket, chat, nama file, command history, atau log.
- Hak file `.env`, storage, dan backup dibuat paling ketat yang didukung Hostinger.

## 12. Database dan backup

- MySQL 8.0.30 memakai user least privilege, password kuat, host restriction bila tersedia, dan TLS bila didukung.
- Backup database harian dan media berkala; salinan terenkripsi/terproteksi berada terpisah dari hosting utama.
- Backup memuat checksum, status, waktu, dan retensi; akses terbatas.
- Uji restore berkala dilakukan pada environment terisolasi, bukan menimpa produksi.
- Sasaran awal RPO maksimal 24 jam dan RTO maksimal 8 jam.
- Backup tidak dianggap valid sebelum verifikasi integritas dan latihan restore.
- Penghapusan/retensi backup mengikuti kebutuhan hukum/operasional yang disetujui dan diaudit.

## 13. Dependency dan supply chain

- `composer.lock` dan lockfile frontend di-commit; install produksi memakai versi terkunci dan tanpa dev dependency.
- Dependency audit dijalankan di lokal/CI; temuan kritis memblokir deploy atau memiliki keputusan mitigasi tertulis.
- Paket hanya dari sumber tepercaya; minimalkan plugin Filament/Livewire pihak ketiga.
- Build Vite dilakukan di lokal/CI; server tidak mengandalkan Node.js.
- Artefak build dan source release dapat diberi checksum.
- PHP web dan CLI sama-sama diverifikasi 8.5 sebelum migrasi/scheduler.

## 14. PWA dan browser storage

- Service worker hanya cache aset/versioned dan informasi umum allowlist.
- Tidak cache profil, saldo, transaksi, pengajuan, notifikasi, respons Livewire privat, signed URL, atau token.
- Tidak ada offline queue untuk tindakan keuangan.
- Logout membersihkan cache/data privat aplikasi yang relevan; update service worker menghapus cache versi lama.
- Hindari menyimpan token autentikasi di `localStorage`.

## 15. Prosedur insiden

### Severity

| Level | Contoh | Target respons awal |
|---|---|---|
| S1 Kritis | kebocoran data, takeover admin, ledger berubah ganda, backup hilang | segera, hentikan dampak |
| S2 Tinggi | akses lintas warga, file privat terbuka, pembayaran salah | hari yang sama |
| S3 Sedang | brute force meningkat, error berulang tanpa kebocoran | ≤1 hari kerja |
| S4 Rendah | anomali minor/log hygiene | sesuai backlog operasional |

### Langkah

1. **Deteksi dan catat:** waktu, reporter, incident ID, gejala, sistem terdampak; jangan salin secret/data berlebih.
2. **Containment:** maintenance mode bila perlu, cabut sesi/akun, rotasi credential terarah, nonaktifkan endpoint/job, lindungi log dan backup.
3. **Preservasi:** simpan log/audit/checksum dan snapshot yang diperlukan tanpa memodifikasi bukti.
4. **Analisis:** tentukan akar masalah, rentang waktu, record/pengguna terdampak, dan integritas ledger.
5. **Eradikasi:** perbaiki kontrol, dependency, permission, secret, atau data melalui koreksi/reversal resmi.
6. **Pemulihan:** restore bila diperlukan, jalankan migration/test, verifikasi laporan, akses, dan monitoring.
7. **Komunikasi:** pengelola menentukan pemberitahuan pihak terdampak dan kewajiban yang berlaku; informasi akurat tanpa klaim belum terbukti.
8. **Tinjauan:** dokumentasikan timeline, dampak, tindakan, test regresi, dan perbaikan SOP.

Khusus dugaan saldo ganda: hentikan pengulangan, periksa idempotency/audit/ledger, jangan edit saldo langsung, dan lakukan koreksi/reversal berizin.

## 16. Checklist rilis keamanan

- HTTPS dan header aman aktif; debug mati.
- Password/session/rate limit diuji.
- Matriks permission dan IDOR diuji pada menu, URL, Livewire, Filament, file, ekspor.
- Klik ganda dan konkurensi ledger lulus.
- Upload privat dan signed route lulus.
- QR/statistik tidak membuka data pribadi.
- Formula injection pada Excel dan XSS konten lulus.
- Dependency audit tanpa risiko kritis tak tertangani.
- Backup terbaru valid dan rollback tersedia.
- Rehearsal MySQL 8.0.30 disposable release tercatat sebelum UAT/production; bila IMP-107 termasuk baseline rilis, bukti trigger append-only/immutability MySQL terisolasi tersedia. Hasil SQLite tidak diperlakukan sebagai bukti MySQL production.
- Cron/timezone serta queue terbatas terverifikasi.
- Secret scan source/build/log lulus.

## 17. Referensi

- [PERMISSIONS.md](PERMISSIONS.md)
- [VALIDATION.md](VALIDATION.md)
- [DATA_MODEL.md](DATA_MODEL.md)
- [DEPLOYMENT.md](DEPLOYMENT.md)
- [TEST_PLAN.md](TEST_PLAN.md)
- [OPERATIONS.md](OPERATIONS.md)
