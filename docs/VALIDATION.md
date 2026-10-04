# Kontrak Validasi

## 1. Prinsip

1. Validasi browser membantu pengguna; validasi server adalah otoritas.
2. Input dinormalisasi sebelum divalidasi, tetapi nilai finansial/berat tidak boleh dikoreksi diam-diam.
3. Validasi bentuk, aturan bisnis, permission, scope record, status, dan konkurensi adalah lapisan berbeda dan semuanya wajib.
4. Kegagalan mengembalikan pesan Bahasa Indonesia yang spesifik pada field atau tindakan, mempertahankan input aman, memindahkan fokus ke ringkasan kesalahan, dan tidak menjalankan efek sukses.
5. Unique constraint, foreign key, check constraint yang didukung, dan database transaction melindungi invariant setelah validasi aplikasi.
6. Zona waktu input/tampilan adalah `Asia/Jakarta`; waktu tersimpan memakai representasi konsisten yang ditetapkan arsitektur.
7. SQLite `:memory:` memvalidasi perilaku aplikasi dan intent schema portabel pada gate harian. Perilaku production-engine MySQL 8.0.30, termasuk locking, transaction, constraint, dan trigger bila relevan, memerlukan evidence release-validation MySQL terpisah sebelum UAT/production; hasil SQLite tidak boleh diklaim sebagai buktinya.

## 2. Normalisasi umum

| Data | Normalisasi | Larangan |
|---|---|---|
| Teks | Trim sisi luar, Unicode normal form, line ending konsisten. | Jangan menghapus karakter bermakna atau menyimpan HTML mentah tanpa sanitasi. |
| Nama | Trim, spasi berulang menjadi satu, pertahankan kapitalisasi yang dimasukkan bila wajar. | Jangan mengizinkan hanya simbol/whitespace. |
| Telepon Indonesia | Hapus spasi/tanda baca; normalisasi `08…` menjadi format kanonik nasional atau E.164 untuk integrasi. | Jangan menebak nomor yang panjangnya tidak valid. |
| Kode/slug | Trim, kapitalisasi/slug sesuai domain. | Jangan menerima separator atau karakter di luar allowlist. |
| Rupiah | Input UI boleh berformat, server menghapus pemisah tampilan lalu parse integer. | Pecahan, notasi ilmiah, `NaN`, nilai negatif yang tidak sah. |
| Berat | Parse decimal string dengan titik sebagai separator canonical; UI Indonesia dapat mengonversi koma secara eksplisit. | Float biner sebagai sumber kebenaran, lebih dari tiga desimal, notasi ilmiah. |
| Tanggal | Parse format eksplisit dan konversi ke tanggal bisnis. | Tanggal ambigu atau timezone browser sebagai satu-satunya sumber. |
| Token | Tidak diubah selain decoding URL standar yang diperlukan. | Trim/ubah case token acak. |

## 3. Akun dan pengguna

### Registrasi dan profil

| Field | Aturan |
|---|---|
| `name` | Wajib; string 2–120 karakter; bukan whitespace; karakter kontrol ditolak. |
| `phone` | Wajib; nomor Indonesia valid setelah normalisasi; unik pada akun berlaku; 10–15 digit format canonical. |
| `password` | Wajib saat membuat/mengganti; minimal 10 karakter; confirmation sama; diperiksa terhadap password umum/terbocor bila layanan tersedia tanpa mengirim plaintext ke log. |
| `dusun_id`, `rw_id`, `rt_id` | Wajib sesuai kebijakan; record aktif; relasi parent konsisten. |
| `address` | Wajib; 5–500 karakter; teks biasa. |
| `identity_number` | Opsional hanya bila kebijakan pengelola mengharuskan; format dan uniqueness terkontrol; tidak ditampilkan penuh. |
| `terms_accepted` | Harus `true` setelah kontrol berlabel yang semula tidak dicentang diterima secara afirmatif; versi saat ini v1.0 dan waktu persetujuan dicatat server disimpan pada `users.terms_version` serta `users.terms_accepted_at`. Dalam operasi yang sama, sistem wajib menambahkan satu catatan riwayat append-only milik pengguna berisi versi diterima dan waktu server; pasangan pengguna/versi harus unik dan catatan lama tidak boleh ditimpa atau dihapus secara operasional. Jika belum diterima, tampilkan kesalahan berlabel yang meminta persetujuan terhadap [Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0](TERMS_AND_PRIVACY.md). |
| `status` | Tidak dapat ditetapkan warga; transisi melalui aksi berizin. |

