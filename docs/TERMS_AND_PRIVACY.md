# Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0

Dokumen publik ini adalah kontrak kanonis untuk pendaftaran dan penggunaan Bank Sampah Digital Sindangheula. Dokumen tersedia untuk dibaca publik, termasuk sebelum pendaftaran. Ketentuan penerimaan akun dan persetujuan layanan berbantuan dibedakan secara tegas.

## Ketentuan Operasional v1.0

### 1. Ruang layanan

Bank Sampah Digital Sindangheula membantu pencatatan layanan bank sampah, termasuk akun warga, setoran, saldo, pengajuan layanan, informasi, dan bukti transaksi. Penggunaan layanan mengikuti alur, status, dan kewenangan yang berlaku pada sistem.

### 2. Pendaftaran dan penerimaan ketentuan

1. Pendaftar wajib menyatakan persetujuan secara afirmatif terhadap Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0 saat mendaftar.
2. Versi yang ditampilkan dan berlaku saat ini adalah v1.0. Sistem menyimpan versi yang saat ini efektif pada `users.terms_version` dan waktu persetujuan yang dicatat server pada `users.terms_accepted_at`, serta mempertahankan catatan riwayat penerimaan yang hanya dapat ditambahkan per pengguna dan versi.
3. Penerimaan ketentuan bukan verifikasi domisili atau duplikasi, bukan aktivasi akun, bukan autentikasi, dan bukan login otomatis.
4. Setelah pendaftaran yang valid, akun tetap berstatus `menunggu_verifikasi`. Hanya keputusan verifikasi admin yang dapat mengaktifkan akun.
5. Ketentuan dipublikasikan untuk berlaku ke depan. Riwayat penerimaan versi sebelumnya tetap dipertahankan.
6. Persetujuan ulang hanya dapat diminta jika ada proses produk yang telah disetujui dan didokumentasikan. Permintaan itu tidak mengubah status, aktivasi, atau sesi akun secara otomatis.

### 3. Penggunaan akun dan layanan

1. Pengguna memberikan data yang benar sesuai formulir dan menjaga kata sandinya sendiri.
2. Akses akun, data, dan tindakan dibatasi oleh peran serta cakupan record yang berwenang.
3. Status transaksi dan layanan mengikuti proses operasional. Persetujuan, pembayaran, penyerahan, dan perubahan saldo tidak dapat dianggap terjadi hanya dari pengisian formulir atau notifikasi.
4. WhatsApp hanya disediakan sebagai tautan manual `wa.me`. Pengguna meninjau isi pesan dan menekan kirim sendiri di WhatsApp. Sistem tidak mengirim WhatsApp otomatis dan tidak menyatakan pesan telah terkirim.

### 4. Layanan berbantuan

Persetujuan untuk layanan berbantuan terpisah dari penerimaan ketentuan akun. Warga dapat memberi persetujuan untuk bantuan petugas pada layanan tertentu. Persetujuan ini tidak memberi petugas hak meminta, menyimpan, atau mengambil alih kata sandi warga. Warga tetap pemilik record, sedangkan petugas dicatat sebagai pelaksana.

### 5. Perubahan dokumen

Versi baru hanya diterbitkan secara prospektif melalui proses yang disetujui dan didokumentasikan. Dokumen publik harus menampilkan versi yang berlaku saat itu. Riwayat penerimaan yang telah tersimpan tidak dihapus atau ditimpa.

## Kebijakan Privasi Ringkas v1.0

### 1. Data dan tujuan penggunaan

Sistem menggunakan data yang diperlukan untuk:

- verifikasi akun dan pencegahan duplikasi;
- penyediaan layanan bank sampah, termasuk pencatatan transaksi dan saldo;
- pemberitahuan dalam aplikasi serta pembukaan tautan WhatsApp manual atas tindakan pengguna;
- keamanan, pencegahan penyalahgunaan, audit, backup, dan operasi layanan.

### 2. Akses dan keterbukaan terbatas

Akses data diberikan menurut peran dan cakupan record. Pengguna hanya dapat membuka data yang menjadi haknya. Petugas dan pengelola hanya dapat membuka data yang diperlukan untuk tugas dan wewenangnya.

Halaman QR verifikasi dan statistik publik hanya menampilkan informasi terbatas yang tidak mengidentifikasi warga. Statistik publik berbentuk agregat yang diizinkan. Nama, alamat, telepon, saldo, foto privat, dan riwayat individu tidak dibuka melalui halaman publik.

### 3. Media, backup, dan keamanan

Foto, bukti, ekspor, dan media privat dilindungi dengan akses terotorisasi. Backup juga diperlakukan sebagai data terlindungi. Kata sandi, token, dan secret tidak dicatat dalam bentuk plaintext pada log atau audit.

### 4. Retensi

Data dan catatan operasional disimpan sesuai kebutuhan operasional atau kebutuhan hukum yang telah disetujui. Dokumen ini tidak menjanjikan durasi retensi tertentu.

### 5. Hubungan dengan ketentuan

Penerimaan Ketentuan Operasional v1.0 mencakup pengakuan bahwa Kebijakan Privasi Ringkas v1.0 telah tersedia untuk dibaca. Persetujuan layanan berbantuan tetap merupakan persetujuan terpisah untuk tindakan bantuan yang relevan.

## Aksesibilitas dan kontrol pendaftaran

Dokumen ini harus tersedia pada halaman publik, footer, dan halaman pendaftaran tanpa mewajibkan login. Formulir pendaftaran menampilkan kontrol kosong yang belum dicentang dengan label jelas, misalnya: “Saya telah membaca dan menyetujui Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0.” Label tersebut tertaut ke dokumen ini.

Pendaftaran tidak dapat diteruskan sebelum kontrol dicentang. Saat persetujuan belum diberikan, form menampilkan kesalahan berlabel yang menjelaskan bahwa persetujuan terhadap Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0 wajib diberikan. Kontrol, label, tautan, dan pesan kesalahan harus dapat diakses dengan keyboard dan pembaca layar.
