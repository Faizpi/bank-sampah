# Roadmap Pengembangan

## Baseline Tunggal

Seluruh fitur yang tercantum pada proposal dan flowchart yang telah disetujui merupakan satu baseline pengembangan. Semua fitur wajib diselesaikan dalam ruang lingkup yang sama.

Status baseline: `locked`.

Perubahan membutuhkan change request, analisis dampak, persetujuan pengelola, pembaruan dokumen, dan pencatatan di `CHANGELOG.md`.

## Urutan Implementasi Teknis

Urutan berikut adalah urutan pengerjaan, bukan pembagian ruang lingkup.

### Fondasi

- Laravel 13, PHP 8.5, Livewire 4, Tailwind CSS 4.1+, Filament 5, Pest 4.
- MariaDB, autentikasi, role/permission, audit, penyimpanan privat, dan design system.
- Konfigurasi test, formatting, static analysis, dan build aset.

### Akun dan master data

- Registrasi, verifikasi, login, perubahan kata sandi mandiri dari profil, dan perubahan berbantuan langsung oleh admin terverifikasi hanya bagi pengguna yang lupa kata sandi dan tidak dapat login.
- Warga tanpa smartphone dan kartu QR.
- Pengguna, role, permission, wilayah.
- Jenis, kategori, satuan, kondisi, foto, dan harga sampah.
- Jadwal, pengumuman, dan edukasi kontekstual.

### Transaksi dan saldo

- Setoran langsung multi-item.
- Snapshot harga.
- Ledger saldo masuk, saldo keluar, dan saldo tertahan.
- Koreksi, pembalikan, bukti transaksi, dan riwayat koreksi warga.
- Pencegahan transaksi ganda.

### Layanan warga

- Penjemputan dengan kapasitas harian dan alternatif tanggal.
- Pencairan tunai.
- Penukaran sembako sederhana tanpa stok terperinci.
- Estimasi nilai sebelum setor.
- Notifikasi, pengingat, dan WhatsApp manual.

### Program dan transparansi

- Target pengumpulan desa.
- Bank Sampah Keliling per RT/RW.
- QR verifikasi bukti.
- Partisipasi RT/RW.
- Statistik publik desa.
- Dashboard warga, tugas petugas hari ini, dan dashboard admin.

### Pengawasan dan peluncuran

- Laporan web, CSV, Excel, dan PDF.
- Audit log.
- Rekonsiliasi harian.
- Pengujian keamanan, aksesibilitas, responsif, UAT, deployment, dan pelatihan.

## Di Luar Baseline

- WhatsApp gateway otomatis.
- Transfer bank atau dompet digital.
- Integrasi timbangan digital.
- Redis, Horizon, WebSocket, dan worker permanen.
- Aplikasi native.
- AI klasifikasi foto.
- Sistem produksi paving block.

Fitur di luar baseline hanya dapat ditambahkan melalui change request baru setelah sistem yang disetujui selesai dan diterima.
