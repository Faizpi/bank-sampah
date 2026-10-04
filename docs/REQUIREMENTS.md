# Kebutuhan Sistem

## 1. Tujuan dan konvensi

Dokumen ini menetapkan perilaku wajib untuk satu baseline pengembangan Sistem Informasi Bank Sampah Digital Desa Sindangheula. Kode kebutuhan bersifat stabil dan menjadi referensi bagi [aturan bisnis](BUSINESS_RULES.md), [alur pengguna](USER_FLOWS.md), [model data](DATA_MODEL.md), [hak akses](PERMISSIONS.md), dan [rencana pengujian](TEST_PLAN.md).

Setiap kebutuhan memakai format `KELOMPOK-NNN`. Kata **harus** berarti wajib. Semua validasi dilakukan di server. Jalur gagal berhenti tanpa menjalankan efek jalur sukses.

## 2. Kebutuhan fungsional

### AUTH — autentikasi dan akun

#### AUTH-001 — Registrasi dan verifikasi akun warga
- **Aktor:** warga, admin.
- **Prasyarat:** nomor telepon belum terikat pada akun aktif; wilayah tersedia; warga menerima secara afirmatif [Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0](TERMS_AND_PRIVACY.md).
- **Alur:** warga mengisi data dan mencentang kontrol persetujuan yang kosong secara awal; sistem memvalidasi; versi v1.0 serta waktu persetujuan yang dicatat server disimpan; akun disimpan `menunggu_verifikasi`; admin memeriksa domisili dan duplikasi; admin menyetujui atau menolak dengan alasan; sistem memberi notifikasi.
- **Hasil:** akun yang disetujui aktif dan dapat login; akun ditolak menyimpan alasan.
- **Kegagalan:** data tidak valid atau persetujuan tidak diberikan dikembalikan ke formulir; duplikasi atau domisili tidak sah tidak mengaktifkan akun.
- **Kriteria penerimaan:** dokumen tersedia publik; status awal benar; keputusan tercatat; penerimaan ketentuan bukan verifikasi, aktivasi, autentikasi, atau login otomatis; hanya akun aktif dapat login; penolakan tidak menjalankan aktivasi.
- **Jejak:** FL-08; BR-AUTH-001–003, BR-AUTH-006; TC-AUTH-001.

#### AUTH-002 — Login, logout, sesi, dan pembatasan percobaan
- **Aktor:** seluruh pengguna terdaftar.
- **Prasyarat:** akun aktif dan kredensial tersedia.
- **Alur:** pengguna memasukkan nomor telepon dan kata sandi; sistem memverifikasi kredensial, status, role, dan permission; sesi diregenerasi; admission ke panel teknis back-office juga memerlukan `backoffice.access`, yang baseline-nya diberikan kepada `admin` dan `superadmin`; permission ini tidak menggantikan authorization domain, action, atau record scope. Setelah autentikasi berhasil, sistem mengarahkan pengguna ke URL privat yang sebelumnya diminta hanya jika URL tersebut ada dan pengguna berwenang, selain itu keberhasilan autentikasi tetap sah tanpa membuat redirect dashboard, shell, atau role hingga IMP-019 dan IMP-029 tersedia; logout mengakhiri sesi.
- **Hasil:** sesi terautentikasi terbatas pada izin pengguna atau sesi dihentikan.
- **Kegagalan:** kredensial salah, akun nonaktif, atau rate limit menghasilkan pesan generik dan tidak membuat sesi.
- **Kriteria penerimaan:** session fixation dicegah; menu dan record tetap diotorisasi; logout menginvalidasi sesi; percobaan login dibatasi.
- **Jejak:** FL-06; BR-AUTH-004; TC-AUTH-002.

#### AUTH-003 — Perubahan kata sandi
- **Aktor:** pengguna terautentikasi untuk perubahan mandiri, atau admin berizin, sistem, dan pengguna target untuk perubahan berbantuan langsung.
- **Jalur A, perubahan berbantuan langsung oleh admin:** hanya untuk pengguna yang benar-benar lupa kata sandi dan tidak dapat login. Admin memiliki `user.reset-password` dan `session.revoke`; target adalah pengguna lain dalam scope yang diizinkan; admin memverifikasi target secara `tatap_muka` atau `callback_nomor_terdaftar`, serta mengisi alasan 10–1000 karakter. Admin memilih target yang bukan dirinya sendiri, memasukkan kata sandi baru dan konfirmasi; server memvalidasi konfirmasi, panjang minimum, dan kebijakan password umum. Dalam satu transaksi atomik, sistem menyimpan hash kata sandi, mencabut seluruh sesi aktif target, dan mencatat aktor, metode verifikasi, alasan, serta hasil tanpa mencatat kata sandi.
- **Jalur B, perubahan mandiri dari profil:** hanya pengguna yang telah terautentikasi pada profilnya sendiri. Pengguna memasukkan kata sandi saat ini, kata sandi baru, dan konfirmasi; server memverifikasi kata sandi saat ini serta memvalidasi konfirmasi, panjang minimum, dan kebijakan password umum. Setelah sukses, sistem menyimpan hash kata sandi dan mencabut sesi aktif lain milik pengguna sambil mempertahankan sesi saat ini bila secara teknis memungkinkan; bila tidak memungkinkan, sistem mencabut seluruh sesi dan meminta autentikasi ulang. Audit mencatat aktor, metode `mandiri_profil`, dan hasil tanpa kata sandi atau secret.
- **Hasil:** kata sandi baru aktif; Jalur A mencabut seluruh sesi target, sedangkan Jalur B mencabut sesi lain atau meminta autentikasi ulang bila sesi saat ini tidak dapat dipertahankan.
- **Kegagalan:** self-target, kondisi lupa/tidak dapat login, permission/scope, metode verifikasi, alasan, kata sandi saat ini, konfirmasi, atau kebijakan kata sandi yang tidak valid berhenti tanpa perubahan kata sandi, sesi, atau audit sukses.
- **Kriteria penerimaan:** tidak ada reset publik, token, masa berlaku token, penyimpanan atau pengiriman token, email, SMS, WhatsApp, rate limit reset, atau alur permintaan generik; kata sandi tidak disimpan, diaudit, atau dicatat pada log.
- **Jejak:** FL-06, FL-08; BR-AUTH-005; TC-AUTH-003.

