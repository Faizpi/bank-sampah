# Operasional dan SOP

## 1. Tujuan dan prinsip

Dokumen ini menjadi SOP harian bagi warga, petugas, bendahara, admin, dan pengelola teknis. Semua tindakan memakai akun pribadi, permission [PERMISSIONS.md](PERMISSIONS.md), aturan [BUSINESS_RULES.md](BUSINESS_RULES.md), serta waktu `Asia/Jakarta`. Tidak ada edit saldo langsung, pengiriman WhatsApp otomatis, transaksi offline tersinkron, atau proses produksi paving block.

## 2. Tanggung jawab

| Role | Tanggung jawab | Serah terima minimum |
|---|---|---|
| Warga | Menjaga akun/kartu, memberi data/foto benar, memeriksa bukti/saldo. | Bukti, nomor pengajuan, alasan/status. |
| Petugas | Identifikasi, timbang, setoran, pickup, tugas, bukti, layanan berbantuan/keliling. | Rekap tugas, alat, bukti, insiden. |
| Bendahara | Verifikasi penerima, pembayaran disetujui, kas, bukti, rekonsiliasi. | Kas awal/akhir, daftar paid/pending, bukti. |
| Admin | Verifikasi, master/harga, approve, kapasitas/jadwal, koreksi, laporan/audit. | Keputusan tertunda, koreksi, selisih, perubahan master. |
| Superadmin teknis | Deploy, akses teknis, backup/restore, cron, health, insiden teknis; bersama admin membuka panel back-office (`backoffice.access`). | Release, backup, secret/access rotation, log insiden. |

## 3. Pembukaan pelayanan

Petugas/admin melakukan checklist sebelum menerima transaksi:

1. Login dengan akun sendiri; pastikan role/area/tugas benar.
2. Pastikan tanggal/waktu aplikasi benar dan layanan/jadwal hari ini aktif.
3. Periksa internet, perangkat, baterai/power, printer bila digunakan, kamera, dan akses storage.
4. Pastikan timbangan rata, nol, bersih, layak, dan hasil uji beban referensi sesuai prosedur alat.
5. Tinjau harga aktif, jenis/kondisi diterima, pengumuman, kapasitas pickup, dan jadwal keliling.
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

1. Petugas mencari/memindai nomor/QR dan menyebut nama untuk dikonfirmasi warga.
2. Periksa bahwa akun aktif dan bukan warga lain.
3. Pisahkan sampah per jenis/kondisi; jelaskan penolakan item yang tidak memenuhi ketentuan.
4. Nolkan timbangan sebelum setiap penimbangan; timbang aktual dan tunjukkan hasil bila memungkinkan.
5. Masukkan setiap jenis, berat maksimal tiga desimal, dan kondisi. Sistem mengambil harga aktif.
6. Tinjau subtotal per item dan total bersama warga. Jika salah, kembali ke input/timbang; jangan finalisasi.
7. Ambil/unggah bukti sesuai kebijakan dan pastikan upload selesai.
8. Tekan konfirmasi satu kali. Saat loading, jangan refresh/menekan ulang.
9. Bila sukses, cocokkan nomor, saldo masuk, bukti, dan QR verifikasi; cetak/berikan bukti bila diperlukan.
10. Bila respons gagal/terputus, ikuti SOP kegagalan internet sebelum mencoba lagi.

## 6. SOP penjemputan dan kapasitas

### Admin/petugas pemeriksa

1. Tinjau foto, jenis/perkiraan, alamat, area, tanggal, dan kapasitas.
2. Jika tanggal penuh, tawarkan alternatif; jangan memaksa slot melebihi kapasitas.
3. Nilai kelayakan. Tolak dengan alasan yang jelas dan berhenti tanpa jadwal.
4. Bila diterima, tetapkan tanggal, petugas, area/rute, dan catatan; kirim notifikasi.
5. Perubahan jadwal/kapasitas diberitahukan; perkiraan berat bukan nilai saldo.

### Petugas lapangan

1. Buka tugas dan verifikasi alamat/kontak sesuai scope.
2. Ubah `menuju_lokasi` hanya saat benar-benar berangkat.
3. Konfirmasi warga dan sampah; ubah `dijemput` setelah barang diterima.
4. Timbang aktual per jenis dan buat transaksi setoran tertaut.
5. Status `selesai` hanya setelah transaksi final, saldo masuk, dan bukti berhasil.
6. Bila warga tidak ada/sampah tidak sesuai/alat gagal, catat kondisi dan ikuti keputusan status resmi; jangan membuat transaksi perkiraan.

