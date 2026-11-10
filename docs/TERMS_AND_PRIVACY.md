# Ketentuan Operasional v2.2 dan Kebijakan Privasi Ringkas v2.2

Dokumen publik ini adalah kontrak kanonis untuk pendaftaran dan penggunaan Bank Sampah Digital Sindangheula. Dokumen tersedia untuk dibaca publik, termasuk sebelum pendaftaran. Dokumen draft ini diselaraskan dengan baseline dummy empat role sebelum penggunaan nyata di desa.

**Batas versi:** v2.2 disiapkan pada 10 November 2026 untuk penambahan pencairan digital simulasi yang disetujui pemilik. v2.1 (28 Oktober 2026) memperkenalkan akses Telegram persisten; v2.0 memperkenalkan kanal Telegram. Versi baru berlaku prospektif bagi pendaftaran baru; riwayat penerimaan versi sebelumnya tidak ditimpa dan tidak ada persetujuan ulang massal otomatis. Pencairan simulasi memerlukan konfirmasi tersendiri dengan versi `payout-sandbox-v1` serta waktu server sebelum setiap pengajuan. Konfigurasi hosting harus diselaraskan sebelum fitur dinyalakan.

## Ketentuan Operasional v2.2

### 1. Ruang layanan

Bank Sampah Digital Sindangheula membantu pencatatan layanan bank sampah, termasuk akun warga, setoran, saldo, pengajuan layanan, informasi, dan bukti transaksi. Penggunaan layanan mengikuti alur, status, dan kewenangan yang berlaku pada sistem.

### 2. Pendaftaran dan penerimaan ketentuan

1. Pendaftar wajib menyatakan persetujuan secara afirmatif terhadap Ketentuan Operasional v2.2 dan Kebijakan Privasi Ringkas v2.2 saat mendaftar.
2. Versi yang ditampilkan dan berlaku saat ini adalah v2.2. Sistem menyimpan versi yang saat ini efektif pada `users.terms_version` dan waktu persetujuan yang dicatat server pada `users.terms_accepted_at`, serta mempertahankan catatan riwayat penerimaan yang hanya dapat ditambahkan per pengguna dan versi.
3. Penerimaan ketentuan bukan verifikasi domisili atau duplikasi, bukan aktivasi akun, bukan autentikasi, dan bukan login otomatis.
4. Setelah pendaftaran yang valid, akun tetap berstatus `menunggu_verifikasi`. Hanya keputusan verifikasi admin yang dapat mengaktifkan akun.
5. Ketentuan dipublikasikan untuk berlaku ke depan. Riwayat penerimaan versi sebelumnya tetap dipertahankan.
6. Persetujuan ulang hanya dapat diminta jika ada proses produk yang telah disetujui dan didokumentasikan. Permintaan itu tidak mengubah status, aktivasi, atau sesi akun secara otomatis.

### 3. Penggunaan akun dan layanan

1. Pengguna memberikan data yang benar sesuai formulir dan menjaga kata sandinya sendiri.
2. Akses akun, data, dan tindakan dibatasi oleh peran serta cakupan record yang berwenang.
3. Status transaksi dan layanan mengikuti proses operasional. Persetujuan, pembayaran, penyerahan, dan perubahan saldo tidak dapat dianggap terjadi hanya dari pengisian formulir atau notifikasi.
4. Informasi layanan tersedia melalui website dan notifikasi dalam aplikasi. WhatsApp tidak digunakan. Telegram tersedia sebagai kanal opsional menurut pasal 6 dan hanya aktif setelah penautan oleh pengguna; kanal ini nonaktif pada sistem sampai diaktifkan sesuai dokumen operasi.

### 4. Layanan mandiri dan serah fisik

