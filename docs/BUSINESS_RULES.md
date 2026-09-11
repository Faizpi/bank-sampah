# Aturan Bisnis

## 1. Kedudukan aturan

Aturan bisnis adalah invariant wajib bagi seluruh alur pada [REQUIREMENTS.md](REQUIREMENTS.md) dan [USER_FLOWS.md](USER_FLOWS.md). Validasi teknis rinci berada di [VALIDATION.md](VALIDATION.md). Semua waktu bisnis memakai `Asia/Jakarta`.

### Aturan lintas domain

- **BR-GEN-001:** Jalur gagal, ditolak, dibatalkan, kedaluwarsa, atau rollback harus berhenti tanpa menjalankan efek jalur sukses.
- **BR-GEN-002:** Operasi yang memengaruhi transaksi, saldo, hold, status final, pembayaran, atau penyerahan harus atomik, diotorisasi pada record, idempotent, dan diaudit.
- **BR-GEN-003:** Record final tidak dihapus atau ditimpa; perbaikan memakai koreksi, reversal, atau status pengganti yang dapat ditelusuri.
- **BR-GEN-004:** Status hanya boleh berubah melalui transisi eksplisit. Setiap transisi menyimpan status lama/baru, pelaku, waktu, alasan atau catatan yang relevan.
- **BR-GEN-005:** Nilai rupiah disimpan sebagai integer/bigint tanpa pecahan. Berat disimpan sebagai `DECIMAL` maksimal tiga desimal.
- **BR-GEN-006:** Semua nomor bisnis, token, dan referensi sumber yang disyaratkan unik harus dijamin oleh unique constraint, bukan hanya pemeriksaan aplikasi.

## 2. Akun dan pengguna

- **BR-AUTH-001:** Registrasi warga menghasilkan status `menunggu_verifikasi`; akun tidak aktif sebelum keputusan admin.
- **BR-AUTH-002:** Nomor telepon yang dinormalisasi harus unik untuk akun yang berlaku; duplikasi identitas diperiksa tanpa mengekspos data warga lain.
- **BR-AUTH-003:** Penolakan verifikasi wajib beralasan dan tidak boleh mengaktifkan akun atau menerbitkan identitas nasabah aktif.
- **BR-AUTH-004:** Hanya akun `aktif` yang dapat membuat sesi; login gagal tidak boleh membedakan “akun tidak ada” dan “kata sandi salah” kepada pengguna umum.
- **BR-AUTH-005:** Perubahan kata sandi memiliki dua jalur saja. (A) Admin atau superadmin berizin hanya dapat mengubah langsung kata sandi pengguna target yang benar-benar lupa kata sandi dan tidak dapat login, melalui alur PasswordAssistance yang tervalidasi dan data pengguna/warga, setelah verifikasi `tatap_muka` atau `callback_nomor_terdaftar`, alasan 10–1000 karakter, permission `user.reset-password` dan `session.revoke`, serta scope record sah. Aktor berizin tidak boleh menjadi target sendiri. Server memvalidasi konfirmasi, panjang minimum, dan kebijakan password umum, lalu secara atomik menyimpan hash, mencabut seluruh sesi aktif target, dan mengaudit aktor/metode/alasan/hasil tanpa kata sandi. (B) Pengguna terautentikasi hanya dapat mengubah kata sandinya sendiri dari profil dengan kata sandi saat ini, kata sandi baru, dan konfirmasi yang tervalidasi. Setelah sukses, sistem mencabut sesi aktif lain pengguna sambil mempertahankan sesi saat ini bila memungkinkan secara teknis, atau mencabut seluruh sesi dan meminta autentikasi ulang bila tidak memungkinkan; audit mencatat aktor/metode `mandiri_profil`/hasil tanpa kata sandi. Tidak ada reset publik, token, masa berlaku atau penyimpanan/pengiriman token, email, SMS, WhatsApp, rate limit reset, atau permintaan generik.
- **BR-AUTH-006:** Pendaftaran wajib menerima secara afirmatif [Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0](TERMS_AND_PRIVACY.md) yang tersedia untuk publik. Penerimaan menyimpan versi yang saat ini efektif pada `users.terms_version` dan waktu server pada `users.terms_accepted_at`, serta mencatat riwayat penerimaan append-only per pengguna dan versi. Penerimaan tidak menggantikan verifikasi, aktivasi, autentikasi, atau login, dan tidak mengubah status `menunggu_verifikasi`. Riwayat penerimaan dipertahankan; versi baru berlaku prospektif dan persetujuan ulang hanya mengikuti proses produk yang telah disetujui serta didokumentasikan tanpa mengubah sesi atau status akun.
- **BR-USR-001:** Satu pengguna dapat memiliki role sesuai penugasan, tetapi tindakan selalu memerlukan permission granular dan scope record.
- **BR-USR-002:** Penonaktifan pengguna menghentikan akses baru tanpa menghapus transaksi, audit, atau keterkaitan historis.
- **BR-USR-003:** Perubahan role/permission, status akun, dan reset administratif wajib diaudit.
- **BR-USR-004:** Dashboard hanya menampilkan data yang dapat dibuka pengguna melalui endpoint detail terkait.
- **BR-USR-005:** `user.view` wajib disertai scope eksplisit untuk membuka pengguna lain. `user.view.area` hanya berlaku bagi `StaffProfile` yang efektif hari ini dengan area pelayanan aktif dan pivot RT aktif, serta hanya menampilkan nasabah aktif pada RT tersebut; `user.view.all` menampilkan seluruh pengguna aktif. Tanpa scope eksplisit, akses fail-closed ke diri sendiri; query dan policy detail memakai scope yang sama dan akses di luar scope menghasilkan 404.