## 7. SOP pencairan tunai

### Pengajuan dan approve

1. Sistem memeriksa nominal/minimum/saldo dan membuat hold.
2. Admin dengan `withdrawal.approve` memeriksa record, risiko, jadwal, dan kas; pengaju berbantuan tidak menyetujui record sendiri.
3. Jika ditolak, tulis alasan; pastikan hold dilepas; jalur berhenti.
4. Jika disetujui, tetapkan batas ambil/lokasi/payer dan informasikan warga.

### Pay oleh bendahara/petugas

1. Buka hanya daftar disetujui/siap diambil dalam assignment.
2. Cocokkan nomor pengajuan, nama, kartu/identitas sesuai kebijakan, nominal, dan status.
3. Hitung uang di depan penerima; minta konfirmasi penerimaan.
4. Unggah bukti, lalu tandai dibayar satu kali.
5. Verifikasi saldo keluar dan hold dikonversi; berikan bukti.
6. Jika bukti/upload/commit gagal, jangan menyerahkan ulang uang sebelum status dan ledger diperiksa.
7. Pengajuan lewat batas diproses scheduler sebagai kedaluwarsa dan hold dilepas; jangan bayar record kedaluwarsa.

## 8. SOP penukaran sembako

1. Warga memilih paket aktif; sistem membuat hold sebesar snapshot nilai.
2. Admin memeriksa ketersediaan **secara manual**; sistem tidak menjadi catatan stok rinci.
3. Bila tidak tersedia/ditolak, isi alasan dan pastikan hold dilepas; jangan lanjut menyiapkan/menyerahkan.
4. Bila disetujui, petugas menyiapkan paket sesuai deskripsi dan menandai siap diambil.
5. Petugas `handover` memverifikasi penerima dan nomor pengajuan.
6. Serahkan paket, unggah bukti, lalu selesaikan satu kali.
7. Pastikan saldo keluar dibuat dan hold dikonversi setelah penyerahan.
8. Bantuan gratis dicatat terpisah dan tidak boleh mengurangi saldo.

## 9. SOP layanan keliling

### Persiapan

1. Admin menetapkan RT/RW, titik, waktu, petugas, jenis diterima, dan kapasitas; cek benturan.
2. Publikasikan jadwal dan kirim pengingat/perubahan.
3. Petugas membawa alat, daftar tugas, kartu/QR fallback, dan bahan bukti cetak.

### Pelaksanaan

1. Buka status titik saat siap; layanan keliling bukan pickup rumah per rumah.
2. Warga datang dan diidentifikasi melalui QR/nomor.
3. Setoran mengikuti SOP setoran langsung lengkap, termasuk snapshot, bukti, idempotensi, dan ledger.
4. Pantau kapasitas dan antrean; jangan menerima transaksi bertipe keliling sebelum titik dibuka.

### Penutupan

1. Hentikan transaksi, pastikan semua request selesai/gagal jelas.
2. Rekap warga, jumlah transaksi, berat, nilai, bukti, item ditolak, dan insiden.
3. Tutup status titik dan serahkan rekap untuk rekonsiliasi.

## 10. SOP warga tanpa smartphone

1. Petugas menjelaskan layanan dan meminta persetujuan; bila tidak setuju, berhenti.
2. Cari nasabah melalui nama/nomor/kartu. Bila belum ada, bantu input tanpa membuat kata sandi yang diketahui petugas; admin memverifikasi.
3. Konfirmasi identitas dan wilayah; aktivitas mencatat warga sebagai pemilik dan petugas sebagai pelaksana.
4. Gunakan nomor/kartu QR dan jalankan SOP layanan yang sama.
5. Berikan bukti cetak, saldo setelah transaksi, status pengajuan, dan cara meminta riwayat/koreksi.
6. Jangan menyimpan kata sandi, login sebagai warga, atau memakai satu akun warga bersama.

## 11. SOP perubahan kata sandi

### A. Perubahan berbantuan langsung oleh admin

