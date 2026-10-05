<p align="center">
  <img src="./public/images/landing/mascot-6.png" width="160" alt="Maskot Bank Sampah Digital Sindangheula">
</p>

# Bank Sampah Digital Sindangheula

Website layanan bank sampah Desa Sindangheula untuk mencatat setoran, saldo, penjemputan, pencairan tunai, dan penukaran sembako.

[Alur pengguna](docs/WEB_WORKFLOW.md) · [Dokumentasi](docs/README.md) · [Pengujian](docs/TEST_PLAN.md) · [Deployment](docs/DEPLOYMENT.md)

> Pengembangan web aktif. Pengujian lokal tidak menggantikan UAT dan persetujuan rilis. Bot Telegram belum diimplementasikan, WhatsApp tidak digunakan, dan pencairan hanya tunai.

## Dibuat untuk layanan desa

| Pengguna | Pekerjaan utama |
| --- | --- |
| Warga | Melihat saldo/riwayat, kartu nasabah, dan mengajukan pickup, pencairan, atau sembako. |
| Petugas | Identifikasi warga, timbang/setoran, pickup yang ditugaskan, layanan keliling, dan serah-terima sembako sesuai area. |
| Bendahara | Memverifikasi penerima dan mencatat pembayaran tunai yang ditugaskan. |
| Admin | Verifikasi warga, harga/master, persetujuan dan penugasan, jadwal, serta laporan. |
| Superadmin | Kewenangan admin ditambah pengelolaan akses, rekonsiliasi berizin, dan Health baca-saja. |

Pelayanan berbantuan tetap tersedia untuk warga tanpa smartphone.

## Alur yang lebih ringkas

- Alamat pickup diisi awal dari profil; area tunggal digunakan otomatis.
- Admin menerima sekaligus menjadwalkan pickup dalam satu formulir.
- Admin menyetujui sekaligus menetapkan bendahara dalam satu formulir.
- Ringkasan setoran dan pembayaran tetap terlihat sebelum satu konfirmasi akhir.
- Petugas cukup menandai paket sembako siap, lalu melakukan serah-terima terverifikasi.
- Ringkasan status warga dilengkapi rincian tahapan yang dapat dibuka bila diperlukan.
- Kartu antrean membuka daftar terfilter; profil dan tugas pengguna dikelola dalam satu formulir.

## Integritas transaksi

Saldo tersedia dihitung dari mutasi masuk dikurangi mutasi keluar dan hold aktif. Hold belum merupakan pengeluaran. Pembayaran atau penyerahan membuat mutasi keluar dan menutup hold secara atomik.

Setoran bernilai tinggi menunggu pemeriksa berbeda; pickup belum selesai selama setorannya belum final. Bukti tersimpan privat. Aksi finansial memeriksa izin, pemilik/penugasan, status, area, dan idempotensi di server. Admin operasional tidak dapat membuat atau mengambil alih akun superadmin.

## Teknologi

Laravel 13, PHP 8.3+, MySQL 8.0.30-compatible/InnoDB, Blade, Livewire 4, Filament 5, Tailwind CSS, dan Vite. Node.js diperlukan untuk build aset, bukan runtime web produksi.

## Menjalankan secara lokal

Siapkan PHP 8.3+, Composer 2, Node.js/npm, dan MySQL lokal (misalnya Laragon). Gunakan database lokal sendiri, bukan database produksi.

1. Jalankan `composer install` dan `npm ci --ignore-scripts`.
2. Salin `.env.example` menjadi `.env`, lalu isi koneksi database lokal dan URL aplikasi.
3. Jalankan `php artisan key:generate`, lalu `php artisan migrate` pada database lokal tersebut.
4. Jalankan `npm run build`.
5. Arahkan document root Laragon ke `public/`, atau gunakan `php artisan serve`.

Jangan mengunggah `.env`, data lokal, log, atau kredensial ke GitHub. Akun dan data demo hanya untuk lingkungan uji.

## Pengujian

```bash
composer test
composer analyse
php vendor/bin/pint --test
npm run build
node --test tests/browser/support/isolated-environment.test.mjs
```

Tes otomatis menggunakan SQLite memori dan storage sementara terpisah. Runner menolak database operasional; pengujian MySQL hanya boleh memakai database disposable bernama `bank_sampah_test` atau suffix-nya.

Uji browser juga memerlukan URL loopback dan token yang memverifikasi identitas database server. Lihat [rencana pengujian](docs/TEST_PLAN.md) dan [hasil validasi lokal](docs/VALIDATION_2026-10-05.md). Hasil SQLite tidak membuktikan seluruh perilaku locking MySQL.

## Dokumentasi dan batas lingkup

- [Workflow warga dan pengurus](docs/WEB_WORKFLOW.md)
- [Aturan bisnis](docs/BUSINESS_RULES.md)
- [Role dan izin](docs/PERMISSIONS.md)
- [Keamanan](docs/SECURITY.md)
- [Keputusan terbaru](docs/DECISIONS.md)
- [Catatan perubahan](docs/CHANGELOG.md)
- [Deployment dan rollback](docs/DEPLOYMENT.md)

Penimbangan/finalisasi setoran serta persiapan/serah-terima sembako tetap melalui web. Integrasi Telegram merupakan tahap terpisah setelah workflow web disepakati dan diuji; repository ini belum menyediakan bot.

Lisensi metadata package: MIT. Distribusi mengikuti kebijakan lisensi proyek.
