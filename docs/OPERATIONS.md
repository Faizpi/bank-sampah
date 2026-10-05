# Operasional dan SOP

## 1. Tujuan dan prinsip

Dokumen ini menjadi SOP harian bagi warga, petugas, bendahara, admin, dan pengelola teknis. Semua tindakan memakai akun pribadi, permission [PERMISSIONS.md](PERMISSIONS.md), aturan [BUSINESS_RULES.md](BUSINESS_RULES.md), serta waktu `Asia/Jakarta`. Tidak ada edit saldo langsung, kanal WhatsApp, transaksi offline tersinkron, atau proses produksi paving block.

## 2. Tanggung jawab

| Role | Tanggung jawab | Serah terima minimum |
|---|---|---|
| Warga | Menjaga akun/kartu, memberi data/foto benar, memeriksa bukti/saldo. | Bukti, nomor pengajuan, alasan/status. |
| Petugas | Identifikasi, timbang, setoran, pickup, tugas, bukti, layanan berbantuan/keliling. | Rekap tugas, alat, bukti, insiden. |
| Bendahara | Verifikasi penerima, pembayaran disetujui, kas, bukti, laporan. | Daftar paid/pending, bukti, dan ekspor laporan. |
| Admin | Verifikasi, master/harga, approve, penugasan/jadwal, koreksi, laporan/audit. | Keputusan tertunda, koreksi, selisih, perubahan master. |
| Superadmin | Seluruh tugas operasional admin, laporan atau audit, role atau permission, Health, deploy, cron, dan insiden teknis. Bersama admin membuka panel back-office (`backoffice.access`). | Keputusan operasional, release, rotasi secret atau akses, dan log insiden. |

## 3. Pembukaan pelayanan

Petugas/admin melakukan checklist sebelum menerima transaksi:

1. Login dengan akun sendiri; pastikan role/area/tugas benar.
2. Pastikan tanggal/waktu aplikasi benar dan layanan/jadwal hari ini aktif.
3. Periksa internet, perangkat, baterai/power, printer bila digunakan, kamera, dan akses storage.
4. Pastikan timbangan rata, nol, bersih, layak, dan hasil uji beban referensi sesuai prosedur alat.
5. Tinjau harga aktif, jenis/kondisi diterima, pengumuman, penjadwalan pickup, dan jadwal keliling.
6. Bendahara menghitung/mencatat kas awal tanpa membuka informasi kepada pihak tidak berizin.
7. Pastikan tidak ada failed transaction/hold/selisih sebelumnya yang belum ditangani.
8. Buka status layanan/titik keliling bila semua siap. Jika pemeriksaan kritis gagal, pelayanan finansial tidak dibuka sampai aman.

## 4. SOP warga

### Akun dan layanan

1. Registrasi dengan data domisili, telepon, alamat, dan persetujuan.
2. Tunggu keputusan admin; bila ditolak, baca alasan dan ajukan perbaikan melalui kanal resmi.
3. Simpan kata sandi dan kartu/QR; laporkan kehilangan agar token dirotasi.
4. Periksa harga, kondisi sampah, jadwal, estimasi, dan edukasi sebelum datang/mengajukan.
5. Setelah layanan, cocokkan nama, jenis, berat, harga snapshot, total, status, dan saldo pada bukti.
6. Laporkan ketidaksesuaian dengan nomor transaksi dan bukti; jangan meminta petugas mengedit saldo langsung.

### Pengajuan

- Penjemputan: foto wajib, alamat/tanggal benar, perkiraan jujur; pilih alternatif bila penuh.
- Pencairan: nominal tidak melebihi saldo tersedia; datang sesuai jadwal dengan identitas/kartu yang ditetapkan.
- Sembako: pilih paket aktif; ketersediaan tetap diperiksa manual.
- Pembatalan hanya sebelum batas status; periksa bahwa hold dilepas.

## 5. SOP setoran langsung
1. Cari atau pindai nasabah, lalu pastikan nama penerima layanan.
2. Isi jenis, kondisi, dan berat aktual. Periksa ringkasan nilai pada halaman yang sama.
3. Unggah bukti. Simpan draf hanya jika perlu melanjutkan nanti; harga draf mengikuti waktu pembuatan draf.
4. Tekan satu tombol konfirmasi akhir. Setoran di bawah ambang langsung final; setoran bernilai tinggi menunggu pemeriksa yang berbeda.
5. Berikan bukti dan jelaskan apakah saldo sudah masuk atau masih menunggu pemeriksaan. Jangan membuat transaksi kedua ketika respons jaringan terlambat.