## 3. Nasabah dan wilayah

- **BR-CST-001:** Nomor nasabah unik, stabil, tidak dapat digunakan ulang untuk warga lain, dan diterbitkan setelah verifikasi.
- **BR-CST-002:** QR nasabah memuat token acak/referensi, bukan nama, alamat, telepon, nomor identitas, atau saldo.
- **BR-CST-003:** Hasil scan hanya kandidat identitas; petugas wajib mengonfirmasi nama sebelum transaksi.
- **BR-CST-004:** Layanan berbantuan memerlukan persetujuan warga dan mencatat warga sebagai pemilik record serta petugas sebagai pelaksana.
- **BR-CST-005:** Petugas dilarang meminta, menyimpan, atau mengambil alih kata sandi warga.
- **BR-CST-006:** Warga tanpa smartphone berhak menerima nomor/kartu QR, bukti cetak, informasi saldo, dan riwayat koreksi melalui prosedur resmi.
- **BR-REG-001:** Hierarki wilayah adalah desa → dusun → RW → RT; relasi parent wajib valid dan aktif saat pembuatan record baru.
- **BR-REG-002:** Area pelayanan dapat mencakup satu atau lebih wilayah, tetapi tidak mengubah identitas administratif warga.
- **BR-REG-003:** Wilayah bereferensi histori dinonaktifkan, bukan dihapus fisik.

## 4. Sampah, harga, berat, dan pembulatan

