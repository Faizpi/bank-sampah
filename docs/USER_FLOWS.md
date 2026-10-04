# Alur Pengguna dan Sistem

## 1. Konvensi

Dokumen ini adalah representasi tekstual normatif dari 36 diagram pada dokumen flowchart final. ID mengikuti urutan `DIAGRAMS` pada generator final. Setiap cabang gagal adalah endpoint atau loop perbaikan; cabang tersebut tidak boleh meneruskan efek sukses. Requirement dan aturan bisnis tetap menjadi sumber perilaku rinci.

## 2. Perencanaan dan sistem

### FL-01 — Tahapan Pengembangan Aplikasi
- **Aktor:** tim pengembang, pengelola, petugas, perwakilan warga.
- **Alur utama:** analisis kebutuhan → perancangan sistem/UI → pengembangan modul → pengujian → pemeriksaan kesesuaian → penerapan → pemeliharaan/evaluasi.
- **Keputusan/gagal:** bila belum sesuai, kembali ke analisis; tidak boleh diterapkan.
- **Hasil:** baseline diterapkan dan terus dievaluasi.
- **Jejak:** seluruh requirement; [TEST_PLAN.md](TEST_PLAN.md); [ROADMAP.md](ROADMAP.md).

### FL-02 — Peta Jalan Pengembangan
- **Aktor:** pemerintah desa, pengelola, tim pengembang.
- **Alur utama:** baseline disetujui → fondasi/akun/master → transaksi dan layanan → program/transparansi → integrasi → pengujian/UAT → peluncuran.
- **Keputusan/gagal:** bila belum sesuai baseline, perbaiki lalu ulangi integrasi dan pengujian.
- **Hasil:** seluruh baseline diterima sebagai satu kesatuan.
- **Jejak:** seluruh requirement; NFR-SCOPE-001; [ROADMAP.md](ROADMAP.md).

### FL-03 — Pengujian dan Penerimaan Sistem
- **Aktor:** tim pengembang, admin, petugas, warga uji.
- **Alur utama:** build siap → uji fungsi/akses → uji transaksi/saldo/file/laporan → UAT → persetujuan → produksi.
- **Keputusan/gagal:** temuan atau UAT belum diterima kembali ke perbaikan dan pengujian terkait.
- **Hasil:** hanya build yang lulus dan disetujui diluncurkan.
- **Jejak:** seluruh requirement; [TEST_PLAN.md](TEST_PLAN.md).

### FL-04 — Diagram Konteks Sistem
- **Aktor:** warga, petugas, bendahara, admin, superadmin.
- **Alur utama:** warga mengirim pendaftaran/pengajuan; petugas memasukkan hasil operasional; admin mengelola dan memutuskan; bendahara membayar; superadmin menjalankan seluruh alur admin sekaligus memelihara teknis.
- **Keputusan/gagal:** semua interaksi ditolak bila role, permission, atau scope record tidak sah.
- **Hasil:** pertukaran data terkontrol dan aktivitas penting diaudit.
- **Jejak:** USR-001–002; [PERMISSIONS.md](PERMISSIONS.md).

### FL-05 — Arsitektur Sistem Tingkat Tinggi
- **Aktor:** pengguna, pengelola teknis.
- **Alur utama:** perangkat → browser/PWA → Blade/Livewire/Alpine/Tailwind → Laravel/PHP → modul domain → MySQL 8.0.30/storage privat → Hostinger terkelola → backup terpisah.
- **Keputusan/gagal:** pekerjaan yang melampaui batas shared hosting tidak mengasumsikan worker permanen; gunakan proses sinkron/cron terbatas.
- **Hasil:** modular monolith dapat dioperasikan di Hostinger.
- **Jejak:** NFR-HOST-001, PWA-001; [ARCHITECTURE.md](ARCHITECTURE.md).

### FL-06 — Alur Hak Akses Pengguna
- **Aktor:** seluruh pengguna.
- **Alur utama:** buka aplikasi → login → validasi → identifikasi role/permission → dashboard sesuai izin → logout/sesi berakhir.
- **Keputusan/gagal:** kredensial tidak valid ditolak dan dicatat; pengguna kembali ke login tanpa sesi.
- **Hasil:** sesi dan fitur dibatasi permission serta record scope.
- **Jejak:** AUTH-002; USR-001; BR-AUTH-004.