Semua Warga menggunakan akun serta smartphone sendiri. Petugas menangani setoran, pickup, dan sembako; Bendahara menangani uang tunai. Pengurus tidak mengajukan layanan atas nama Warga dan tidak meminta kata sandi Warga. Foto rutin opsional; hold belum merupakan pengeluaran. Claim belum berarti uang atau paket diberikan. Status final layanan fisik mengikuti pencatatan setelah serah fisik; jika tidak pasti, transaksi dikunci untuk pemeriksaan Admin. Pencairan digital simulasi mengikuti hasil API terverifikasi dan ketentuan khusus simulasi di bawah, bukan serah fisik.

### 5. Perubahan dokumen

Versi baru hanya diterbitkan secara prospektif melalui proses yang disetujui dan didokumentasikan. Dokumen publik harus menampilkan versi yang berlaku saat itu. Riwayat penerimaan yang telah tersimpan tidak dihapus atau ditimpa.

### 6. Kanal Telegram opsional

1. Layanan bot Telegram bersifat opsional dan hanya dapat diaktifkan melalui penautan yang dimulai dari halaman Profil pada website setelah memasukkan kata sandi akun. Sistem tidak pernah meminta nama pengguna, kata sandi, atau kode apa pun melalui chat Telegram.
2. Satu akun web dapat tertaut dengan paling banyak satu akun Telegram aktif, dan satu akun Telegram dengan paling banyak satu akun web. Penggantian menuntut pemutusan tautan sebelumnya melalui akun pemiliknya; pengurus tidak menautkan akun Warga atas nama Warga.
3. Akses bot tidak memiliki batas waktu otomatis atau batas tidak aktif selama Telegram tetap tertaut, akun aktif, dan hak akses masih sah. Warga, Petugas, serta Bendahara tidak perlu kembali ke website hanya karena lama tidak menggunakan bot. Tombol dan isian percakapan sementara tetap dapat kedaluwarsa; pengguna cukup membuka /menu dan mengisi ulang melalui bot. Status akun, peran, wilayah tugas, kepemilikan, klaim, status transaksi, dan idempotensi tetap diperiksa oleh server. Perubahan data keamanan akun dapat memerlukan verifikasi ulang, bukan pembaruan berkala.
4. Informasi, pengajuan, pencatatan, dan berkas yang tersedia melalui bot mengikuti peran serta status layanan yang berlaku; penimbangan dan penyerahan uang atau paket tetap dilakukan secara fisik. Pesan yang sudah terkirim berada pada riwayat Telegram pengguna dan tidak dapat ditarik oleh sistem.
5. Pengguna dapat memutus tautan dari Profil di website dengan konfirmasi kata sandi. Perintah /keluar pada bot hanya menampilkan petunjuk pemutusan dan tidak memutus tautan dengan sendirinya. Admin yang berwenang dapat mencabut tautan dengan alasan yang dicatat pada audit.

## Kebijakan Privasi Ringkas v2.2

### Pencairan digital (simulasi)

Pencairan digital adalah fitur riset opsional melalui Xendit Test Mode pada database terpisah. Tujuan yang tersedia setelah konfigurasi adalah BCA, Mandiri, BRI, BNI, DANA, dan GoPay. Tidak ada dana riil yang dikirim dan ini bukan layanan pembayaran berizin atau kemitraan resmi dengan pemilik merek. Tunai tetap mengikuti serah fisik Bendahara; hasil digital mengikuti status API terverifikasi tanpa persetujuan rutin Bendahara/Admin.

Pengguna harus memakai data uji. Nama pemilik, nomor rekening/dompet digital uji, nominal, dan referensi dikirim ke Xendit untuk simulasi. Pengisian melalui Telegram juga melewati layanan Telegram. Ringkasan tujuan dan nominal ditampilkan sebelum konfirmasi tersendiri. Server menyimpan tujuan terenkripsi serta catatan persetujuan dan transaksi; laporan/audit tidak menampilkan nomor lengkap atau rahasia. Saldo riset disisihkan saat konfirmasi, didebit sekali ketika simulasi berhasil, dilepas jika gagal final, dan dikembalikan melalui mutasi baru jika hasil dibalik. Hasil belum pasti tetap ditahan untuk pemeriksaan, bukan dianggap sukses.