- **BR-WST-001:** Jenis sampah wajib memiliki kategori, satuan, minimal satu kondisi penerimaan, dan status. Kondisi adalah master terpisah dan relasinya banyak-ke-banyak dengan jenis sampah.
- **BR-WST-002:** Jenis nonaktif tidak dapat dipilih pada transaksi baru, tetapi tetap tampil pada histori.
- **BR-WST-003:** Edukasi harus kontekstual terhadap jenis atau tindakan dan tidak boleh mengubah aturan transaksi.
- **BR-PRC-001:** Harga sampah adalah rupiah per satuan, berupa integer nol atau positif; harga nol hanya boleh dipakai jika kebijakan penerimaan tanpa nilai dinyatakan eksplisit oleh admin.
- **BR-PRC-002:** Periode harga untuk satu pasangan jenis dan kondisi tidak boleh tumpang tindih. Satuan mengikuti jenis sampah dan wilayah bukan scope harga baseline.
- **BR-PRC-003:** Tepat satu harga aktif dipilih berdasarkan waktu efektif transaksi; bila tidak ada, transaksi tidak dapat difinalkan.
- **BR-PRC-004:** Perubahan harga menutup periode lama, membuat record baru, dan diaudit; riwayat harga tidak ditimpa.
- **BR-PRC-005:** Detail transaksi menyimpan snapshot minimal nama/kode jenis, satuan, kondisi, harga per satuan, berat aktual, subtotal, dan versi aturan pembulatan.
- **BR-WGT-001:** Berat aktual lebih dari nol dan disimpan maksimal tiga angka desimal dalam `kg` sebagai satuan kanonik.
- **BR-WGT-002:** Input berat dengan presisi lebih dari tiga desimal ditolak, bukan dipotong diam-diam.
- **BR-WGT-003:** Satuan berat fisik dapat dikonversi ke `kg` sebelum perhitungan hanya bila faktor konversinya ditetapkan; hasil berat kanonik tetap maksimal tiga desimal. Satuan non-berat tidak dikonversi otomatis.
- **BR-RND-001:** Subtotal dihitung dari nilai desimal presisi penuh `berat × harga`, lalu dibulatkan ke rupiah terdekat satu kali per detail transaksi menggunakan half-up.
- **BR-RND-002:** Total transaksi adalah penjumlahan subtotal rupiah setiap detail; total tidak dibulatkan ulang dari agregat berat.
- **BR-RND-003:** Aturan pembulatan yang dipakai disimpan/diidentifikasi pada snapshot agar transaksi lama dapat direproduksi.

## 5. Setoran dan transaksi

- **BR-DEP-001:** Setoran wajib memiliki nomor unik, nasabah aktif, petugas pelaksana, waktu, metode/lokasi, dan minimal satu detail.
- **BR-DEP-002:** Setiap detail wajib memiliki jenis aktif saat dibuat, berat aktual positif, snapshot harga, dan subtotal.
- **BR-DEP-003:** Draf tidak membuat mutasi saldo, QR bukti sah, target, atau statistik transaksi final.
- **BR-DEP-004:** Finalisasi hanya boleh terjadi setelah identitas, detail, total, dan bukti yang disyaratkan dikonfirmasi.
- **BR-DEP-005:** Finalisasi membuat transaksi, detail snapshot, saldo masuk, audit, dan status sumber terkait dalam satu database transaction.
- **BR-DEP-006:** Satu transaksi final memiliki tepat satu mutasi saldo masuk sumber utama.
- **BR-DEP-007:** Petugas pembuat dapat mengubah draf dalam scope tugasnya; transaksi final tidak dapat diedit langsung.
- **BR-DEP-008:** Koreksi final wajib menyimpan alasan, nilai lama/baru, bukti bila relevan, pelaku, dampak saldo, dan hubungan ke transaksi asli.
- **BR-DEP-009:** Reversal tidak menghapus transaksi asli; reversal membuat record lawan dan menandai hubungan pembalikan.
- **BR-DEP-010:** Warga dapat melihat alasan, tanggal, nilai lama/baru, dan dampak saldo koreksi miliknya tanpa catatan internal sensitif.
- **BR-DEP-011:** Setiap perintah finalisasi memiliki idempotency key unik pada actor/scope operasi.
- **BR-DEP-012:** Pengulangan key dengan payload sama mengembalikan hasil pertama; key sama dengan payload berbeda ditolak sebagai konflik.
- **BR-DEP-013:** Finalisasi mengunci record sumber/rekening yang relevan dan mengandalkan constraint unik untuk mencegah efek ganda.
- **BR-DEP-014:** Commit gagal me-rollback seluruh transaksi; retry tidak boleh dibuat dengan menekan konfirmasi berulang tanpa memeriksa hasil lama.

## 6. Ledger, saldo, hold, koreksi, dan reversal