### USR — pengguna dan profil

#### USR-001 — Kelola warga, petugas, bendahara, admin, dan superadmin
- **Aktor:** admin, superadmin sesuai permission; warga untuk profil sendiri.
- **Prasyarat:** aktor terautentikasi dan berwenang pada record.
- **Alur:** aktor melihat, membuat, memperbarui, mengaktifkan, atau menonaktifkan data sesuai izin; perubahan sensitif diaudit.
- **Hasil:** data pengguna konsisten dengan role dan wilayahnya.
- **Kegagalan:** akses di luar scope ditolak; pengguna bereferensi transaksi tidak dihapus fisik.
- **Kriteria penerimaan:** warga hanya melihat profil sendiri; `user.view` tanpa scope eksplisit fail-closed ke diri sendiri; petugas/bendahara dengan `user.view` + `user.view.area` hanya melihat diri sendiri dan nasabah aktif pada RT aktif di area pelayanan aktif yang efektif hari ini; admin/superadmin dengan `user.view` + `user.view.all` melihat seluruh pengguna aktif; petugas tidak dapat menaikkan role; nonaktif mencegah login tanpa menghilangkan riwayat.
- **Jejak:** FL-04, FL-06; BR-USR-001–003; TC-USR-001.

#### USR-002 — Dashboard sesuai peran
- **Aktor:** warga, petugas, bendahara, admin, superadmin.
- **Prasyarat:** login berhasil.
- **Alur:** sistem memuat ringkasan sesuai izin: saldo-first bagi warga, tugas hari ini bagi petugas/bendahara, dan tindakan serta data operasional bagi admin.
- **Hasil:** pengguna melihat informasi dan tindakan relevan tanpa data terlarang.
- **Kegagalan:** kegagalan widget tidak membuka data atau merusak shell; tampilkan state error yang dapat dipulihkan.
- **Kriteria penerimaan:** agregat sesuai record scope; tautan tindakan tetap diperiksa permission; shell mengikuti [DESIGN.md](DESIGN.md).
- **Jejak:** FL-04, FL-07; BR-USR-004; TC-USR-002.

### CST — identitas nasabah

#### CST-001 — Nomor, kartu, dan QR nasabah
- **Aktor:** admin, petugas, warga.
- **Prasyarat:** profil nasabah terverifikasi.
- **Alur:** sistem menerbitkan nomor unik dan token QR acak; kartu dapat ditampilkan atau dicetak; petugas memindai atau mencari nomor lalu mengonfirmasi nama.
- **Hasil:** nasabah dapat ditemukan tanpa menaruh data pribadi di QR.
- **Kegagalan:** token tidak valid atau kartu nonaktif tidak memilih nasabah.
- **Kriteria penerimaan:** nomor dan token unik; token dapat dirotasi; QR tidak memuat identitas; konfirmasi petugas wajib sebelum transaksi.
- **Jejak:** FL-09, FL-32, FL-33; BR-CST-001–003; TC-CST-001.

#### CST-002 — Pelayanan warga tanpa smartphone
- **Aktor:** warga, petugas, admin.
- **Prasyarat:** persetujuan warga dan identitas dapat diverifikasi.
- **Alur:** petugas mencari atau membantu pendaftaran; admin memverifikasi bila perlu; layanan memakai nomor/kartu QR; transaksi dicatat atas nama warga dan pelaksana; warga menerima bukti cetak dan informasi saldo.
- **Hasil:** warga memperoleh layanan yang sama tanpa penyerahan kata sandi.
- **Kegagalan:** tanpa persetujuan atau identitas valid proses dihentikan.
- **Kriteria penerimaan:** pelaksana tercatat; petugas tidak mengambil alih kata sandi; bukti dapat dicetak; hak transparansi tetap tersedia.
- **Jejak:** FL-34; BR-CST-004–006; TC-CST-002.

### REG — wilayah dan area pelayanan