### FL-07 — Sitemap Aplikasi
- **Aktor:** publik, warga, petugas, admin.
- **Alur utama:** beranda/login bercabang ke halaman umum, area warga, area petugas, atau back-office; tiap area menampilkan navigasi khusus.
- **Keputusan/gagal:** menu tidak berizin tidak tampil dan URL langsung tetap ditolak.
- **Hasil:** struktur navigasi ringkas tanpa kebocoran data.
- **Jejak:** USR-002, ANN-001, PUB-002; [DESIGN.md](DESIGN.md).

## 3. Operasional utama

### FL-08 — Registrasi dan Verifikasi Akun
- **Aktor:** warga, admin.
- **Alur utama:** buka [Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0](TERMS_AND_PRIVACY.md) yang tersedia publik → isi data → centang persetujuan afirmatif → validasi → simpan versi v1.0 dan waktu server → simpan `menunggu_verifikasi` → admin periksa domisili/duplikasi → setujui → aktifkan → notifikasi.
- **Keputusan/gagal:** persetujuan belum dicentang atau data invalid kembali diperbaiki; penerimaan tidak membuat verifikasi, aktivasi, autentikasi, atau login otomatis; keputusan tolak menyimpan alasan dan mengirim notifikasi tanpa aktivasi.
- **Hasil:** persetujuan admin mengaktifkan akun; penolakan menyimpan alasan, tidak mengaktifkan akun, dan menghasilkan pemberitahuan yang dapat dipahami.
- **Jejak:** AUTH-001, AUTH-003; BR-AUTH-001–003, BR-AUTH-006.

### FL-09 — Setoran Sampah Langsung
- **Aktor:** warga, petugas.
- **Alur utama:** identifikasi QR/nomor → konfirmasi warga → timbang beberapa jenis → ambil harga → hitung → tinjau → bukti/konfirmasi → transaksi+saldo masuk → bukti/notifikasi.
- **Keputusan/gagal:** data salah kembali ke timbang/input; commit gagal rollback seluruh efek.
- **Hasil:** satu transaksi final dan satu efek saldo masuk.
- **Jejak:** CST-001, DEP-001, PRC-002; BR-DEP-001–006.

### FL-10 — Pengajuan dan Penyelesaian Penjemputan
- **Aktor:** warga, admin/petugas, petugas lapangan.
- **Alur utama:** pengajuan+foto → validasi/kapasitas → pemeriksaan → terima/jadwalkan → menuju lokasi → timbang aktual → transaksi → saldo masuk → selesai.
- **Keputusan/gagal:** kapasitas penuh menawarkan tanggal lain; tidak layak ditolak beralasan dan berhenti.
- **Hasil:** penyelesaian hanya setelah transaksi final.
- **Jejak:** PUP-001, DEP-001; BR-PUP-001–010.

### FL-11 — Pencairan Saldo Tunai
- **Aktor:** warga, admin approver, bendahara/petugas payer.
- **Alur utama:** ajukan nominal → cek saldo/minimum → hold → approve → jadwal → verifikasi penerima → bayar+bukti → saldo keluar → selesai.
- **Keputusan/gagal:** saldo kurang berhenti tanpa hold; ditolak melepas hold dan berhenti; belum dibayar tidak membuat saldo keluar.
- **Hasil:** uang diserahkan satu kali dan ledger konsisten.
- **Jejak:** WDR-001, BAL-002; BR-WDR-001–009.

### FL-12 — Penukaran Saldo dengan Sembako
- **Aktor:** warga, admin approver, petugas handover.
- **Alur utama:** pilih paket → cek saldo/status → hold → cek ketersediaan manual → approve/siapkan → verifikasi/serahkan → bukti → saldo keluar.
- **Keputusan/gagal:** tidak tersedia/ditolak melepas hold dan berhenti tanpa penyerahan.
- **Hasil:** paket diserahkan satu kali tanpa stok terperinci.
- **Jejak:** GRC-001, BAL-002; BR-GRC-001–009.

### FL-13 — Koreksi Transaksi Final
- **Aktor:** admin berizin, warga sebagai pembaca.
- **Alur utama:** pilih transaksi → alasan+bukti → tampilkan nilai lama → masukkan nilai benar → hitung dampak → konfirmasi → catatan koreksi+mutasi penyesuaian+audit → tampilkan ke warga → notifikasi.
- **Keputusan/gagal:** batal konfirmasi berhenti tanpa koreksi/mutasi.
- **Hasil:** transaksi asli tetap ada dan dampak transparan.
- **Jejak:** DEP-002, BAL-002, AUD-001; BR-DEP-008–010.