- **BR-BAL-001:** Saldo bukan kolom yang dapat diedit langsung; sumber kebenaran adalah ledger/mutasi dan hold aktif.
- **BR-BAL-002:** `saldo tersedia = total saldo masuk − total saldo keluar − saldo tertahan`.
- **BR-BAL-003:** Rupiah menggunakan integer/bigint; nilai mutasi wajib lebih dari nol dengan arah/jenis yang eksplisit.
- **BR-BAL-004:** Saldo tersedia tidak boleh negatif pada saat commit.
- **BR-BAL-005:** Setiap mutasi memiliki nomor unik, rekening, jenis, nilai, sumber polymorphic/eksplisit, waktu efektif, dan saldo setelah mutasi atau bukti ledger yang setara.
- **BR-BAL-006:** Kombinasi sumber dan jenis efek ledger harus unik agar satu sumber tidak membukukan efek yang sama dua kali.
- **BR-BAL-007:** Mutasi final append-only secara operasional; pembetulan memakai mutasi penyesuaian atau reversal.
- **BR-BAL-008:** Cache saldo, bila digunakan, hanya derivasi dan harus dapat diperiksa dari ledger serta hold.
- **BR-BAL-009:** Hold dibuat saat pengajuan pencairan/penukaran sah diterima sistem, setelah saldo tersedia dikunci dan diperiksa.
- **BR-BAL-010:** Hold aktif mengurangi saldo tersedia tetapi bukan saldo keluar.
- **BR-BAL-011:** Satu pengajuan memiliki maksimal satu hold aktif; nilai hold sama dengan kewajiban nominal pengajuan.
- **BR-BAL-012:** Penyelesaian pembayaran/penyerahan membuat saldo keluar dan menutup hold secara atomik tanpa pengurangan ganda.
- **BR-BAL-013:** Penolakan, pembatalan yang sah, atau kedaluwarsa menutup hold sebagai `dilepas` tanpa membuat saldo keluar.
- **BR-BAL-014:** Koreksi pengurangan yang akan menyebabkan saldo tersedia negatif tidak boleh diselesaikan otomatis; harus mengikuti keputusan resmi yang menjaga ledger tetap valid.
- **BR-BAL-015:** Reversal mutasi membuat mutasi lawan bernilai sama dan referensi dua arah; mutasi asli tidak diubah.
- **BR-BAL-016:** Setiap koreksi atau reversal menimbulkan audit dan memperbarui riwayat yang dapat dilihat warga terkait.

## 7. Pembatalan dan kedaluwarsa

- **BR-CAN-001:** Pembatalan hanya diizinkan pada status yang dinyatakan dalam state machine domain.
- **BR-CAN-002:** Penjemputan dapat dibatalkan warga sebelum petugas berstatus `menuju_lokasi`; setelah itu keputusan operasional berada pada petugas/admin.
- **BR-CAN-003:** Pencairan dapat dibatalkan warga sebelum persetujuan; setelah disetujui hanya pembatalan resmi sebelum pembayaran yang dapat dilakukan pihak berwenang.
- **BR-CAN-004:** Penukaran dapat dibatalkan warga sebelum persetujuan; setelah disetujui hanya pembatalan resmi sebelum penyerahan.
- **BR-CAN-005:** Transaksi setoran final dibatalkan melalui reversal/koreksi, bukan status batal biasa atau penghapusan.
- **BR-EXP-001:** Batas kedaluwarsa pencairan dan sembako dikonfigurasi dalam hari/jam bisnis dan disimpan pada pengajuan saat persetujuan.
- **BR-EXP-002:** Proses scheduler menandai pengajuan melewati batas sebagai `kedaluwarsa` hanya jika belum dibayar/diserahkan.
- **BR-EXP-003:** Kedaluwarsa melepas hold atomik dan memperbarui status yang terlihat oleh pihak terkait; tidak membuat saldo keluar.

## 8. Penjemputan dan kapasitas