#### REG-001 — Kelola dusun, RW, RT, dan area pelayanan
- **Aktor:** admin.
- **Prasyarat:** memiliki permission master wilayah.
- **Alur:** admin mengelola hierarki, status, area pelayanan, penugasan, dan keterkaitan jadwal; sistem memvalidasi parent dan kode unik.
- **Hasil:** pengguna, penjemputan, layanan keliling, target, dan laporan memakai referensi wilayah konsisten.
- **Kegagalan:** parent tidak aktif, duplikasi, atau wilayah bereferensi tidak dapat dihapus fisik.
- **Kriteria penerimaan:** hierarki valid; kode unik pada scope; deaktivasi mempertahankan riwayat; filter wilayah konsisten.
- **Jejak:** FL-04, FL-07, FL-34, FL-36; BR-REG-001–003; TC-REG-001.

### WST — master sampah dan edukasi

#### WST-001 — Kelola kategori, jenis, satuan, kondisi, foto, dan edukasi
- **Aktor:** admin; warga/petugas sebagai pembaca.
- **Prasyarat:** admin berwenang; kategori dan satuan tersedia.
- **Alur:** admin menyimpan kode, nama, kategori, satuan, satu atau lebih kondisi diterima, foto privat/terkontrol, edukasi kontekstual, urutan, dan status. Kondisi adalah master yang dipasangkan banyak-ke-banyak ke jenis sampah.
- **Hasil:** katalog aktif tersedia pada harga, estimasi, dan transaksi.
- **Kegagalan:** data invalid atau bereferensi ditolak; item nonaktif tidak dapat dipakai untuk transaksi baru.
- **Kriteria penerimaan:** satuan dan kondisi eksplisit; edukasi muncul sesuai konteks; riwayat transaksi lama tetap terbaca.
- **Jejak:** FL-07, FL-31; BR-WST-001–003; TC-WST-001.

### PRC — harga sampah

#### PRC-001 — Harga berlaku dan riwayat harga
- **Aktor:** admin; warga/petugas sebagai pembaca.
- **Prasyarat:** jenis sampah aktif dan permission harga tersedia.
- **Alur:** admin memasukkan harga rupiah untuk pasangan jenis sampah dan kondisi serta waktu berlaku; sistem menolak periode tumpang tindih, menutup periode lama, mengaktifkan harga baru, dan mencatat audit.
- **Hasil:** tepat satu harga berlaku pada waktu transaksi untuk kombinasi yang ditetapkan.
- **Kegagalan:** harga negatif, rentang tidak valid, atau tumpang tindih tidak mengubah harga lama.
- **Kriteria penerimaan:** riwayat immutable secara operasional; harga lama pada transaksi tidak berubah; publik melihat harga aktif dan tanggal berlaku.
- **Jejak:** FL-14; BR-PRC-001–004; TC-PRC-001.

#### PRC-002 — Snapshot harga transaksi
- **Aktor:** sistem, petugas.
- **Prasyarat:** item transaksi valid dan harga aktif ditemukan.
- **Alur:** pada konfirmasi, sistem menyalin harga per satuan, satuan, nama jenis, dan aturan pembulatan ke detail transaksi.
- **Hasil:** subtotal dan bukti bersumber dari snapshot.
- **Kegagalan:** harga aktif tidak ditemukan menghentikan finalisasi tanpa ledger.
- **Kriteria penerimaan:** perubahan master harga tidak mengubah transaksi final; snapshot tidak boleh kosong.
- **Jejak:** FL-09, FL-14, FL-16; BR-PRC-005; TC-PRC-002.

### DEP — setoran dan transaksi

#### DEP-001 — Setoran langsung multi-item
- **Aktor:** petugas, warga.
- **Prasyarat:** nasabah aktif; petugas berwenang; master dan harga aktif tersedia.
- **Alur:** identitas dikonfirmasi; petugas menambah satu atau lebih jenis, berat aktual maksimal tiga desimal, kondisi, dan bukti; sistem menghitung subtotal/total; petugas meninjau lalu mengonfirmasi.
- **Hasil:** transaksi final, detail snapshot, bukti, satu mutasi saldo masuk, QR verifikasi, notifikasi, dan audit dibuat atomik.
- **Kegagalan:** input salah kembali ke penimbangan; kegagalan commit melakukan rollback seluruh efek.
- **Kriteria penerimaan:** multi-item dihitung benar; berat positif; tidak ada mutasi pada draf; transaksi tidak dapat difinalkan dua kali.
- **Jejak:** FL-09, FL-16, FL-19, FL-20, FL-25; BR-DEP-001–006; TC-DEP-001.

#### DEP-002 — Bukti, draf, koreksi, dan reversal transaksi
- **Aktor:** petugas pembuat, admin dengan izin koreksi, warga sebagai pembaca riwayatnya.
- **Prasyarat:** transaksi ada dan akses record sah.
- **Alur:** draf dapat diperbaiki pembuat; final tidak diedit atau dihapus; kesalahan final diproses sebagai koreksi/reversal beralasan dan berbukti.
- **Hasil:** nilai lama/baru, dampak saldo, pelaksana, waktu, bukti, dan audit dapat ditelusuri.
- **Kegagalan:** pembatalan konfirmasi berhenti tanpa perubahan; izin atau bukti tidak cukup ditolak.
- **Kriteria penerimaan:** warga melihat riwayat koreksi terbatas; ledger penyesuaian seimbang; transaksi asli tetap ada.
- **Jejak:** FL-13, FL-20, FL-25, FL-28; BR-DEP-007–010; TC-DEP-002.