1. Gunakan jalur ini hanya ketika pengguna benar-benar lupa kata sandi dan tidak dapat login. Pengguna yang masih dapat login harus memakai perubahan mandiri dari profilnya sendiri.
2. Dari data pengguna/warga, admin dengan `user.reset-password` dan `session.revoke` memilih pengguna target dalam scope yang sah. Admin tidak boleh memilih dirinya sendiri.
3. Verifikasi target dilakukan secara `tatap_muka` atau melalui `callback_nomor_terdaftar`. Kanal lain tidak cukup.
4. Isi metode verifikasi dan alasan 10–1000 karakter. Jangan meminta, menyimpan, mengulang, atau mencatat kata sandi lama.
5. Masukkan kata sandi baru dan konfirmasi. Server memvalidasi konfirmasi, minimum 10 karakter, dan kebijakan password umum/terbocor bila tersedia; kata sandi tidak masuk audit atau log.
6. Setelah berhasil, pastikan seluruh sesi aktif target dicabut dan audit mencatat aktor, metode, alasan, serta hasil. Kegagalan validasi, kondisi lupa/tidak dapat login, permission, scope, atau self-target berhenti tanpa perubahan. Tidak ada token, email, SMS, WhatsApp, atau alur reset publik.

### B. Perubahan mandiri dari profil

1. Pengguna login membuka profil sendiri dan memasukkan kata sandi saat ini, kata sandi baru, serta konfirmasi.
2. Server memverifikasi kata sandi saat ini dan kebijakan kata sandi baru. Jangan mencatat atau menyalin kata sandi pada catatan, audit, atau log.
3. Setelah berhasil, sistem mencabut sesi aktif lain pengguna sambil mempertahankan sesi saat ini bila memungkinkan secara teknis. Bila tidak memungkinkan, sistem mencabut seluruh sesi dan pengguna harus autentikasi ulang.
4. Audit mencatat aktor, metode `mandiri_profil`, alasan sistem `perubahan_mandiri`, dan hasil.

## 12. SOP WhatsApp manual

1. Pilih template resmi dan periksa nomor tujuan.
2. Tekan **Buka WhatsApp**; aplikasi hanya membuka `wa.me`.
3. Tinjau penerima dan isi di WhatsApp; hapus data yang tidak perlu.
4. Pengguna menekan kirim sendiri.
5. Jangan menandai pesan otomatis terkirim dalam sistem. Jika WhatsApp gagal dibuka, salin pesan atau gunakan kanal resmi alternatif tanpa mengubah status bisnis.

## 13. SOP koreksi dan reversal

1. Hentikan pengulangan transaksi dan kumpulkan nomor, bukti, nilai lama, hasil timbang, serta pelapor.
2. Admin berizin membuka transaksi final; pembuat transaksi tidak otomatis boleh mengoreksi sendiri.
3. Isi alasan rinci, bukti, dan nilai benar. Sistem menghitung delta serta dampak saldo.
4. Tinjau apakah dampak menyebabkan saldo negatif atau menyentuh pengajuan lain; eskalasi sesuai aturan.
5. Konfirmasi hanya setelah pemeriksaan. Batal harus berhenti tanpa perubahan.
6. Sistem membuat correction/reversal, mutasi penyesuaian/lawan, audit, dan notifikasi; transaksi asli tidak dihapus.
7. Warga menerima informasi sebelum/sesudah, alasan, tanggal, dan dampak yang aman.
8. Masukkan koreksi dalam rekonsiliasi harian dan minta pengesahan pihak berbeda bila memungkinkan.

## 14. SOP rekonsiliasi dan penutupan pelayanan

1. Hentikan penerimaan transaksi baru dan pastikan task in-flight memiliki status final/gagal jelas.
2. Ambil saldo awal, setoran final, pickup selesai, pencairan paid, sembako selesai, hold aktif/dilepas, koreksi/reversal, dan kas.
3. Cocokkan nomor, nilai, bukti, petugas, serta kas fisik pencairan.
4. Cocokkan ledger masuk/keluar dan hold dengan kewajiban saldo.
5. Jika selisih, tandai terbuka; telusuri idempotensi, status, bukti, kas, dan assignment. Jangan sahkan “sesuai”.
6. Lakukan koreksi resmi hanya bila bukti memadai, lalu hitung ulang.
7. Bendahara/admin mengesahkan sesuai separation of duties; simpan laporan dan catatan.
8. Catat kas akhir, tugas tertunda, kapasitas/jadwal besok, alat, dan insiden.
9. Logout, amankan kartu/perangkat/kas, dan tutup lokasi.

## 15. Gangguan internet