- **BR-PUP-001:** Pengajuan penjemputan wajib memiliki minimal satu foto, alamat, wilayah, jenis/perkiraan, tanggal pilihan, dan catatan yang diperlukan.
- **BR-PUP-002:** Perkiraan berat dipakai hanya untuk perencanaan kapasitas; nilai setoran memakai penimbangan aktual petugas.
- **BR-PUP-003:** Alamat harus berada dalam area pelayanan aktif pada tanggal pilihan.
- **BR-PUP-004:** Kapasitas harian dapat dibatasi oleh jumlah alamat, perkiraan berat, wilayah, petugas, dan kendaraan sebagai parameter operasional.
- **BR-PUP-005:** Penghitungan kapasitas memasukkan pengajuan pada status yang memesan slot dan mengecualikan ditolak/dibatalkan/kedaluwarsa.
- **BR-PUP-006:** Pemeriksaan dan reservasi kapasitas dilakukan atomik agar permintaan bersamaan tidak melampaui batas.
- **BR-PUP-007:** Bila tanggal penuh, pengajuan tidak ditempatkan pada tanggal itu dan sistem menawarkan tanggal aktif terdekat yang memenuhi kebijakan.
- **BR-PUP-008:** Penolakan wajib beralasan dan menjadi endpoint tanpa penjadwalan, penimbangan, transaksi, atau saldo masuk.
- **BR-PUP-009:** Status valid: `menunggu_pemeriksaan → diterima → dijadwalkan → menuju_lokasi → dijemput → selesai`; endpoint lain `ditolak` dan `dibatalkan`.
- **BR-PUP-010:** Status `selesai` hanya dapat dibuat setelah transaksi hasil penimbangan berhasil final.

## 9. Pencairan tunai

- **BR-WDR-001:** Nominal pencairan berupa rupiah integer positif, memenuhi minimum aktif, dan tidak melebihi saldo tersedia.
- **BR-WDR-002:** Pengajuan sah langsung membuat hold dengan nominal sama.
- **BR-WDR-003:** Permission `withdrawal.approve` terpisah dari `withdrawal.pay`; UI, policy, dan service harus memeriksa keduanya.
- **BR-WDR-004:** Admin approver menetapkan keputusan; penolakan wajib beralasan dan melepas hold.
- **BR-WDR-005:** Payer hanya dapat melihat/membayar pengajuan disetujui atau siap diambil dalam scope penugasannya.
- **BR-WDR-006:** Verifikasi penerima dan bukti pembayaran wajib sebelum status `sudah_dibayar`.
- **BR-WDR-007:** Saldo keluar dibuat setelah uang diserahkan, bersamaan dengan penyelesaian dan penutupan hold.
- **BR-WDR-008:** Pembayaran idempotent; satu pengajuan tidak dapat dibayar dua kali.
- **BR-WDR-009:** Status valid: `menunggu_verifikasi → disetujui → siap_diambil → sudah_dibayar`; endpoint `ditolak`, `dibatalkan`, `kedaluwarsa`.

## 10. Penukaran sembako

- **BR-GRC-001:** Paket aktif memiliki nama, isi deskriptif, nilai rupiah, foto opsional, periode, dan status; sistem tidak mengelola jumlah stok rinci.
- **BR-GRC-003:** Pengajuan saldo hanya sah bila paket aktif dan saldo cukup; sistem membuat hold sebesar nilai snapshot paket.
- **BR-GRC-004:** Ketersediaan diperiksa manual sebelum persetujuan dan dicatat sebagai keputusan operasional, bukan mutasi stok.
- **BR-GRC-005:** Permission `grocery.approve` terpisah dari `grocery.handover`.
- **BR-GRC-006:** Penolakan/tidak tersedia wajib beralasan, melepas hold, dan berhenti tanpa penyiapan atau penyerahan.
- **BR-GRC-007:** Verifikasi penerima dan bukti penyerahan wajib sebelum selesai.
- **BR-GRC-008:** Saldo keluar dibuat setelah paket diserahkan, atomik dengan penutupan hold; penyerahan idempotent.
- **BR-GRC-009:** Status valid: `menunggu_verifikasi → disetujui → sedang_disiapkan → siap_diambil → selesai`; endpoint `ditolak`, `dibatalkan`, `kedaluwarsa`.

## 11. Pengumuman dan WhatsApp manual