#### DEP-003 — Idempotensi dan penanganan kegagalan
- **Aktor:** petugas, sistem, admin.
- **Prasyarat:** permintaan finalisasi memiliki idempotency key.
- **Alur:** sistem mencari hasil lama; permintaan baru diproses dalam database transaction dengan lock dan constraint; hasil lama dikembalikan untuk pengulangan; kegagalan parsial di-rollback dan direkonsiliasi.
- **Hasil:** satu kejadian bisnis menghasilkan paling banyak satu transaksi final dan mutasi sumber.
- **Kegagalan:** commit gagal menghasilkan status gagal tanpa saldo berubah.
- **Kriteria penerimaan:** klik ganda tidak menggandakan saldo; retry aman; insiden dapat dilacak.
- **Jejak:** FL-20, FL-29; BR-DEP-011–014; TC-DEP-003.

### BAL — saldo dan ledger

#### BAL-001 — Ledger saldo rupiah dan saldo tersedia
- **Aktor:** sistem; warga/admin sebagai pembaca sesuai izin.
- **Prasyarat:** rekening ledger nasabah tersedia.
- **Alur:** setiap sumber sah menulis mutasi immutable masuk/keluar/penyesuaian; sistem menghitung `saldo tersedia = total saldo masuk − total saldo keluar − saldo tertahan`.
- **Hasil:** saldo dapat ditelusuri ke sumber dan tidak negatif.
- **Kegagalan:** referensi ganda, nilai nol/negatif yang tidak sah, atau hasil negatif ditolak dan di-rollback.
- **Kriteria penerimaan:** tidak ada edit saldo langsung; rupiah integer/bigint; mutasi sumber unik; rekalkulasi menghasilkan nilai yang sama.
- **Jejak:** FL-16–FL-19; BR-BAL-001–008; TC-BAL-001.

#### BAL-002 — Penahanan, pelepasan, koreksi, dan pembalikan saldo
- **Aktor:** sistem, admin berizin.
- **Prasyarat:** pengajuan atau transaksi sumber valid.
- **Alur:** pengajuan sah membuat hold; selesai mengubah kewajiban menjadi saldo keluar dan menutup hold; ditolak/dibatalkan/kedaluwarsa melepas hold; koreksi/reversal membuat mutasi lawan atau penyesuaian, bukan mengubah catatan lama.
- **Hasil:** saldo tersedia selalu mencerminkan status sumber.
- **Kegagalan:** sumber yang sudah selesai tidak dapat diproses ulang; jalur gagal tidak membuat saldo keluar.
- **Kriteria penerimaan:** satu hold aktif per sumber; hold tidak terhitung ganda; seluruh perubahan diaudit dan koreksi terlihat warga.
- **Jejak:** FL-13, FL-17–FL-21, FL-28; BR-BAL-009–016; TC-BAL-002.

### PUP — penjemputan

#### PUP-001 — Pengajuan, kapasitas, penjadwalan, dan penyelesaian penjemputan
- **Aktor:** warga, admin/petugas, petugas lapangan.
- **Prasyarat:** nasabah aktif; alamat dalam area pelayanan; tanggal layanan tersedia.
- **Alur:** warga mengisi foto wajib, jenis, perkiraan jumlah/berat, alamat, tanggal, dan catatan; sistem memeriksa kapasitas; jika penuh menawarkan tanggal lain; petugas menilai, menerima/menolak beralasan, menjadwalkan, menjemput, menimbang aktual, dan membuat transaksi.
- **Hasil:** pengajuan selesai hanya setelah transaksi setoran berhasil; saldo memakai berat aktual.
- **Kegagalan:** foto/area invalid ditolak; kapasitas penuh tidak menyimpan pada tanggal tersebut; penolakan berhenti tanpa jadwal atau transaksi.
- **Kriteria penerimaan:** alternatif tanggal tersedia; status mengikuti state machine; perkiraan tidak menjadi saldo; petugas berangkat mencegah pembatalan warga.
- **Jejak:** FL-10, FL-22, FL-36; BR-PUP-001–010; TC-PUP-001.

### WDR — pencairan tunai

#### WDR-001 — Pengajuan, persetujuan, pembayaran, dan kedaluwarsa pencairan
- **Aktor:** warga, admin approver, bendahara/petugas payer.
- **Prasyarat:** saldo tersedia memenuhi minimum; aktor berbeda memiliki permission terkait.
- **Alur:** warga mengajukan nominal; sistem membuat hold; admin menyetujui/menolak; pengajuan disetujui dijadwalkan; payer memverifikasi penerima, membayar, mengunggah bukti, dan menyelesaikan; sistem membuat saldo keluar serta menutup hold.
- **Hasil:** pembayaran selesai satu kali atau hold dilepas pada tolak, batal, dan kedaluwarsa.
- **Kegagalan:** saldo tidak cukup berhenti tanpa hold; penolakan tidak menuju pembayaran; pembayaran tanpa bukti/identitas ditolak.
- **Kriteria penerimaan:** `approve` terpisah dari `pay`; status valid; idempotent; tidak ada saldo keluar sebelum uang diserahkan.
- **Jejak:** FL-11, FL-17, FL-18, FL-21, FL-23; BR-WDR-001–009; TC-WDR-001.

### GRC — penukaran sembako

