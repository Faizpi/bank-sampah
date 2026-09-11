# Roadmap Pengembangan

Roadmap ini mencatat urutan pengembangan dan evaluasi ruang lingkup aktif. Dokumen ini tidak menjanjikan kapabilitas yang belum tersedia dan tidak menyatakan aplikasi siap produksi.

## Ruang lingkup aktif

Ruang lingkup aktif mencakup fungsi inti yang dijelaskan pada [PRODUCT.md](PRODUCT.md), [REQUIREMENTS.md](REQUIREMENTS.md), dan [BUSINESS_RULES.md](BUSINESS_RULES.md). Aturan target yang masih menjadi sasaran produk, hierarki wilayah serta kapasitas layanan keliling, dan validasi penjemputan tetap normatif. Dokumentasi tidak menjadikan defect pada area tersebut sebagai perilaku yang diterima.

## Prioritas pengembangan

### Fondasi

- Laravel 13 dengan PHP 8.3 atau lebih baru sesuai `composer.json`
- Blade, Livewire 4, Tailwind CSS 4.1+, Filament 5, dan Pest 4
- MySQL 8.0.30, autentikasi, role atau permission, audit, media privat, dan design system

### Akun dan master data

- Registrasi, verifikasi, login, perubahan kata sandi mandiri, serta bantuan admin bagi pengguna yang tidak dapat login
- Warga tanpa smartphone dan kartu QR
- Pengguna, wilayah, jenis sampah, kondisi, satuan, harga, dan edukasi

### Transaksi dan layanan

- Setoran langsung multi-item, snapshot harga, ledger, hold, koreksi, reversal, dan bukti
- Penjemputan dengan foto wajib, validasi area, kapasitas harian, alternatif tanggal, penimbangan aktual, dan penyelesaian setelah setoran final
- Pencairan tunai dan penukaran sembako sederhana tanpa stok rinci
- Estimasi nilai, pengumuman, dan WhatsApp manual

### Program dan pengawasan

- Jadwal Bank Sampah Keliling pada hierarki desa, dusun, RW, dan RT, dengan validasi benturan serta kapasitas per jadwal
- Data agregat pengumpulan dan statistik yang tersedia
- QR verifikasi bukti, laporan yang tersedia, dan audit log
- Health sebagai satu-satunya administrasi teknis aktif

## Di luar ruang lingkup aktif

- Bantuan sembako gratis
- Push notification dan notification center yang dijanjikan sebagai kanal aktif
- Reset kata sandi publik berbasis token, email, SMS, atau WhatsApp
- Queue database, worker asinkron, dan retry otomatis
- WhatsApp gateway otomatis
- Transfer bank, dompet digital, timbangan digital, Redis, Horizon, WebSocket, aplikasi native, AI klasifikasi foto, dan produksi paving block

Penambahan kapabilitas memerlukan change request, analisis dampak, persetujuan pengelola, pembaruan dokumen, dan bukti implementasi.