### FL-14 — Perubahan Harga Sampah
- **Aktor:** admin.
- **Alur utama:** pilih jenis → masukkan harga/periode → validasi → tutup harga lama → aktifkan harga baru → simpan riwayat/audit → tampilkan.
- **Keputusan/gagal:** invalid/tumpang tindih kembali diperbaiki tanpa mengubah periode lama.
- **Hasil:** harga baru aktif dan transaksi lama tetap memakai snapshot.
- **Jejak:** PRC-001–002; BR-PRC-001–005.

### FL-15 — Alur Notifikasi dan Pengingat
- **Aktor:** sistem, warga, petugas, admin.
- **Alur utama:** event/jadwal → tentukan penerima/template → simpan notifikasi → bila diminta buka WhatsApp manual → tampilkan belum dibaca/pengingat → pengguna membaca.
- **Keputusan/gagal:** kanal tambahan tidak aktif langsung ke notifikasi aplikasi; gagal membuka WhatsApp tidak mengubah proses.
- **Hasil:** pemberitahuan tercatat tanpa klaim pesan otomatis.
- **Jejak:** NOT-001, WA-001; BR-NOT-*, BR-WA-*.

## 4. Saldo dan transaksi

### FL-16 — Alur Saldo Masuk
- **Aktor:** petugas, sistem, warga.
- **Alur utama:** data timbang → validasi → konfirmasi → transaksi final → mutasi masuk → saldo tersedia → bukti/notifikasi.
- **Keputusan/gagal:** belum dikonfirmasi tetap draf dan berhenti tanpa ledger.
- **Hasil:** satu saldo masuk per transaksi final.
- **Jejak:** DEP-001, BAL-001; BR-BAL-001–008.

### FL-17 — Alur Saldo Keluar
- **Aktor:** warga, admin, payer/handover.
- **Alur utama:** pengajuan disetujui dengan hold → manfaat diserahkan → bukti valid → mutasi keluar → tutup hold → selesai.
- **Keputusan/gagal:** belum diserahkan tetap tertahan dan tidak membuat saldo keluar.
- **Hasil:** pengurangan permanen hanya setelah manfaat diterima.
- **Jejak:** BAL-001, WDR-001, GRC-001.

### FL-18 — Alur Saldo Tertahan
- **Aktor:** warga, sistem.
- **Alur utama:** pengajuan → hitung saldo → buat hold → kurangi saldo tersedia → hasil proses → selesai menjadi keluar atau gagal melepas hold.
- **Keputusan/gagal:** saldo kurang berhenti tanpa hold; tolak/batal tidak membuat saldo keluar.
- **Hasil:** saldo tersedia selalu mencerminkan hold aktif.
- **Jejak:** BAL-002; BR-BAL-009–013.

### FL-19 — Siklus Saldo Warga
- **Aktor:** warga, petugas, admin, sistem.
- **Alur utama:** setoran sah → masuk → tersedia → pengajuan → tertahan → selesai menjadi keluar atau gagal dikembalikan → saldo tersedia terbaru.
- **Keputusan/gagal:** proses gagal mengembalikan hold, bukan meneruskan saldo keluar.
- **Hasil:** rumus saldo tersedia terjaga pada setiap siklus.
- **Jejak:** BAL-001–002; BR-BAL-002.

### FL-20 — Pencegahan Transaksi Ganda
- **Aktor:** petugas, sistem.
- **Alur utama:** konfirmasi → baca idempotency key → bila baru mulai transaction+lock → simpan atomik → commit → sukses.
- **Keputusan/gagal:** key lama mengembalikan hasil lama; commit gagal rollback dan berhenti.
- **Hasil:** retry/klik ganda tidak menggandakan mutasi.
- **Jejak:** DEP-003; BR-DEP-011–014.

### FL-21 — Pembatalan dan Pengembalian Saldo
- **Aktor:** warga, admin, sistem.
- **Alur utama:** pengajuan aktif → permintaan batal/keputusan tolak → validasi status → simpan alasan/status → bila ada hold lepaskan → audit/notifikasi.
- **Keputusan/gagal:** status tidak membolehkan batal berhenti; tanpa hold tidak membuat mutasi palsu.
- **Hasil:** hold sah dilepas tepat satu kali.
- **Jejak:** BAL-002, WDR-001, GRC-001; BR-CAN-*.

## 5. State machine