#### GRC-001 — Paket dan penukaran sembako tanpa stok terperinci
- **Aktor:** warga, admin approver, petugas handover.
- **Prasyarat:** paket aktif; saldo cukup; ketersediaan dikonfirmasi manual.
- **Alur:** warga memilih paket; sistem menahan saldo; admin memeriksa ketersediaan manual lalu menyetujui/menolak; petugas menyiapkan, memverifikasi warga, menyerahkan, dan mengunggah bukti; sistem membuat saldo keluar.
- **Hasil:** penukaran selesai satu kali atau saldo dilepas pada tolak, batal, dan kedaluwarsa.
- **Kegagalan:** paket tidak aktif/tidak tersedia berhenti tanpa penyerahan; jalur penolakan tidak menuju sukses.
- **Kriteria penerimaan:** `approve` terpisah dari `handover`; tidak ada stok rinci; bantuan gratis tidak memotong saldo; bukti wajib.
- **Jejak:** FL-12, FL-17, FL-18, FL-21, FL-24; BR-GRC-001–009; TC-GRC-001.

### NOT — notifikasi dan pengingat

#### NOT-001 — Notifikasi dalam aplikasi
- **Aktor:** sistem, seluruh pengguna sesuai kejadian.
- **Prasyarat:** kejadian domain atau jadwal pengingat valid.
- **Alur:** sistem menentukan penerima, template, waktu, dan referensi; menyimpan notifikasi; menampilkan status belum dibaca; pengguna membuka atau menandai dibaca.
- **Hasil:** akun, transaksi, koreksi, saldo, status, harga, jadwal, perubahan, dan kedaluwarsa dapat diberitahukan.
- **Kegagalan:** kegagalan notifikasi tidak membatalkan transaksi yang sudah commit dan dicatat untuk retry terbatas.
- **Kriteria penerimaan:** tidak memuat secret; tautan tetap diotorisasi; duplikasi dicegah; preferensi yang berlaku dihormati.
- **Jejak:** FL-15; BR-NOT-001–004; TC-NOT-001.

### WA — WhatsApp manual

#### WA-001 — Tautan `wa.me` dan template pesan
- **Aktor:** warga, petugas, admin.
- **Prasyarat:** nomor tujuan dan template yang diizinkan tersedia.
- **Alur:** aplikasi membentuk tautan `wa.me` dengan pesan terenkode; pengguna menekan tombol; aplikasi membuka WhatsApp; pengguna meninjau dan mengirim sendiri.
- **Hasil:** komunikasi manual dimulai tanpa klaim pengiriman otomatis.
- **Kegagalan:** nomor/template invalid tidak membuat tautan; WhatsApp tidak tersedia menampilkan instruksi alternatif.
- **Kriteria penerimaan:** UI menggunakan frasa “Buka WhatsApp”, bukan “Pesan terkirim”; tidak ada gateway; data sensitif tidak dimasukkan ke template.
- **Jejak:** FL-15; BR-WA-001–005; TC-WA-001.

### ANN — pengumuman

#### ANN-001 — Kelola dan tampilkan pengumuman
- **Aktor:** admin; warga/petugas/publik sebagai pembaca sesuai audiens.
- **Prasyarat:** admin memiliki permission publikasi.
- **Alur:** admin membuat isi, audiens, periode, status, dan prioritas; sistem memvalidasi serta menerbitkan; pembaca melihat pengumuman aktif.
- **Hasil:** informasi harga, layanan, libur, edukasi, dan kegiatan tersedia pada kanal yang tepat.
- **Kegagalan:** konten invalid atau periode berakhir tidak dipublikasikan.
- **Kriteria penerimaan:** publikasi diaudit; konten aman dari XSS; audiens dan periode dipatuhi.
- **Jejak:** FL-07, FL-15; BR-ANN-001–003; TC-ANN-001.

### TGT — target desa

#### TGT-001 — Target pengumpulan dan progres
- **Aktor:** admin, sistem, warga/publik.
- **Prasyarat:** jenis/periode valid dan admin berwenang.
- **Alur:** admin menetapkan target berat, jenis, periode, tujuan, dan visibilitas; sistem mengakumulasi transaksi final yang sah; progres diperbarui; akhir periode menutup target dan menerbitkan ringkasan.
- **Hasil:** progres agregat dan sisa target dapat dilihat sesuai visibilitas.
- **Kegagalan:** target invalid tidak aktif; transaksi draf/dibal­ikkan tidak menambah progres bersih.
- **Kriteria penerimaan:** unit konsisten; periode tidak ambigu; koreksi/reversal tercermin; publik tanpa data pribadi.
- **Jejak:** FL-31; BR-TGT-001–006; TC-TGT-001.

### MOB — Bank Sampah Keliling

#### MOB-001 — Jadwal dan operasi layanan keliling per RT/RW
- **Aktor:** admin, petugas, warga.
- **Prasyarat:** wilayah, titik, waktu, petugas, dan kapasitas tersedia.
- **Alur:** admin membuat jadwal; sistem mendeteksi benturan; jadwal dipublikasikan; petugas membuka titik; warga datang dengan QR/nomor; setoran mengikuti alur langsung; petugas menutup dan merekap layanan.
- **Hasil:** layanan terjadwal tercatat per wilayah tanpa diperlakukan sebagai penjemputan rumah.
- **Kegagalan:** benturan mengharuskan waktu/petugas lain; layanan belum dibuka tidak menerima transaksi bertipe keliling.
- **Kriteria penerimaan:** RT/RW, titik, waktu, petugas, jenis diterima, kapasitas, dan status jelas; perubahan menghasilkan pengingat.
- **Jejak:** FL-32; BR-MOB-001–006; TC-MOB-001.

