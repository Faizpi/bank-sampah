# Product Definition

## Identitas

- Nama resmi: Sistem Informasi Bank Sampah Digital Desa Sindangheula.
- Nama aplikasi: Bank Sampah Digital Sindangheula.
- Lokasi: Desa Sindangheula, Kecamatan Pabuaran, Kabupaten Serang, Provinsi Banten.
- Bentuk: web application mobile-first dan PWA installable dengan cache terbatas untuk halaman informasi umum.

## Visi

Menyediakan layanan bank sampah yang mudah digunakan, transparan, inklusif, dan dapat dikelola secara berkelanjutan oleh masyarakat desa.

## Masalah yang Diselesaikan

- Data nasabah dan petugas belum terpusat.
- Perhitungan nilai sampah berisiko salah.
- Warga sulit melihat saldo dan riwayat.
- Penjemputan, pencairan, dan penukaran belum memiliki alur terdokumentasi.
- Perubahan harga dan koreksi transaksi sulit ditelusuri.
- Laporan dan audit memerlukan proses manual.
- Warga tanpa smartphone berisiko tertinggal dari pelayanan digital.

## Pengguna

### Warga dengan smartphone

Membutuhkan akses mudah ke harga, saldo, riwayat, penjemputan, pencairan, sembako, jadwal, dan informasi layanan.

### Warga tanpa smartphone

Menerima pelayanan berbantuan dari petugas menggunakan nomor/kartu QR dan bukti cetak tanpa kehilangan hak atas transparansi data.

### Petugas

Membutuhkan transaksi cepat, scan QR, daftar tugas hari ini, pengelolaan penjemputan, pembayaran, dan penyerahan.

### Bendahara

Membutuhkan daftar pencairan yang telah disetujui, verifikasi penerima, pencatatan pembayaran, dan bukti.

### Admin

Mengelola pengguna, wilayah, harga, transaksi, saldo, penjemputan, pencairan, sembako, jadwal, target, laporan, dan audit.

### Superadmin

Mengelola konfigurasi teknis, role, permission, backup, dan pemeliharaan tanpa hak mengubah saldo di luar mekanisme koreksi.

### Pemerintah desa

Membutuhkan laporan agregat untuk evaluasi partisipasi, pengumpulan sampah, dan data plastik sebagai pendukung perencanaan bahan baku paving block. Sistem tidak mengelola produksi paving block.

## Nilai Produk

- Transparansi saldo rupiah.
- Pelayanan yang dapat diakses melalui HP dan laptop.
- Pencatatan transaksi yang dapat diaudit.
- Proses petugas yang cepat dan terarah.
- Pelayanan inklusif bagi warga tanpa smartphone.
- Data agregat untuk evaluasi program desa.

## Baseline Fitur Terkunci

- Registrasi dengan penerimaan afirmatif Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0, login, perubahan kata sandi mandiri dari profil, perubahan berbantuan langsung oleh admin terverifikasi hanya bagi pengguna yang lupa kata sandi dan tidak dapat login, serta verifikasi akun.
- Nasabah, petugas, role, permission, dan wilayah dasar.
- Nomor/kartu/QR nasabah.
- Jenis sampah, kategori, harga, riwayat harga, dan edukasi kontekstual dasar.
- Setoran multi-item, snapshot harga, bukti, saldo masuk, dan riwayat.
- Ledger saldo, saldo tersedia, saldo tertahan, saldo keluar, koreksi, dan pembalikan.
- Riwayat koreksi yang dapat dilihat warga.
- Penjemputan dengan foto wajib dan penimbangan aktual.
- Pencairan tunai.
- Paket dan penukaran sembako sederhana tanpa stok terperinci.
- Dashboard warga, tugas petugas hari ini, dan dashboard admin.
- Notifikasi dan pengingat dasar dalam aplikasi.
- Tombol WhatsApp manual dengan template pesan melalui `wa.me`.
- Pelayanan berbantuan bagi warga tanpa smartphone.
- Pengumuman, laporan dasar, ekspor, dan audit log.

### Fitur Program dan Transparansi

- Target pengumpulan desa.
- Bank Sampah Keliling per RT/RW.
- Estimasi nilai sebelum setor.
- QR verifikasi bukti transaksi.
- Partisipasi RT/RW dan statistik publik desa.
- Kapasitas penjemputan dan alternatif tanggal.
- PWA installable dengan cache terbatas untuk informasi umum.

WhatsApp tetap menggunakan pengiriman manual melalui tautan `wa.me`. Integrasi gateway otomatis berada di luar baseline dan hanya dapat ditambahkan melalui change request baru.

## Di Luar Ruang Lingkup

- Produksi, formula, kualitas, stok, distribusi, dan biaya paving block.
- Akuntansi desa menyeluruh.
- Aplikasi native Android/iOS.
- Transaksi offline yang disinkronkan kemudian.
- WebSocket, live tracking, chat, AI pengenalan sampah, Redis, dan Horizon.

## Indikator Keberhasilan

- Setoran menambah saldo tepat satu kali.
- Saldo tidak negatif dan seluruh perubahan dapat ditelusuri.
- Warga dapat memahami saldo dan status tanpa bantuan teknis.
- Petugas dapat menyelesaikan setoran sederhana dengan cepat.
- Pengajuan memiliki status, alasan, bukti, dan pelaksana yang jelas.
- Data warga tidak terbuka kepada pihak yang tidak berwenang.
- Aplikasi berfungsi pada lebar 360 piksel dan browser yang ditetapkan.
