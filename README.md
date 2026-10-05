<p align="center">
  <img src="./public/images/landing/mascot-6.png" width="160" alt="Maskot Bank Sampah Digital Sindangheula">
</p>

# Bank Sampah Digital Sindangheula

Website mobile-first untuk setoran, saldo rupiah, penjemputan, pencairan tunai, dan penukaran sembako Desa Sindangheula.

[Workflow](docs/WEB_WORKFLOW.md) · [Dokumentasi](docs/README.md) · [Pengujian](docs/TEST_PLAN.md) · [Validasi cutover](docs/VALIDATION_CUTOVER_2026-10-05.md)

## Empat peran dan tugas yang jelas

| Peran | Tugas utama |
| --- | --- |
| Warga | Menggunakan akun/smartphone sendiri; saldo, kartu, riwayat, dan pengajuan layanan. |
| Petugas | Setoran, pickup, layanan keliling, dan sembako pada area tugas aktif. |
| Bendahara | Mengambil dan mencatat pembayaran tunai searea; laporan pembayaran sendiri. |
| Admin | Akun, wilayah, master, laporan, audit, koreksi resmi, dan pengecualian. |

Satu akun hanya satu peran. Pengurus tidak merangkap tugas. Admin bukan pelaksana fisik rutin.

## Alur yang ringkas

- Semua setoran langsung final setelah ringkasan berat/nilai diperiksa.
- Petugas/Bendahara mengambil tugas sendiri, tanpa persetujuan Admin rutin.
- Foto lampiran rutin opsional; bila dikirim tetap tervalidasi dan privat.
- Pembayaran hanya tunai penuh, diambil ketika uang fisik tersedia. Tidak ada transfer, pembayaran sebagian, atau tombol tunda kas.
- Sembako dapat langsung diserahkan; Paket siap diambil adalah pilihan, bukan tahap wajib.
- Hold belum merupakan pengeluaran. Pelepasan klaim mempertahankan hold; pembatalan sah melepasnya. Setelah klaim, permintaan batal mengunci penyerahan sampai serah fisik dipastikan.
- Tidak ada expiry otomatis pengajuan layanan.

Claim mengikat pelaksana sebelum uang/barang berpindah. Setelah serah fisik, satu pencatatan atomik membuat mutasi keluar dan kuitansi. Respons hilang tidak boleh memicu serah fisik kedua; ketidakpastian masuk jalur exception Admin beralasan dan terautentikasi ulang.

Bot Telegram belum diimplementasikan dan WhatsApp tidak digunakan. Penimbangan serta serah-terima sembako tetap melalui web. Tes otomatis belum menggantikan UAT peserta desa nyata.

## Teknologi

Laravel 13, PHP 8.3+, MySQL 8.0.30-compatible/InnoDB, Livewire 4, Filament 5, Tailwind CSS, dan Vite. Node.js diperlukan untuk build/pengujian, bukan runtime web produksi.

## Menjalankan lokal

1. Siapkan PHP, Composer, Node.js/npm dan MySQL lokal, misalnya Laragon.
2. Jalankan `composer install` dan `npm ci --ignore-scripts`.
3. Salin `.env.example` ke `.env`; isi koneksi lokal dan URL, lalu jalankan `php artisan key:generate`.
4. Pada database lokal kosong, jalankan `php artisan migrate --seed`, kemudian `npm run build`.
5. Document root Laragon harus `public/`; alternatifnya `php artisan serve`.

Seed lokal membuat tepat empat akun demo (username `warga`, `petugas`, `bendahara`, `admin`), area tugas, harga, dan paket; tidak ada transaksi/saldo dummy. Kata sandi demo default: `Dev#Sindangheula2026`, kecuali dikonfigurasi sendiri. Akun ini hanya untuk local/testing, bukan akun produksi.

### Reset baseline dummy yang sudah ada

Simpan backup terlebih dahulu. Command ini hanya menerima local/testing, host loopback, tepat database `bank_sampah`, dan root media workspace.

```bash
php artisan app:reset-demo-baseline --confirm-reset-demo --dry-run
php artisan app:reset-demo-baseline --confirm-reset-demo
```

Tanpa dry-run data dan media dummy dihapus. Jangan dipakai untuk data nyata; migration cutover transaksi menolak baseline transaksi yang tidak kosong. Cutover schema tidak dapat di-rollback sebagian. [Panduan rilis/rollback](docs/DEPLOYMENT.md).

## Pengujian

```bash
composer test
composer analyse
php vendor/bin/pint --test
npm run build
npm run test:browser-guard
```

Unit/feature memakai SQLite memori dan storage/cache/sesi terpisah. MySQL/browser hanya memakai database disposable `bank_sampah_test` atau suffix-nya, URL loopback, dan token preflight yang sesuai. Runner browser: `npm run test:browser`; skenario locking nyata: `tests/mysql/cutover-concurrency.php`. Lihat [rencana tes](docs/TEST_PLAN.md) untuk batas bukti.

Proposal dan sembilan flowchart ada di [documents](documents/). Kuitansi/audit adalah jejak sistem, bukan bukti independen serah fisik. [UAT](docs/UAT_EVIDENCE.md) belum boleh diklaim selesai tanpa peserta nyata.

Jangan mengunggah `.env`, token, backup/database, log, atau kredensial nyata ke GitHub. Lisensi metadata package MIT; distribusi mengikuti kebijakan proyek.