### EST — estimasi nilai

#### EST-001 — Kalkulator estimasi sebelum setor
- **Aktor:** warga/publik.
- **Prasyarat:** jenis dan harga aktif tersedia.
- **Alur:** pengguna memilih jenis dan memasukkan perkiraan berat; sistem menghitung berat dikali harga aktif dan menampilkan edukasi serta penafian.
- **Hasil:** estimasi informatif tanpa record transaksi, mutasi, atau hold.
- **Kegagalan:** input/harga invalid menampilkan panduan perbaikan tanpa hasil menyesatkan.
- **Kriteria penerimaan:** penafian selalu terlihat; nilai akhir dinyatakan mengikuti penimbangan aktual; kalkulator tidak mengubah data keuangan.
- **Jejak:** FL-33; BR-EST-001–004; TC-EST-001.

### QRV — verifikasi bukti

#### QRV-001 — Verifikasi publik bukti transaksi dengan QR
- **Aktor:** warga, pemeriksa, sistem.
- **Prasyarat:** bukti memiliki token acak.
- **Alur:** pemeriksa membuka token; sistem memvalidasi token dan status; halaman menampilkan nomor bukti, tanggal, berat, nilai, dan status sah secara terbatas.
- **Hasil:** keaslian bukti dapat diperiksa tanpa login dan tanpa data pribadi.
- **Kegagalan:** token invalid/nonaktif menampilkan “tidak ditemukan/tidak sah” tanpa petunjuk enumerasi.
- **Kriteria penerimaan:** token berentropi tinggi; rate limit; transaksi nonfinal tidak dinyatakan sah; saldo, alamat, identitas, dan telepon lengkap tidak tampil.
- **Jejak:** FL-35; BR-QRV-001–005; TC-QRV-001.

### PUB — partisipasi dan statistik publik

#### PUB-001 — Statistik partisipasi RT/RW internal
- **Aktor:** admin, pemerintah desa sesuai permission.
- **Prasyarat:** periode dan wilayah valid.
- **Alur:** sistem mengagregasi nasabah aktif, transaksi final, berat, jenis dominan, dan pertumbuhan per RT/RW; menyajikan tabel/grafik/heatmap.
- **Hasil:** perbandingan wilayah tersedia untuk evaluasi.
- **Kegagalan:** data di bawah ambang privasi tidak dirinci; akses tanpa izin ditolak.
- **Kriteria penerimaan:** koreksi/reversal diperhitungkan; filter konsisten; tidak ada saldo individual pada tampilan agregat.
- **Jejak:** FL-26, FL-36; BR-PUB-001–004; TC-PUB-001.

#### PUB-002 — Statistik publik desa
- **Aktor:** publik, admin penerbit.
- **Prasyarat:** metrik telah diizinkan untuk publikasi.
- **Alur:** sistem mengagregasi total sampah/plastik, nasabah aktif, progres target, jadwal, dan kegiatan; menghapus dimensi pribadi; admin mengatur publikasi.
- **Hasil:** statistik desa tersedia tanpa data pribadi.
- **Kegagalan:** agregat berisiko identifikasi disembunyikan atau digabung; data internal tidak bocor.
- **Kriteria penerimaan:** hanya allowlist metrik; tidak ada endpoint drill-down individual; cache tidak menyimpan respons privat.
- **Jejak:** FL-07, FL-26, FL-29; BR-PUB-005–009; TC-PUB-002.

### RPT — laporan dan ekspor

#### RPT-001 — Laporan web, CSV, Excel, dan PDF
- **Aktor:** admin dan pihak internal berizin.
- **Prasyarat:** permission laporan/ekspor dan filter valid.
- **Alur:** pengguna memilih jenis, periode, wilayah, dan filter; sistem mengotorisasi scope, membangun laporan langsung atau antrean berkala berbatas waktu, mencatat ekspor, lalu menyediakan hasil.
- **Hasil:** laporan operasional, transaksi, saldo, penjemputan, pencairan, sembako, koreksi, partisipasi, dan rekonsiliasi tersedia dalam format diminta.
- **Kegagalan:** akses/filter invalid ditolak; pekerjaan besar gagal tanpa membuka file parsial.
- **Kriteria penerimaan:** angka lintas format konsisten; ekspor dilindungi dan kedaluwarsa; aktivitas tercatat; CSV aman dari formula injection.
- **Jejak:** FL-26; BR-RPT-001–006; TC-RPT-001.

### AUD — audit log