- **BR-ANN-001:** Pengumuman memiliki audiens, periode publikasi, status, dan pelaku publikasi.
- **BR-ANN-002:** Konten ditampilkan sebagai teks/HTML tersanitasi dan tidak dapat menjalankan script.
- **BR-ANN-003:** Pengumuman di luar periode atau nonaktif tidak ditampilkan pada kanal sasaran.
- **BR-WA-001:** WhatsApp hanya dibuka melalui tautan `wa.me` dengan template terenkode; aplikasi tidak mengirim pesan.
- **BR-WA-002:** Tombol dan status hanya menyatakan “Buka WhatsApp” atau “Salin pesan”, tidak pernah “terkirim” atau “terkirim otomatis”.
- **BR-WA-003:** Pengguna harus meninjau dan menekan kirim di aplikasi WhatsApp.
- **BR-WA-004:** Template menggunakan data minimal, tidak memuat saldo lengkap, identitas sensitif, secret, atau URL privat tanpa proteksi.
- **BR-WA-005:** Kegagalan membuka WhatsApp tidak mengubah status proses bisnis.

## 12. Target dan layanan keliling

- **BR-TGT-001:** Target memiliki jenis/kelompok sampah, berat sasaran dalam satuan baku, periode, tujuan, status, dan pengaturan visibilitas.
- **BR-TGT-002:** Periode mulai harus sebelum periode selesai; target aktif tidak boleh memiliki definisi ambigu pada scope yang sama.
- **BR-TGT-003:** Progres hanya berasal dari berat bersih detail transaksi final pada scope target.
- **BR-TGT-004:** Koreksi dan reversal memperbarui progres bersih; transaksi draf tidak dihitung.
- **BR-TGT-005:** Target aktif dihitung saat data sah berubah; akhir periode menutup target dan menyimpan ringkasan yang dapat direproduksi.
- **BR-TGT-006:** Publik hanya melihat agregat yang disetujui, bukan kontribusi individu.
- **BR-MOB-001:** Layanan keliling adalah titik layanan terjadwal per RT/RW, bukan penjemputan individual.
- **BR-MOB-002:** Jadwal wajib memiliki titik, wilayah yang mengikuti hierarki desa, dusun, RW, dan RT, waktu, petugas, jenis diterima, kapasitas, serta status.
- **BR-MOB-003:** Jadwal tidak boleh berbenturan pada petugas atau titik dalam waktu yang sama. Kapasitas berlaku pada jadwal dan membatasi penerimaan layanan keliling tersebut.
- **BR-MOB-004:** Transaksi hanya dapat ditandai sebagai layanan keliling ketika jadwal berstatus dibuka dan petugas berada dalam penugasan.
- **BR-MOB-005:** Setoran keliling mengikuti seluruh aturan setoran langsung, harga snapshot, ledger, bukti, dan idempotensi.
- **BR-MOB-006:** Penutupan layanan membuat rekap; perubahan jadwal aktif harus terlihat pada informasi jadwal yang digunakan warga dan petugas.

## 13. Estimasi dan edukasi

- **BR-EST-001:** Estimasi memakai jenis, perkiraan berat, dan harga aktif saat perhitungan.
- **BR-EST-002:** Hasil estimasi tidak membuat transaksi, mutasi, hold, reservasi harga, atau jaminan nilai akhir.
- **BR-EST-003:** Penafian bahwa nilai akhir mengikuti berat aktual dan harga transaksi wajib tampil bersama hasil.
- **BR-EST-004:** Input invalid atau harga tidak tersedia menghasilkan panduan/edukasi, bukan nilai nol yang dapat disalahartikan.

## 14. QR verifikasi dan statistik publik