### 1. Data dan tujuan penggunaan

Sistem menggunakan data yang diperlukan untuk:

- verifikasi akun dan pencegahan duplikasi;
- penyediaan layanan bank sampah, termasuk pencatatan transaksi dan saldo;
- informasi status pada halaman terkait, notifikasi dalam aplikasi, serta kanal Telegram yang ditautkan dan diizinkan pengguna;
- keamanan, pencegahan penyalahgunaan, audit, dan operasi layanan.

### 2. Akses dan keterbukaan terbatas

Akses data diberikan menurut peran dan cakupan record. Pengguna hanya dapat membuka data yang menjadi haknya. Petugas dan pengelola hanya dapat membuka data yang diperlukan untuk tugas dan wewenangnya.

Halaman QR verifikasi dan statistik publik hanya menampilkan informasi terbatas yang tidak mengidentifikasi warga. Statistik publik berbentuk agregat yang diizinkan. Nama, alamat, telepon, saldo, foto privat, dan riwayat individu tidak dibuka melalui halaman publik.

### 3. Media dan keamanan

Foto, bukti, ekspor, dan media privat dilindungi dengan akses terotorisasi. Kata sandi, token, dan secret tidak dicatat dalam bentuk plaintext pada log atau audit.

### 4. Hubungan dengan ketentuan

Penerimaan Ketentuan Operasional v2.2 mencakup pengakuan bahwa Kebijakan Privasi Ringkas v2.2 telah tersedia untuk dibaca. Tidak ada pengajuan transaksi rutin oleh pengurus atas nama Warga.

### 5. Kanal Telegram

1. Data yang dikirim ke Telegram hanya milik akun yang ditautkan: nama dan peran akun, isi yang diminta sendiri melalui bot (saldo, riwayat, pengajuan, tugas sesuai peran), notifikasi yang diizinkan, ringkasan tabungan, pengumuman yang dilanggan, berkas laporan pembayaran milik Bendahara sendiri, serta foto opsional yang dikirim pengguna sendiri.
2. Seluruh pilihan kiriman (informasi transaksi, pengingat, ringkasan tabungan, langganan pengumuman) mati secara default dan hanya aktif setelah dipilih pengguna. Pilihan dapat diubah atau dihentikan kapan saja dari website maupun bot, dan berhenti berlangganan tidak membatalkan pengajuan yang sudah tercatat.
3. Kiriman melewati layanan Telegram sehingga salinan pesan yang sudah terkirim berada pada riwayat percakapan pengguna di Telegram dan tidak dapat dihapus oleh sistem. Antrean kiriman pada server disimpan paling lama tujuh hari dan berkas laporan paling lama 24 jam sejak dibuat, lalu dihapus; pesan yang sudah terkirim dapat tersimpan pada riwayat Telegram.
4. Pencabutan akses dilakukan dari Profil di website dengan konfirmasi kata sandi, atau oleh Admin yang berwenang dengan alasan yang dicatat; seluruh kiriman tertunda kepada tautan yang dicabut dibatalkan.

## Aksesibilitas dan kontrol pendaftaran

Dokumen ini harus tersedia pada halaman publik, footer, dan halaman pendaftaran tanpa mewajibkan login. Formulir pendaftaran menampilkan kontrol kosong yang belum dicentang dengan label jelas, misalnya: “Saya telah membaca dan menyetujui Ketentuan Operasional v2.2 dan Kebijakan Privasi Ringkas v2.2.” Label tersebut tertaut ke dokumen ini.

Pendaftaran tidak dapat diteruskan sebelum kontrol dicentang. Saat persetujuan belum diberikan, form menampilkan kesalahan berlabel yang menjelaskan bahwa persetujuan terhadap Ketentuan Operasional v2.2 dan Kebijakan Privasi Ringkas v2.2 wajib diberikan. Kontrol, label, tautan, dan pesan kesalahan harus dapat diakses dengan keyboard dan pembaca layar.