### FL-22 — Status Penjemputan
- **Aktor:** warga, admin, petugas.
- **Alur utama:** menunggu pemeriksaan → diterima → dijadwalkan → menuju lokasi → dijemput → selesai.
- **Keputusan/gagal:** pemeriksaan dapat berakhir ditolak; sebelum berangkat dapat berakhir dibatalkan.
- **Hasil:** selesai hanya setelah transaksi berhasil.
- **Jejak:** PUP-001; BR-PUP-009–010.

### FL-23 — Status Pencairan Tunai
- **Aktor:** warga, admin, bendahara.
- **Alur utama:** menunggu verifikasi → disetujui → siap diambil → sudah dibayar.
- **Keputusan/gagal:** keputusan tolak berakhir ditolak; melewati batas sebelum dibayar berakhir kedaluwarsa dan hold dilepas.
- **Hasil:** status pembayaran tidak dapat dicapai dari jalur gagal.
- **Jejak:** WDR-001; BR-WDR-009, BR-EXP-*.

### FL-24 — Status Penukaran Sembako
- **Aktor:** warga, admin, petugas.
- **Alur utama:** menunggu verifikasi → disetujui → sedang disiapkan → siap diambil → selesai.
- **Keputusan/gagal:** tolak atau kedaluwarsa menjadi endpoint dan melepas hold.
- **Hasil:** selesai hanya setelah bukti penyerahan.
- **Jejak:** GRC-001; BR-GRC-009, BR-EXP-*.

### FL-25 — Status Transaksi Setoran
- **Aktor:** petugas, admin.
- **Alur utama:** draf → validasi → final → bila salah koreksi/reversal → dikoreksi/dibalik.
- **Keputusan/gagal:** draf invalid kembali diperbaiki; final tanpa kesalahan tetap final.
- **Hasil:** final tidak dihapus atau diedit langsung.
- **Jejak:** DEP-001–002; BR-DEP-007–010.

## 6. Administrasi dan pemeliharaan

### FL-26 — Pembuatan Laporan dan Statistik
- **Aktor:** admin, superadmin, bendahara/petugas berizin, sistem.
- **Alur utama:** pilih laporan dan filter → otorisasi sesuai scope → tampilkan ringkasan/detail web → buat Excel privat → audit → unduh.
- **Keputusan/gagal:** data besar masuk antrean terbatas; proses gagal tidak menerbitkan file parsial.
- **Hasil:** laporan konsisten dan statistik publik teragregasi.
- **Jejak:** RPT-001, PUB-001–002; BR-RPT-*, BR-PUB-*.

### FL-27 — Audit Log
- **Aktor:** sistem, admin, pengelola teknis.
- **Alur utama:** aktivitas penting → identifikasi pelaku/waktu → catat aksi/objek/perubahan → admin menelusuri → retensi teknis bila sah.
- **Keputusan/gagal:** permintaan perubahan/penghapusan operasional ditolak.
- **Hasil:** jejak append-oriented tersedia sesuai retensi.
- **Jejak:** AUD-001; BR-AUD-001–006.

### FL-28 — Backup dan Pemulihan Data
- **Aktor:** superadmin.
- **Alur utama:** jadwal → backup database/media → enkripsi+simpan terpisah → verifikasi integritas → retensi → uji restore berkala.
- **Keputusan/gagal:** backup invalid dicatat dan diulang; restore gagal memperbaiki prosedur lalu mengulang dari backup.
- **Hasil:** sasaran RPO/RTO dapat dibuktikan.
- **Jejak:** NFR-OPS-001; [OPERATIONS.md](OPERATIONS.md).

### FL-29 — Penanganan Kesalahan Transaksi
- **Aktor:** petugas, admin, sistem.
- **Alur utama:** deteksi → hentikan pengulangan manual → periksa transaksi/mutasi → gunakan hasil lengkap atau rollback parsial → bila saldo terlanjur berubah buat koreksi → insiden/audit → verifikasi.
- **Keputusan/gagal:** transaksi lengkap tidak dibuat ulang; koreksi hanya jika saldo benar-benar terdampak.
- **Hasil:** sistem pulih tanpa efek ganda.
- **Jejak:** DEP-003, BAL-002.

### FL-30 — Laporan dan Ekspor Excel
- **Aktor:** admin, bendahara, petugas.
- **Alur utama:** pilih jenis laporan → pilih hari ini, minggu ini, bulan, atau tanggal custom → terapkan filter → lihat ringkasan → unduh Excel.
- **Keputusan/gagal:** periode atau filter tidak valid ditolak; akses dan scope record tetap diperiksa.
- **Hasil:** laporan operasional dapat dibaca di web dan disimpan sebagai file Excel privat.
- **Jejak:** RPT-001; BR-RPT-001–006.