1. Jangan finalisasi berulang. Catat waktu, warga, draf/nomor, idempotency reference yang terlihat, dan layar status.
2. Jika belum mengirim, pertahankan draf dan tunggu koneksi.
3. Jika request telah dikirim tetapi hasil tidak jelas, periksa riwayat transaksi/ledger dari koneksi stabil sebelum retry.
4. Jika hasil lama ada, gunakan/cetak hasil tersebut. Jika tidak ada dan sistem menyatakan aman, retry dengan mekanisme idempotent.
5. Aplikasi tidak mengantrekan transaksi offline; catatan manual darurat tidak menjadi saldo sampai dimasukkan dan diverifikasi resmi.
6. Setelah pulih, cocokkan semua catatan manual dengan sistem dan rekonsiliasi.

## 16. Gangguan timbangan

1. Hentikan penimbangan; jangan menebak berat atau memakai perkiraan pickup.
2. Periksa permukaan, nol, baterai/daya, dan uji beban referensi.
3. Jika tetap tidak valid, beri tanda alat tidak dipakai dan gunakan timbangan cadangan terverifikasi.
4. Jika tidak ada alat sah, tunda transaksi/penjemputan atau bawa ke lokasi timbang resmi; komunikasikan kepada warga.
5. Catat alat, waktu, transaksi terdampak, dan tindakan. Transaksi yang terlanjur final salah mengikuti koreksi, bukan edit.

## 17. Backup dan restore

### Backup

1. Setiap permintaan metadata backup baru wajib membawa `operator-key` eksplisit dari pemanggil. Untuk actor dan key yang sama, retry dengan payload kanonik (alias, checksum, ukuran, dan retensi ternormalisasi) mengembalikan backup yang sama; key yang sama untuk payload berbeda ditolak.
2. Scheduler/teknis menjalankan database harian dan media berkala; backup pra-deploy wajib.
2. Simpan terpisah, lindungi/enkripsi, hitung checksum, dan catat status/ukuran/waktu.
3. Periksa kegagalan setiap hari; backup gagal segera diulang/diinvestigasi.
4. Terapkan retensi yang disetujui tanpa menghapus satu-satunya salinan valid.

### Uji restore

1. Jadwalkan berkala dan gunakan environment terisolasi.
2. Pilih backup, verifikasi checksum, restore database dan media yang konsisten.
3. Periksa login test, row count, transaksi, ledger/hold, file, audit, serta migration status.
4. Catat waktu mulai/selesai dan bukti RPO/RTO; perbaiki prosedur bila gagal.

### Restore insiden produksi

1. Deklarasikan insiden, maintenance mode, hentikan cron penulis, lindungi log/bukti.
2. Tentukan restore point dan dampak data setelah RPO.
3. Restore database+media konsisten, deploy release kompatibel, cache ulang.
4. Verifikasi integritas, permission, ledger, hold, QR/file, scheduler.
5. Rekonsiliasi data setelah restore; transaksi yang hilang dipulihkan melalui prosedur resmi, bukan edit saldo.
6. Dokumentasikan keputusan, pelaksana, hasil, dan komunikasi.

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

Tinjau audit, failed task, rekonsiliasi, backup, cron, dan contact list. Superadmin tidak menerima kewenangan saldo hanya karena menangani pergantian teknis.

## 19. Monitoring rutin

| Frekuensi | Pemeriksaan |
|---|---|
| Setiap pelayanan | alat, internet, harga, tugas, failed transaction, kas, rekonsiliasi |
| Harian | scheduler heartbeat, failed jobs, backup, error log, storage/inode, hold kedaluwarsa |
| Mingguan | permission/assignment anomali, kapasitas, transaksi ganda, koreksi, statistik, file temp |
| Bulanan | dependency/security update, restore sample, performa DB, quota hosting, SSL/domain, SOP |
| Saat pergantian/rilis | akses, secret, rehearsal MariaDB disposable sebelum UAT/production, backup, rollback, restore, smoke test, training |

## 20. Eskalasi

- Saldo/transaksi ganda, akses lintas warga, uang/paket diserahkan tanpa record, atau backup hilang: hentikan proses terkait dan eskalasi sebagai insiden kritis.
- Selisih kas/ledger: jangan tutup sebagai sesuai; admin+bendahara menelusuri.
- Gangguan teknis biasa: catat incident ID, gejala, waktu, perangkat, langkah; jangan kirim secret/screenshot data warga tanpa redaksi.
- Ikuti [SECURITY.md](SECURITY.md) untuk containment dan [DEPLOYMENT.md](DEPLOYMENT.md) untuk rollback.