- **BR-QRV-001:** QR bukti berisi token acak berentropi tinggi, bukan ID berurutan atau data pribadi.
- **BR-QRV-002:** Token unik, dapat dinonaktifkan/dirotasi, dan aksesnya dibatasi laju.
- **BR-QRV-003:** Halaman publik hanya menampilkan nomor bukti, tanggal, total berat, nilai, dan status validitas yang diizinkan.
- **BR-QRV-004:** Saldo, alamat, nomor identitas, telepon lengkap, foto privat, dan catatan internal tidak ditampilkan.
- **BR-QRV-005:** Transaksi nonfinal, dibalik, atau token tidak valid tidak boleh dinyatakan sah.
- **BR-PUB-001:** Statistik internal RT/RW menggunakan transaksi final bersih setelah koreksi/reversal.
- **BR-PUB-002:** Definisi nasabah aktif, periode, wilayah, dan jenis dominan harus konsisten di laporan dan dashboard.
- **BR-PUB-003:** Tampilan agregat internal tetap tunduk pada permission dan scope wilayah.
- **BR-PUB-004:** Data dengan jumlah subjek terlalu kecil untuk aman digabung atau disembunyikan menurut ambang konfigurasi privasi.
- **BR-PUB-005:** Statistik publik memakai allowlist metrik dan dimensi; tidak ada drill-down ke warga.
- **BR-PUB-006:** Statistik publik tidak memuat nama, nomor nasabah, alamat, telepon, saldo, foto, atau histori individu.
- **BR-PUB-007:** Total plastik hanya berasal dari kategori/jenis yang diklasifikasikan plastik pada transaksi final bersih.
- **BR-PUB-008:** Cache publik dipisahkan dari cache respons terautentikasi dan tidak menyimpan data privat.
- **BR-PUB-009:** Data sampah plastik hanya menjadi informasi pendukung pemanfaatan lanjutan paving block; sistem tidak menghitung atau mengelola produksi.

## 15. Laporan dan audit

- **BR-RPT-001:** Laporan memakai definisi metrik dan zona waktu yang sama dengan dashboard.
- **BR-RPT-002:** Scope record diterapkan sebelum agregasi dan ekspor.
- **BR-RPT-003:** Tampilan web dan Excel untuk filter yang sama menghasilkan angka yang sama.
- **BR-RPT-004:** Excel menetralkan nilai yang dapat dieksekusi sebagai formula.
- **BR-RPT-005:** File ekspor bersifat privat, memiliki masa berlaku, dan akses/unduh dicatat.
- **BR-AUD-001:** Audit mencatat pelaku, waktu, aksi, objek, korelasi, serta nilai lama/baru yang relevan dan aman.
- **BR-AUD-002:** Login, perubahan akses, master harga, transaksi, koreksi, ledger, status, approve, pay, handover, dan ekspor wajib diaudit.
- **BR-AUD-003:** Audit append-oriented dan tidak dapat diubah atau dihapus melalui fungsi operasional biasa.
- **BR-AUD-005:** Password, token, secret, cookie, dan isi file sensitif tidak boleh masuk audit/log.
- **BR-AUD-006:** Aksi keuangan yang mewajibkan audit harus menyimpan audit dalam transaksi yang sama atau gagal seluruhnya.
## 16. PWA, file, dan ruang lingkup

- **BR-PWA-001:** PWA boleh memasang manifest, ikon, shell, dan aset terversi.
- **BR-PWA-002:** Cache offline hanya untuk halaman informasi umum yang di-allowlist.
- **BR-PWA-003:** Profil, saldo, transaksi, notifikasi, pengajuan, token, file privat, dan respons terautentikasi tidak boleh dicache untuk penggunaan offline.
- **BR-PWA-004:** Aksi keuangan dan perubahan data selalu memerlukan koneksi; tidak ada antrean transaksi offline.
- **BR-PWA-005:** Perubahan versi menginvalidasi cache lama secara terkendali.
- **BR-FIL-001:** File disimpan di filesystem privat atau object storage kompatibel S3; database hanya menyimpan metadata dan path/key.
- **BR-FIL-002:** File privat diberikan melalui route terotorisasi atau signed URL berumur terbatas.
- **BR-SCP-001:** Paving block hanya disebut sebagai tujuan pemanfaatan lanjutan data plastik.
- **BR-SCP-002:** Sistem tidak memiliki formula, batch produksi, suhu, uji kuat tekan, stok produk jadi, distribusi, atau biaya produksi paving block.

## 17. Hubungan dokumen

- Implementasi izin: [PERMISSIONS.md](PERMISSIONS.md)
- Kontrak data: [DATA_MODEL.md](DATA_MODEL.md)
- Validasi input dan status: [VALIDATION.md](VALIDATION.md)
- Kontrol keamanan: [SECURITY.md](SECURITY.md)
- Skenario pembuktian: [TEST_PLAN.md](TEST_PLAN.md)
- Prosedur manusia: [OPERATIONS.md](OPERATIONS.md)