### Login dan perubahan kata sandi

- Kredensial wajib ada, tetapi pesan gagal tetap generik.
- Percobaan login dibatasi per kombinasi IP/akun secara proporsional.
- Jalur berbantuan admin hanya menerima target selain aktor yang benar-benar lupa kata sandi dan tidak dapat login, metode `tatap_muka` atau `callback_nomor_terdaftar`, alasan 10–1000 karakter, pelaku dengan `user.reset-password` dan `session.revoke`, serta scope target yang sah.
- Jalur mandiri hanya menerima aktor terautentikasi pada profilnya sendiri, kata sandi saat ini yang cocok, kata sandi baru, dan confirmation. Jalur ini tidak menerima target, metode verifikasi, atau alasan dari pengguna.
- Kata sandi baru wajib minimal 10 karakter, confirmation sama, dan lulus kebijakan password umum/terbocor bila layanan tersedia. Kata sandi tidak boleh menjadi input audit atau log.
- Dalam satu transaction, Jalur A menyimpan hash, mencabut seluruh sesi aktif target, dan mencatat aktor, metode verifikasi, alasan, serta hasil tanpa secret. Jalur B menyimpan hash, mencabut sesi aktif lain sambil mempertahankan sesi saat ini bila mungkin secara teknis, atau mencabut seluruh sesi dan mewajibkan autentikasi ulang; audit mencatat aktor, metode `mandiri_profil`, alasan sistem `perubahan_mandiri`, serta hasil tanpa secret. Tidak ada input atau validasi token reset.

### Role dan permission

- Role/permission harus berasal dari katalog [PERMISSIONS.md](PERMISSIONS.md).
- Tidak boleh menghapus permission yang masih diperlukan proses sistem tanpa migrasi terencana.
- Pemberian permission sensitif memerlukan alasan dan konfirmasi; pengguna tidak boleh mengubah role sendiri.

## 4. Wilayah, nasabah, dan QR

| Objek | Validasi |
|---|---|
| Dusun/RW/RT | Nama 1–100; kode 1–30; unik dalam parent; parent aktif; tidak boleh membentuk siklus. |
| Area pelayanan | Nama wajib; minimal satu wilayah; aturan kapasitas nonnegatif; status enum. |
| Nomor nasabah | Dibuat sistem; pola tetap; unik; immutable setelah terbit kecuali prosedur migrasi resmi. |
| Token QR nasabah | Dibuat CSPRNG; panjang/entropi memadai; unik; hash bila desain memilih validasi hash; tidak menerima token buatan pengguna. |
| Scan nasabah | Token aktif dan record aktif; petugas berizin pada area; nama tetap dikonfirmasi sebelum transaksi. |

## 5. Master sampah dan harga

| Field | Aturan |
|---|---|
| Kode jenis/kategori | Wajib, 1–30, allowlist huruf/angka/hubung/garis bawah, unik. |
| Nama | Wajib, 2–120 karakter. |
| Kategori/satuan | FK aktif dan sesuai domain. Satuan berat fisik hanya dapat dikonversi ke `kg` bila faktor konversi ditetapkan; satuan non-berat tidak dikonversi otomatis. |
| Kondisi diterima | Minimal satu kondisi aktif dari master `waste_conditions`; pasangan jenis/kondisi harus unik. |
| Urutan | Integer 0–9999. |
| Harga | Integer rupiah `0..9_000_000_000_000_000`; harga nol memerlukan konfirmasi kebijakan. |
| Berlaku mulai | Wajib; timestamp/tanggal bisnis valid. |
| Berlaku sampai | Opsional; harus sesudah mulai; tidak tumpang tindih dengan harga pada scope sama. |
| Foto | Mengikuti validasi file; alt text/deskripsi wajib bila dipublikasikan. |

Finalisasi setoran melakukan validasi ulang harga aktif di dalam transaction. Harga dari browser tidak dipercaya.

## 6. Setoran dan detail transaksi

### Header

- Nasabah wajib aktif dan dapat diakses petugas.
- Petugas wajib aktif, memiliki `deposit.create/finalize`, dan berada dalam scope lokasi/tugas.
- Metode adalah enum `langsung`, `penjemputan`, atau `keliling`; referensi sumber wajib cocok untuk dua metode terakhir.
- Waktu transaksi tidak boleh melampaui batas backdate/future yang ditetapkan; override memerlukan permission dan alasan.
- Idempotency key wajib berupa UUID/ULID atau token acak valid dan unik pada scope perintah.