## 6. SOP penjemputan
1. Warga memilih tanggal dan mengirim jenis/perkiraan serta foto. Alamat diisi awal dari profil; area tunggal digunakan otomatis.
2. Admin meninjau dan memilih petugas serta tanggal dalam satu formulir Terima dan jadwalkan. Tanggal pilihan bukan slot terjamin; pickup tidak memakai kuota otomatis.
3. Petugas menandai menuju lokasi lalu dijemput. Bila ada kendala, petugas berwenang mencatat alasan dan menghentikan tugas; warga tidak bisa membatalkan setelah petugas berangkat.
4. Timbang aktual, periksa jenis/kondisi/berat, unggah bukti, lalu konfirmasi melalui web.
5. Setoran yang perlu persetujuan membuat pickup tetap dijemput dengan keterangan menunggu pemeriksaan. Persetujuan mencatat saldo dan menyelesaikan pickup; penolakan mengakhiri pickup dengan alasan tanpa saldo masuk.

## 7. SOP pencairan tunai
1. Warga mengisi nominal, tanggal, lokasi, dan area bila pilihannya lebih dari satu. Pengajuan membuat saldo tertahan, bukan saldo keluar.
2. Admin memakai satu formulir Setujui dan tetapkan bendahara. Pembayar harus bendahara aktif dengan area yang sesuai dan berbeda dari pemberi persetujuan.
3. Bendahara membuka antrean miliknya, mencocokkan kartu atau nomor nasabah, dan mengunggah foto bukti.
4. Tinjau penerima, nominal, dan foto pada halaman yang sama. Setelah uang benar-benar diserahkan, tekan Uang tunai sudah diserahkan - catat pembayaran.
5. Sistem membuat satu mutasi keluar dan menutup hold atomik. Tidak ada transfer bank atau pengiriman uang oleh aplikasi.
6. Pengajuan lama yang sudah disetujui tetapi belum ditugaskan masih dapat dilanjutkan melalui tindakan penugasan.

## 8. SOP penukaran sembako
1. Warga memilih paket aktif dan area yang sesuai; sistem menyimpan snapshot paket dan menahan saldo.
2. Admin memeriksa ketersediaan fisik lalu menyetujui atau menolak dengan alasan.
3. Setelah paket tersedia, petugas searea menekan Paket sudah siap - beri tahu warga. Tidak wajib menekan Mulai siapkan.
4. Serah-terima tetap melalui web: cocokkan penerima, simpan bukti, dan konfirmasikan penyerahan.
5. Pengaju, penerima, atau pemberi persetujuan tidak boleh menjadi pelaksana penyerahan transaksi yang sama. Hold menjadi mutasi keluar hanya ketika serah-terima tercatat.
6. Status sedang_disiapkan dari data lama tetap dapat dilanjutkan ke siap_diambil.

## 9. SOP layanan keliling

### Persiapan

1. Admin menetapkan wilayah sesuai hierarki desa, dusun, RW, dan RT, lalu menetapkan titik, waktu, petugas, jenis diterima, serta kapasitas jadwal; cek benturan.
2. Publikasikan jadwal. Setiap perubahan harus terlihat pada informasi jadwal yang dipakai warga dan petugas.
3. Petugas membawa alat, daftar tugas, kartu/QR fallback, dan bahan bukti cetak.

### Pelaksanaan

1. Buka status titik saat siap; layanan keliling bukan pickup rumah per rumah.
2. Warga datang dan diidentifikasi melalui QR/nomor.
3. Setoran mengikuti SOP setoran langsung lengkap, termasuk snapshot, bukti, idempotensi, dan ledger.
4. Pantau kapasitas dan antrean; jangan menerima transaksi bertipe keliling sebelum titik dibuka.

### Penutupan

1. Hentikan transaksi, pastikan semua request selesai/gagal jelas.
2. Rekap warga, jumlah transaksi, berat, nilai, bukti, item ditolak, dan insiden.
3. Tutup status titik dan serahkan rekap untuk laporan.

## 10. SOP warga tanpa smartphone

1. Petugas menjelaskan layanan dan meminta persetujuan; bila tidak setuju, berhenti.
2. Cari nasabah melalui nama/nomor/kartu. Bila belum ada, bantu input tanpa membuat kata sandi yang diketahui petugas; admin memverifikasi.
3. Konfirmasi identitas dan wilayah; aktivitas mencatat warga sebagai pemilik dan petugas sebagai pelaksana.
4. Gunakan nomor/kartu QR dan jalankan SOP layanan yang sama.
5. Berikan bukti cetak, saldo setelah transaksi, status pengajuan, dan cara meminta riwayat/koreksi.
6. Jangan menyimpan kata sandi, login sebagai warga, atau memakai satu akun warga bersama.