#### AUD-001 — Audit aktivitas penting dan retensi
- **Aktor:** sistem, admin pembaca, superadmin teknis untuk retensi resmi.
- **Prasyarat:** kejadian audit terjadi atau pembaca berizin.
- **Alur:** sistem mencatat pelaku, waktu, IP/perangkat relevan, aksi, objek, nilai lama/baru yang aman, dan korelasi; admin menelusuri; retensi hanya dijalankan melalui kebijakan teknis.
- **Hasil:** perubahan akun, akses, harga, transaksi, saldo, status, persetujuan, pembayaran, penyerahan, ekspor, dan konfigurasi dapat ditelusuri.
- **Kegagalan:** pengguna operasional tidak dapat mengubah/menghapus log; secret tidak dicatat.
- **Kriteria penerimaan:** log append-oriented; pencarian berizin; kegagalan audit pada aksi keuangan memblokir commit atau dicakup transaksi yang sama.
- **Jejak:** FL-27; BR-AUD-001–006; TC-AUD-001.

### REC — rekonsiliasi

#### REC-001 — Rekonsiliasi harian dan penanganan selisih
- **Aktor:** admin, bendahara, petugas.
- **Prasyarat:** pelayanan harian ditutup dan seluruh bukti tersedia.
- **Alur:** sistem mengambil saldo awal, transaksi, setoran, pencairan, penukaran, hold, koreksi, dan kas; petugas mencocokkan bukti; selisih ditelusuri dan dikoreksi resmi bila terbukti; rekap disahkan.
- **Hasil:** laporan rekonsiliasi menyimpan hasil, selisih, penjelasan, pelaksana, dan pengesahan.
- **Kegagalan:** selisih terbuka mencegah status “sesuai”; koreksi informal tidak diizinkan.
- **Kriteria penerimaan:** total ledger dapat direproduksi; kas dibandingkan dengan pembayaran; selisih memiliki status dan tindak lanjut.
- **Jejak:** FL-29, FL-30; BR-REC-001–006; TC-REC-001.

### PWA — aplikasi web progresif terbatas

#### PWA-001 — Instalasi dan cache informasi umum
- **Aktor:** warga, petugas, publik.
- **Prasyarat:** browser mendukung PWA dan aplikasi dilayani melalui HTTPS.
- **Alur:** browser membaca manifest/service worker; pengguna dapat memasang aplikasi; cache hanya menyimpan aset versi dan halaman informasi umum yang diizinkan; transaksi selalu memerlukan koneksi.
- **Hasil:** aplikasi installable dengan fallback informasi umum terbatas.
- **Kegagalan:** offline pada aksi privat/keuangan menampilkan kebutuhan koneksi dan tidak mengantrekan transaksi.
- **Kriteria penerimaan:** tidak ada cache saldo, profil, transaksi, token, atau respons privat; pembaruan cache terversi; logout membersihkan data privat browser yang relevan.
- **Jejak:** FL-05, FL-07; BR-PWA-001–005; TC-PWA-001.

## 3. Kebutuhan nonfungsional lintas domain

| ID | Kebutuhan | Kriteria penerimaan ringkas |
|---|---|---|
| NFR-SEC-001 | Seluruh trafik produksi memakai HTTPS; autentikasi, otorisasi record, validasi server, CSRF, XSS, SQL injection, rate limiting, unggahan privat, audit, dan secret mengikuti [SECURITY.md](SECURITY.md). | Uji keamanan kritis lulus dan tidak ada secret pada kode/log. |
| NFR-DAT-001 | Operasi keuangan atomik, idempotent, memakai constraint dan lock yang sesuai. | Uji konkurensi/klik ganda menghasilkan satu efek. |
| NFR-UX-001 | UI mobile-first Sindangheula Green Ledger mengikuti [DESIGN.md](DESIGN.md). | Lebar 360, 390, 768, dan 1280 px berfungsi tanpa scroll horizontal tak disengaja. |
| NFR-A11Y-001 | WCAG AA, target sentuh minimal 48 px untuk warga/petugas, teks isi warga minimal 16 px, status memakai teks+ikon+warna. | Audit otomatis dan manual tidak menemukan blocker kritis. |
| NFR-PERF-001 | Query terindeks, pagination, gambar terkompresi, dan pekerjaan ekspor besar dibatasi sesuai shared hosting. | Halaman utama responsif pada volume uji yang disepakati tanpa timeout normal. |
| NFR-COMP-001 | Mendukung Chrome Android/desktop, Edge, Safari mobile, dan Firefox versi yang masih didukung vendor. | Skenario kritis lulus pada matriks browser. |
| NFR-TIME-001 | Zona waktu aplikasi `Asia/Jakarta`; waktu database disimpan konsisten dan ditampilkan dalam zona aplikasi. | Pengujian batas hari dan cron menghasilkan tanggal bisnis yang benar. |
| NFR-OPS-001 | Backup database harian dan media berkala, salinan terpisah, verifikasi, serta uji restore. | Sasaran awal RPO ≤24 jam dan RTO ≤8 jam terbukti dalam latihan. |
| NFR-HOST-001 | Deployment sesuai batas Hostinger shared hosting pada [DEPLOYMENT.md](DEPLOYMENT.md). | Tidak memerlukan root, Redis, Horizon, Supervisor, WebSocket, atau worker permanen. |
| NFR-SCOPE-001 | Data plastik hanya mendukung tujuan pemanfaatan lanjutan paving block; produksi tidak dikelola. | Tidak ada modul formula, batch, suhu, uji tekan, stok produk jadi, distribusi, atau biaya produksi. |

## 4. Traceability 36 flowchart

Nomor mengacu pada urutan diagram di `Kumpulan_Flowchart_Bank_Sampah_Digital_Sindangheula.pdf` dan rincian [USER_FLOWS.md](USER_FLOWS.md).

