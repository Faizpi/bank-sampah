# Bank Sampah Digital

Bank Sampah Digital adalah aplikasi web untuk mengelola bank sampah. Warga bisa menabung sampah, memantau saldo, mengajukan penjemputan, dan mencairkan saldo. Pengelola bisa mencatat setoran, memverifikasi transaksi, dan melihat laporan.

Aplikasi ini dibuat mobile-first, jadi nyaman dipakai lewat HP saat petugas berada di lapangan.

## Siapa saja penggunanya

**Warga** — mendaftar akun, melihat saldo dan riwayat transaksi, mengajukan penjemputan sampah, mengajukan pencairan saldo, memakai kartu nasabah ber-QR, dan menukar saldo dengan paket sembako.

**Petugas** — mencari nasabah, mencatat setoran sampah, menangani tugas penjemputan, dan melayani penukaran di layanan keliling.

**Bendahara** — memverifikasi dan mencatat pencairan saldo, mengunggah bukti pembayaran, serta menyusun laporan.

**Admin** — mengelola data warga dan pengguna, wilayah (dusun, RW, RT), jenis sampah dan harganya, pengumuman, program, hingga audit dan rekonsiliasi.

**Pengunjung** — tanpa perlu masuk, siapa pun bisa melihat katalog sampah, daftar harga, jadwal layanan keliling, pengumuman, dan statistik.

## Alur singkat

1. Warga menyetor sampah yang sudah dipilah.
2. Petugas mencatat jenis, kondisi, dan berat sampah, lalu transaksi disimpan sebagai bukti.
3. Saldo warga bertambah sesuai harga yang berlaku saat itu.
4. Warga bisa mengajukan penjemputan atau mencairkan saldo kapan saja.

Setiap transaksi tercatat rapi dan bisa ditelusuri kembali.

## Menjalankan di komputer sendiri

Aplikasi ini memakai Laravel dan MySQL, dan paling mudah dijalankan lewat [Laragon](https://laragon.org).

```bash
git clone https://github.com/Faizpi/bank-sampah.git
cd bank-sampah
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

Atur `.env` agar menunjuk ke database lokal, lalu arahkan virtual host ke folder `public`. Aplikasi akan terbuka di:

```text
http://bank-sampah-skripsi.test
```

## Akun percobaan

Setelah data awal dimuat, tersedia akun contoh untuk mencoba semua peran. Seluruh akun memakai kata sandi yang sama:

```text
Banten123
```

Ganti kata sandi ini sebelum dipakai sungguhan.
