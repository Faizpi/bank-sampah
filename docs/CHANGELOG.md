# Changelog

Dokumen ini mencatat perubahan kontrak ruang lingkup. Entri bukan bukti rilis, User Acceptance Testing (UAT), deployment, atau perbaikan defect aplikasi.

## Unreleased

### Changed

- PHP minimum diselaraskan ke `^8.3` sesuai `composer.json`
- Ruang lingkup aktif diselaraskan dengan aplikasi saat ini tanpa menambahkan kapabilitas
- Health ditetapkan sebagai satu-satunya administrasi teknis aktif pada UI
- Perubahan kata sandi tetap terdiri dari perubahan mandiri setelah login dan bantuan admin bagi pengguna yang tidak dapat login
- FL-15 menjelaskan informasi pada halaman terkait dan WhatsApp manual
- FL-36 memvisualisasikan Health baca-saja dengan permission `system.maintenance`
- Aturan target, hierarki wilayah serta kapasitas layanan keliling, dan validasi penjemputan tetap normatif
- Bukti UAT historis dan klaim kesiapan production dihapus dari status aktif
- Navigasi warga dipertegas menjadi `Beranda`, `Kartu Nasabah`, `Layanan`, `Riwayat`, `Akun`; `Riwayat` menaungi setoran, pencairan, dan sembako tanpa menduplikasi tujuan `Setoran`

### Preserved

- Laravel 13, Blade, Livewire 4, Tailwind CSS 4.1+, Filament 5, Pest 4, dan MySQL 8.0.30
- Ledger, hold, idempotensi, koreksi, reversal, separation of duties, media privat, QR terbatas, WhatsApp manual, serta PWA dengan cache publik terbatas
- Pelayanan warga tanpa smartphone, setoran, penjemputan, pencairan, penukaran sembako berbasis saldo, pengumuman, jadwal layanan keliling, estimasi, laporan yang tersedia, dan audit append-only

## Aturan entri

Gunakan kategori `Added`, `Changed`, `Fixed`, `Security`, `Deprecated`, dan `Removed`. Entri fitur harus merujuk requirement dan keputusan terkait. Jangan menulis klaim perbaikan defect tanpa perubahan aplikasi dan bukti verifikasi yang sesuai.