## 11. SOP perubahan kata sandi

### A. Perubahan berbantuan langsung oleh admin atau superadmin

1. Gunakan jalur ini hanya ketika pengguna benar-benar lupa kata sandi dan tidak dapat login. Pengguna yang masih dapat login harus memakai perubahan mandiri dari profilnya sendiri.
2. Dari data pengguna/warga melalui alur PasswordAssistance, admin atau superadmin dengan `user.reset-password` dan `session.revoke` memilih pengguna target dalam scope yang sah. Aktor berizin tidak boleh memilih dirinya sendiri.
3. Verifikasi target dilakukan secara `tatap_muka` atau melalui `callback_nomor_terdaftar`. Kanal lain tidak cukup.
4. Isi metode verifikasi dan alasan 10–1000 karakter. Jangan meminta, menyimpan, mengulang, atau mencatat kata sandi lama.
5. Masukkan kata sandi baru dan konfirmasi. Server memvalidasi konfirmasi, minimum 10 karakter, dan kebijakan password umum/terbocor bila tersedia; kata sandi tidak masuk audit atau log.
6. Setelah berhasil, pastikan seluruh sesi aktif target dicabut dan audit mencatat aktor, metode, alasan, serta hasil. Kegagalan validasi, kondisi lupa/tidak dapat login, permission, scope, atau self-target berhenti tanpa perubahan. Tidak ada token, email, SMS, WhatsApp, atau alur reset publik.

### B. Perubahan mandiri dari profil

1. Pengguna login membuka profil sendiri dan memasukkan kata sandi saat ini, kata sandi baru, serta konfirmasi.
2. Server memverifikasi kata sandi saat ini dan kebijakan kata sandi baru. Jangan mencatat atau menyalin kata sandi pada catatan, audit, atau log.
3. Setelah berhasil, sistem mencabut sesi aktif lain pengguna sambil mempertahankan sesi saat ini bila memungkinkan secara teknis. Bila tidak memungkinkan, sistem mencabut seluruh sesi dan pengguna harus autentikasi ulang.
4. Audit mencatat aktor, metode `mandiri_profil`, alasan sistem `perubahan_mandiri`, dan hasil.

## 12. SOP informasi layanan
1. Buka informasi status, pengumuman, dan notifikasi dalam aplikasi.
2. Gunakan ringkasan status warga; buka rincian tahapan hanya bila diperlukan.
3. Notifikasi internal tersimpan setelah commit, bukan bukti pesan eksternal terkirim.
4. WhatsApp tidak digunakan. Telegram belum tersedia.

## 13. SOP koreksi dan reversal

1. Hentikan pengulangan transaksi dan kumpulkan nomor, bukti, nilai lama, hasil timbang, serta pelapor.
2. Admin berizin membuka transaksi final; pembuat transaksi tidak otomatis boleh mengoreksi sendiri.
3. Isi alasan rinci, bukti, dan nilai benar. Sistem menghitung delta serta dampak saldo.
4. Tinjau apakah dampak menyebabkan saldo negatif atau menyentuh pengajuan lain; eskalasi sesuai aturan.
5. Konfirmasi hanya setelah pemeriksaan. Batal harus berhenti tanpa perubahan.
6. Sistem membuat correction atau reversal, mutasi penyesuaian atau lawan, dan audit; transaksi asli tidak dihapus serta riwayat warga diperbarui.
7. Warga menerima informasi sebelum/sesudah, alasan, tanggal, dan dampak yang aman.
8. Pastikan koreksi tercermin pada laporan setelah proses resmi selesai.

## 14. SOP laporan harian

1. Pastikan pencairan dan penyerahan yang dilakukan hari itu sudah memiliki status akhir atau alasan gagal yang jelas.
2. Buka menu **Laporan** dan pilih jenis laporan yang diperlukan.
3. Gunakan preset **Hari ini**, **Minggu ini**, **Per bulan**, atau **Tanggal custom**.
4. Periksa ringkasan, filter area/status bila diperlukan, lalu unduh Excel.
5. Simpan file dengan nama tanggal pelayanan dan letakkan pada folder laporan internal.
6. Jika ada data yang tidak sesuai, gunakan alur koreksi resmi pada transaksi terkait; jangan mengubah file Excel untuk menggantikan data aplikasi.

## 15. Gangguan internet