| Flow | Judul | Requirement utama |
|---|---|---|
| FL-01 | Tahapan Pengembangan Aplikasi | NFR-OPS-001, seluruh baseline |
| FL-02 | Peta Jalan Pengembangan | NFR-SCOPE-001, seluruh baseline |
| FL-03 | Pengujian dan Penerimaan Sistem | seluruh requirement; [TEST_PLAN.md](TEST_PLAN.md) |
| FL-04 | Diagram Konteks Sistem | USR-001, USR-002 |
| FL-05 | Arsitektur Sistem Tingkat Tinggi | NFR-HOST-001, PWA-001 |
| FL-06 | Alur Hak Akses Pengguna | AUTH-002, USR-001 |
| FL-07 | Sitemap Aplikasi | USR-002, ANN-001, PUB-002 |
| FL-08 | Registrasi dan Verifikasi Akun | AUTH-001, AUTH-003 |
| FL-09 | Setoran Sampah Langsung | CST-001, DEP-001, PRC-002 |
| FL-10 | Pengajuan dan Penyelesaian Penjemputan | PUP-001, DEP-001 |
| FL-11 | Pencairan Saldo Tunai | WDR-001, BAL-002 |
| FL-12 | Penukaran Saldo dengan Sembako | GRC-001, BAL-002 |
| FL-13 | Koreksi Transaksi Final | DEP-002, BAL-002, AUD-001 |
| FL-14 | Perubahan Harga Sampah | PRC-001, PRC-002 |
| FL-15 | Alur Notifikasi dan Pengingat | NOT-001, WA-001, ANN-001 |
| FL-16 | Alur Saldo Masuk | DEP-001, BAL-001 |
| FL-17 | Alur Saldo Keluar | BAL-001, WDR-001, GRC-001 |
| FL-18 | Alur Saldo Tertahan | BAL-002, WDR-001, GRC-001 |
| FL-19 | Siklus Saldo Warga | BAL-001, BAL-002 |
| FL-20 | Pencegahan Transaksi Ganda | DEP-003, BAL-001 |
| FL-21 | Pembatalan dan Pengembalian Saldo | BAL-002, WDR-001, GRC-001 |
| FL-22 | Status Penjemputan | PUP-001 |
| FL-23 | Status Pencairan Tunai | WDR-001 |
| FL-24 | Status Penukaran Sembako | GRC-001 |
| FL-25 | Status Transaksi Setoran | DEP-001, DEP-002 |
| FL-26 | Pembuatan Laporan dan Statistik | RPT-001, PUB-001, PUB-002 |
| FL-27 | Audit Log | AUD-001 |
| FL-28 | Backup dan Pemulihan Data | NFR-OPS-001, AUD-001 |
| FL-29 | Penanganan Kesalahan Transaksi | DEP-003, BAL-002, REC-001 |
| FL-30 | Rekonsiliasi Harian | REC-001, PUB-001 |
| FL-31 | Target Pengumpulan Sampah Desa | TGT-001 |
| FL-32 | Bank Sampah Keliling per RT/RW | MOB-001, DEP-001 |
| FL-33 | Estimasi Nilai Sebelum Setor | EST-001, WST-001 |
| FL-34 | Pelayanan Warga Tanpa Smartphone | CST-002, AUTH-001 |
| FL-35 | Verifikasi Bukti Transaksi dengan QR | QRV-001 |
| FL-36 | Pengaturan Kapasitas Penjemputan Harian | PUP-001, REG-001 |

ID `FL-01`–`FL-36` mengikuti urutan generator dan diagram final sebagaimana dirinci secara normatif di [USER_FLOWS.md](USER_FLOWS.md). Nomor halaman PDF berbeda karena lima halaman pendahuluan; gunakan ID dan judul untuk traceability.

## 5. Matriks cakupan fitur proposal

| Cakupan proposal | Requirement |
|---|---|
| Akun, pengguna, role, permission | AUTH-001–003, USR-001–002, [PERMISSIONS.md](PERMISSIONS.md) |
| Wilayah, nasabah, kartu, QR | REG-001, CST-001–002 |
| Sampah, harga, edukasi | WST-001, PRC-001–002 |
| Setoran, penimbangan, bukti, transaksi ganda | DEP-001–003 |
| Ledger, saldo, hold, koreksi, reversal | BAL-001–002, DEP-002 |
| Penjemputan dan kapasitas | PUP-001 |
| Pencairan dan sembako | WDR-001, GRC-001 |
| Notifikasi, WhatsApp manual, pengumuman | NOT-001, WA-001, ANN-001 |
| Target, keliling, estimasi, QR bukti | TGT-001, MOB-001, EST-001, QRV-001 |
| Partisipasi, publik, laporan | PUB-001–002, RPT-001 |
| Audit, rekonsiliasi, PWA | AUD-001, REC-001, PWA-001 |

## 6. Perubahan requirement

Perubahan ID, perilaku, atau acceptance criteria wajib melalui change request, analisis dampak pada semua dokumen tertaut, persetujuan pengelola, dan entri [CHANGELOG.md](CHANGELOG.md). Pengurutan implementasi di [ROADMAP.md](ROADMAP.md) tidak mengubah kewajiban requirement mana pun.