## 7. Program, transparansi, dan inklusi

### FL-31 — Target Pengumpulan Sampah Desa
- **Aktor:** admin, sistem, warga/publik.
- **Alur utama:** buat target → validasi → aktifkan → akumulasi transaksi sah → hitung progres → publikasi → tutup pada akhir periode → ringkasan.
- **Keputusan/gagal:** target invalid kembali diperbaiki; transaksi draf/dibalik tidak dihitung.
- **Hasil:** progres agregat bersih dan ringkasan akhir.
- **Jejak:** TGT-001; BR-TGT-001–006.

### FL-32 — Bank Sampah Keliling per RT/RW
- **Aktor:** admin, petugas, warga.
- **Alur utama:** buat jadwal → validasi benturan → publikasi/pengingat → buka titik → identifikasi warga → setoran langsung → saldo/bukti → tutup/rekap.
- **Keputusan/gagal:** benturan kembali memilih waktu/petugas; jadwal belum dibuka tidak menerima transaksi keliling.
- **Hasil:** titik layanan terjadwal, bukan penjemputan rumah.
- **Jejak:** MOB-001, DEP-001; BR-MOB-001–006.

### FL-33 — Estimasi Nilai Sebelum Setor
- **Aktor:** warga, publik.
- **Alur utama:** pilih jenis → masukkan perkiraan berat → validasi harga → hitung → tampilkan estimasi, edukasi, penafian → selesai tanpa transaksi.
- **Keputusan/gagal:** input/harga invalid kembali diperbaiki tanpa menampilkan angka menyesatkan.
- **Hasil:** informasi saja, tanpa ledger atau hold.
- **Jejak:** EST-001; BR-EST-001–004.

### FL-34 — Pelayanan Warga Tanpa Smartphone
- **Aktor:** warga, petugas, admin.
- **Alur utama:** minta bantuan → penjelasan/persetujuan → cari/buat akun → verifikasi → nomor/kartu QR → layanan atas nama warga → bukti cetak/saldo.
- **Keputusan/gagal:** tidak setuju berhenti; identitas invalid dikembalikan ke verifikasi, bukan transaksi.
- **Hasil:** layanan inklusif dengan pelaksana tercatat.
- **Jejak:** CST-002, AUTH-001; BR-CST-004–006.

### FL-35 — Verifikasi Bukti Transaksi dengan QR
- **Aktor:** warga, pemeriksa, sistem.
- **Alur utama:** scan → validasi token → ambil data terbatas → periksa final → tampilkan nomor/tanggal/berat/nilai/status.
- **Keputusan/gagal:** token invalid menampilkan tidak sah; transaksi nonfinal tidak dinyatakan sah.
- **Hasil:** keaslian diverifikasi tanpa data pribadi.
- **Jejak:** QRV-001; BR-QRV-001–005.

### FL-36 — Pengaturan Kapasitas Penjemputan Harian
- **Aktor:** admin, warga, sistem.
- **Alur utama:** admin tetapkan batas → warga pilih tanggal → hitung pemakaian → bila tersedia reservasi/pengajuan → admin jadwalkan → bila berubah kirim pengingat.
- **Keputusan/gagal:** penuh menawarkan tanggal lain; perubahan jadwal tidak mengubah estimasi menjadi nilai saldo.
- **Hasil:** kapasitas tidak terlampaui dan alternatif transparan.
- **Jejak:** PUP-001, REG-001; BR-PUP-004–007.

## 8. Matriks kelengkapan

| Kategori | Flow | Jumlah |
|---|---|---:|
| Perencanaan dan pengembangan | FL-01–FL-03 | 3 |
| Sistem dan pengguna | FL-04–FL-07 | 4 |
| Operasional utama | FL-08–FL-15 | 8 |
| Saldo dan transaksi | FL-16–FL-21 | 6 |
| Status | FL-22–FL-25 | 4 |
| Administrasi dan pemeliharaan | FL-26–FL-30 | 5 |
| Program, transparansi, inklusi | FL-31–FL-36 | 6 |
| **Total** | **FL-01–FL-36** | **36** |

Semua perubahan alur harus memperbarui requirement, aturan bisnis, permission, validasi, test case, dan SOP terkait. Urutan flow tidak membentuk pembagian ruang lingkup fitur.