### Detail

| Field | Aturan |
|---|---|
| `waste_type_id` | Wajib, aktif pada waktu pembuatan; duplikasi jenis/kondisi dalam satu transaksi digabung atau ditolak secara konsisten. |
| `weight` | Decimal lebih dari `0`, maksimal tiga desimal, maksimum operasional dikonfigurasi; tidak dipotong diam-diam. |
| `condition` | Kondisi aktif yang dipasangkan ke jenis melalui `waste_type_conditions`; kondisi tidak diterima tidak dapat difinalkan. |
| `price_snapshot` | Diambil server dari harga aktif; integer nonnegatif; tidak dikirim sebagai otoritas dari client. |
| `subtotal` | Dihitung server sesuai BR-RND; nilai client diabaikan. |

### Finalisasi

1. Draf berstatus benar dan belum memiliki mutasi final.
2. Minimal satu detail valid.
3. Bukti wajib bila kebijakan alur menetapkan.
4. Total server sama dengan penjumlahan subtotal.
5. Idempotency key dan payload hash konsisten.
6. Record dikunci; harga diperiksa; transaksi, ledger, bukti, audit, dan status sumber disimpan atomik.
7. Bila satu pemeriksaan gagal, tidak ada mutasi atau status sukses.

## 7. Saldo, hold, koreksi, dan reversal

- Nominal mutasi dan hold adalah integer positif; arah/jenis menentukan efek, bukan tanda negatif bebas.
- Rekening, sumber, dan jenis efek wajib valid serta unik.
- Saldo tersedia dihitung ulang di dalam lock sebelum hold, saldo keluar, koreksi pengurangan, atau reversal.
- Hold hanya dibuat untuk pengajuan aktif, belum memiliki hold aktif, dan nominal sama dengan snapshot pengajuan.
- Pelepasan/konversi hold hanya berlaku pada hold `aktif`; pengulangan mengembalikan hasil lama atau konflik aman.
- Koreksi wajib memiliki alasan 10–1000 karakter, referensi transaksi, nilai baru yang tervalidasi, dan bukti untuk kasus yang ditetapkan SOP.
- Reversal wajib menyebut alasan dan record asli yang belum pernah dibalik penuh.
- Nilai baru yang menyebabkan saldo tersedia negatif ditolak dan diarahkan ke prosedur keputusan resmi, tidak dipaksakan.

## 8. Penjemputan dan kapasitas

| Field | Aturan |
|---|---|
| Foto | Minimal satu; lolos validasi file; wajib sebelum submit. |
| Jenis/perkiraan | Minimal satu jenis; perkiraan jumlah/berat positif jika diisi; bukan dasar saldo. |
| Alamat | Wajib; 5–500 karakter; wilayah dan area pelayanan aktif. |
| Tanggal pilihan | Tanggal layanan aktif, tidak lampau, tidak libur, berada dalam horizon pemesanan. |
| Catatan | Opsional, maksimum 1000, teks biasa. |
| Kapasitas | Nilai batas nonnegatif; kombinasi wilayah/petugas/kendaraan valid; reservasi dilakukan atomik. |
| Keputusan | Tolak wajib alasan; terima memerlukan slot; jadwal memerlukan petugas aktif. |
| Penyelesaian | Memerlukan status `dijemput` dan transaksi setoran final tertaut. |

Transisi yang tidak tercantum pada BR-PUP-009 ditolak. Pembatalan warga setelah `menuju_lokasi` ditolak dengan penjelasan.

## 9. Pencairan

- Nominal integer positif, memenuhi minimum aktif, tidak melebihi saldo tersedia saat submit.
- Rekening dan pengajuan dikunci saat membuat hold.
- Warga hanya dapat memilih metode/lokasi/jadwal yang aktif.
- Approver bukan pengaju berbantuan pada record sama; permission `withdrawal.approve` wajib.
- Penolakan memerlukan alasan 10–1000 karakter.
- Persetujuan menetapkan batas ambil yang valid dan assignment payer/lokasi bila diperlukan.
- Pembayaran memerlukan status `disetujui/siap_diambil`, `withdrawal.pay`, identitas penerima terkonfirmasi, nominal tetap sama, dan bukti valid.
- `paid_at` diisi server; tidak boleh selesai dua kali.
- Scheduler kedaluwarsa hanya memproses record melewati `expires_at` yang belum dibayar.