1. Jangan finalisasi berulang. Catat waktu, warga, draf/nomor, idempotency reference yang terlihat, dan layar status.
2. Jika belum mengirim, pertahankan draf dan tunggu koneksi.
3. Jika request telah dikirim tetapi hasil tidak jelas, periksa riwayat transaksi/ledger dari koneksi stabil sebelum retry.
4. Jika hasil lama ada, gunakan/cetak hasil tersebut. Jika tidak ada dan sistem menyatakan aman, retry dengan mekanisme idempotent.
5. Aplikasi tidak mengantrekan transaksi offline; catatan manual darurat tidak menjadi saldo sampai dimasukkan dan diverifikasi resmi.
6. Setelah pulih, cocokkan semua catatan manual dengan sistem dan laporan.

## 16. Gangguan timbangan

1. Hentikan penimbangan; jangan menebak berat atau memakai perkiraan pickup.
2. Periksa permukaan, nol, baterai/daya, dan uji beban referensi.
3. Jika tetap tidak valid, beri tanda alat tidak dipakai dan gunakan timbangan pengganti yang telah diverifikasi.
4. Jika tidak ada alat sah, tunda transaksi/penjemputan atau bawa ke lokasi timbang resmi; komunikasikan kepada warga.
5. Catat alat, waktu, transaksi terdampak, dan tindakan. Transaksi yang terlanjur final salah mengikuti koreksi, bukan edit.

## 17. Health dan penanganan gangguan

Health adalah satu-satunya administrasi teknis aktif pada UI. Superadmin memerlukan `system.maintenance` untuk melihat status aman secara baca-saja; permission tersebut tidak membuka halaman atau tindakan teknis lain.

Jika terjadi gangguan:

1. Catat incident ID, waktu, gejala, dan proses yang terdampak.
2. Hentikan pengulangan tindakan finansial sampai status transaksi dan ledger diperiksa.
3. Gunakan audit serta riwayat status untuk menentukan apakah operasi sudah lengkap atau gagal.
4. Jalankan koreksi atau reversal resmi bila data finansial benar-benar perlu diperbaiki. Jangan mengedit saldo langsung.
5. Verifikasi kembali akses, transaksi, ledger, hold, dan file terkait sebelum membuka proses yang dihentikan.

## 18. Pergantian petugas/admin

### Sebelum hari terakhir

1. Inventarisasi tugas, assignment, pengajuan, koreksi, laporan, kas, alat, kartu akses, dan pengetahuan operasional.
2. Serahkan dokumen/referensi tanpa membagikan password atau secret pribadi.
3. Tentukan pengganti dan role minimum; lakukan pelatihan/UAT tugas.

### Pada waktu efektif

1. Nonaktifkan akun lama dan cabut sesi/assignment.
2. Rotasi credential bersama yang tidak dapat dihindari dan secret teknis yang diketahui pihak lama.
3. Terbitkan akun baru pribadi dengan permission minimum dan alasan assignment.
4. Audit perubahan role/permission; cek separation of duties.
5. Uji login/tugas/akses baru dan pastikan akun lama ditolak.

### Setelah pergantian

Tinjau audit, failed task, laporan, Health, cron, dan contact list. Permission koreksi, pembalikan, dan penyesuaian saldo hanya tersedia pada pemilik rekonsiliasi yang ditetapkan; baseline saat ini berada pada superadmin dan tetap wajib memakai alasan, bukti, serta audit.

## 19. Monitoring rutin

| Frekuensi | Pemeriksaan |
|---|---|
| Setiap pelayanan | alat, internet, harga, tugas, failed transaction, kas, laporan |
| Harian | Health, scheduler heartbeat, error log, storage atau inode, hold kedaluwarsa |
| Mingguan | anomali permission atau assignment, kapasitas, transaksi ganda, koreksi, statistik, file sementara |
| Bulanan | pembaruan dependency atau keamanan, performa database, kuota hosting, SSL atau domain, SOP |
| Saat pergantian atau rilis | akses, secret, rehearsal MySQL 8.0.30 disposable sebelum UAT atau production, rollback, smoke test, dan pelatihan |

## 20. Eskalasi

- Saldo atau transaksi ganda, akses lintas warga, atau uang atau paket yang diserahkan tanpa record: hentikan proses terkait dan eskalasi sebagai insiden kritis.
- Selisih kas/ledger: jangan tutup sebagai sesuai; admin+bendahara menelusuri.
- Gangguan teknis biasa: catat incident ID, gejala, waktu, perangkat, langkah; jangan kirim secret/screenshot data warga tanpa redaksi.
- Ikuti [SECURITY.md](SECURITY.md) untuk containment dan [DEPLOYMENT.md](DEPLOYMENT.md) untuk rollback.