## 10. Sembako

- Paket: nama 2–120, isi 3–1000, nilai integer positif untuk penukaran saldo, periode valid, status enum; tidak ada field jumlah stok rinci.
- Nilai paket disnapshot saat pengajuan.
- Bantuan gratis memakai jenis sumber yang berbeda dan tidak boleh masuk alur hold/saldo keluar.
- Pengajuan memerlukan paket aktif dan saldo cukup.
- Persetujuan memerlukan hasil cek ketersediaan manual, catatan, dan `grocery.approve`.
- Persiapan/siap diambil mengikuti urutan status.
- Handover memerlukan `grocery.handover`, penerima terverifikasi, bukti valid, dan status `siap_diambil`.
- Selesai tidak dapat diulang; tolak/batal/kedaluwarsa melepas hold tanpa saldo keluar.

## 11. Target, keliling, estimasi, dan publik

### Target

- Nama/tujuan wajib; jenis/scope valid; berat target decimal positif maksimal tiga desimal; mulai < selesai.
- Visibilitas publik boolean/enum dan metrik publik harus allowlist.
- Progres tidak diterima dari input admin; dihitung dari transaksi final bersih.

### Layanan keliling

- Titik 3–255 karakter, RT/RW aktif, waktu mulai < selesai, minimal satu petugas dan jenis diterima.
- Jadwal petugas/titik tidak boleh overlap.
- Kapasitas nonnegatif dan status mengikuti `draf → dipublikasikan → dibuka → ditutup`, dengan `dibatalkan` sebagai endpoint sah sebelum dibuka.
- Transaksi bertipe keliling memerlukan jadwal `dibuka` dan petugas terdaftar.

### Estimasi

- Jenis aktif, harga aktif, berat positif maksimal tiga desimal.
- Hasil dihitung server/client dari data publik tepercaya tetapi tidak disimpan sebagai transaksi/hold.
- Penafian wajib hadir; ketika harga tidak ada, jangan tampilkan estimasi angka.

### Statistik dan QR publik

- Parameter periode/region berasal dari allowlist dan dibatasi rentangnya.
- Token QR harus cocok secara constant-time bila membandingkan hash; rate limit dan respons tidak mengungkap keberadaan ID internal.
- Query publik hanya memilih kolom allowlist dan menerapkan ambang privasi.
- Data publik tidak menerima nama field bebas, raw SQL, atau sort column di luar allowlist.

## 12. Notifikasi, WhatsApp, pengumuman, dan laporan

- Template notifikasi/WhatsApp memakai key yang terdaftar; placeholder hanya dari allowlist dan di-escape/URL-encode.
- Nomor `wa.me` dinormalisasi; aplikasi hanya menghasilkan URL dan tidak menerima status “terkirim”.
- Pengumuman: judul 3–160, isi 3–10.000, audiens enum, periode valid, konten disanitasi.
- Filter laporan: periode wajib dalam batas; wilayah/status/jenis dari enum/FK; sort dan kolom ekspor allowlist.
- Nama file ekspor dibuat server; tidak memakai input path pengguna.
- Nilai sel Excel yang dimulai `=`, `+`, `-`, atau `@` dinetralkan sesuai strategi ekspor.

## 13. File dan media

| Jenis | Format yang diterima | Batas awal | Penyimpanan |
|---|---|---:|---|
| Foto penjemputan | JPEG atau PNG pada input; dinormalisasi menjadi JPEG | 1 MB/file; maksimal 2 foto | Privat |
| Foto transaksi/sembako | JPEG, PNG, WebP berdasarkan pemeriksaan MIME dan signature | 5 MB/file; maksimal 1 sesuai alur | Privat |
| Foto master/pengumuman | JPEG, PNG, WebP | 5 MB/file | Publik terkontrol atau hasil turunan; asli privat |
| Bukti pembayaran | JPEG atau PNG; dinormalisasi menjadi JPEG | Maksimal 1 MB setelah kompresi | Privat |
| Bukti penyerahan | JPEG, PNG, WebP, PDF | 5 MB/file | Privat |
| Ekspor | XLSX yang dibuat sistem | Batas sesuai job/hosting | Privat dan kedaluwarsa |

Aturan tambahan:

1. Ekstensi harus cocok dengan MIME/signature; nama asli tidak menjadi nama path.
2. File executable, SVG unggahan pengguna, HTML, arsip, macro, dan polyglot mencurigakan ditolak.
3. Dimensi gambar minimum/maksimum ditetapkan secara operasional; metadata lokasi EXIF dihapus dari turunan publik.
4. Path tidak boleh menerima `..`, separator bebas, atau URL eksternal sebagai file lokal.
5. Upload parsial dibersihkan; record bisnis tidak final bila file wajib gagal disimpan.
6. File privat diunduh melalui route terotorisasi/signed URL sebagaimana [SECURITY.md](SECURITY.md).

## 14. Status dan konkurensi

- Enum/value object wajib digunakan untuk status; string bebas ditolak.
- Transisi diperiksa pada model/service domain dan diuji exhaustively.
- Aksi Livewire menonaktifkan tombol saat request, tetapi idempotensi server tetap wajib.
- Operasi kritis memakai transaction, row lock yang sesuai, optimistic version bila diperlukan, dan unique constraint.
- Konflik versi menampilkan bahwa data telah berubah dan meminta refresh; jangan menimpa perubahan diam-diam.

## 15. Kontrak pesan kesalahan

| Kondisi | Pesan pengguna | Efek |
|---|---|---|
| Field kosong/format salah | Sebut field dan cara memperbaiki. | Fokus ke field/ringkasan; tidak menyimpan. |
| Permission/scope gagal | “Anda tidak memiliki akses untuk tindakan ini.” | HTTP 403; tidak mengungkap record. |
| Record tidak ditemukan/di luar scope | Pesan tidak ditemukan yang generik. | HTTP 404 sesuai strategi anti-enumerasi. |
| Status berubah | “Data telah berubah. Muat ulang sebelum melanjutkan.” | HTTP 409/validasi state; tidak menimpa. |
| Idempotency payload konflik | “Permintaan yang sama sudah digunakan untuk data berbeda.” | HTTP 409; tidak membuat efek baru. |
| Kapasitas penuh | Sebut tanggal penuh dan tampilkan alternatif. | Tidak mereservasi tanggal penuh. |
| Saldo kurang | Sebut saldo tersedia dan batas nominal jika pengguna berhak. | Tidak membuat hold. |
| Upload gagal | Sebut format/ukuran atau kegagalan penyimpanan tanpa path internal. | Tidak final bila bukti wajib. |
| Kegagalan internal | Pesan referensi insiden generik. | Rollback; detail hanya pada log tersanitasi. |

Pesan tidak boleh menampilkan stack trace, SQL, path server, secret, token, atau data warga lain.

## 16. Traceability validasi

| Area | Requirement | Aturan bisnis | Test utama |
|---|---|---|---|
| Akun | AUTH-001–003 | BR-AUTH-* | TC-AUTH-* |
| Setoran/harga | PRC-001–002, DEP-001–003 | BR-PRC-*, BR-WGT-*, BR-RND-*, BR-DEP-* | TC-PRC-*, TC-DEP-* |
| Ledger | BAL-001–002 | BR-BAL-* | TC-BAL-* |
| Penjemputan | PUP-001 | BR-PUP-* | TC-PUP-* |
| Pencairan | WDR-001 | BR-WDR-*, BR-EXP-* | TC-WDR-* |
| Sembako | GRC-001 | BR-GRC-*, BR-EXP-* | TC-GRC-* |
| Publik/QR | QRV-001, PUB-001–002 | BR-QRV-*, BR-PUB-* | TC-QRV-*, TC-PUB-* |
| File/laporan | RPT-001 | BR-FIL-*, BR-RPT-* | TC-RPT-*, TC-SEC-* |

## 17. UI/UX regression checklist

- Primary role flows are usable at 360px, 390px, 768px, and desktop widths without horizontal scrolling.
- Every multi-step flow exposes the current step, preserves safe input, and presents a review before an irreversible submit.
- Validation failures render a visible summary, move focus to it, and keep the user on the relevant step.
- Destructive or irreversible actions require an explicit confirmation that states the affected record and consequence.
- Statuses and operational metrics use human-readable labels; raw enum values and internal IDs are not presented as the primary UI.
- Upload controls state accepted formats, file-size limits, and count limits before submission.
- Offline/online status is visible where field work depends on connectivity and uses an accessible live region.
- Tables provide a mobile-readable alternative, human labels for filters, and a clear empty state.
- Browser UAT must still be run against disposable MySQL before release; SQLite/PHPUnit results do not replace production-engine evidence.
