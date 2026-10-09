# Validasi data utama 9 Oktober 2026

Pemilik memilih `smssinde_banksampah.sql` sebagai sumber pengguna, alamat/wilayah, sampah, dan harga; mengizinkan penggantian dummy lokal `bank_sampah`. SQL asli tidak diubah/dieksekusi. Hasil ekstraksi privat tidak masuk Git.

## Pengujian yang dijalankan

| Pemeriksaan | Bukti |
|---|---|
| Regresi UI sebelum checkpoint | 32 lulus, 134 assertions |
| Suite lengkap SQLite setelah pemisahan fixture | 1.199 lulus, 1 dilewati khusus Windows, 5.613 assertions |
| Tes seeder baru | 17 lulus, 63 assertions; termasuk dalam suite lengkap |
| MySQL terpilih | 151 lulus, 533 assertions: seeder, cutover, UI, auth, login Admin |
| Konverter literal dan guard browser | 5 tes Node lulus |
| Konkurensi MySQL setelah pemisahan fixture | 4 skenario lulus: Admin terakhir, claim eksklusif, area dicabut pada snapshot lama, saldo terkini |
| PHPStan | 0 error |
| Pint dan whitespace | Lulus |
| Impor data asli di MySQL disposable | Semua jumlah tabel dan hash pengguna cocok, semua QR dapat didekripsi/valid |
| Workflow pada data asli disposable | Setoran 50 kg Aqua Botol memakai Rp1.500/kg; pencairan Rp20.000 dengan claim Bendahara dan retry menyisakan Rp55.000; Admin tidak memperoleh akses fisik rutin |
| Backup sebelum reset | Dump MySQL lokal nonempty; berhasil direstore pada database disposable dengan empat peran/count awal |
| Pemeriksaan database utama setelah reset | 26 akun/20 profil Warga/4 profil pengurus/4 penugasan/5 RW/19 RT/4 jenis/4 harga; counter Admin 2; tidak ada profil Warga pada pengurus; hash dan QR valid |
| Browser utama | Login, pendaftaran, dan harga tampil pada server proyek yang benar; daftar memuat 19 RT dengan wilayah/RW/kampung; empat harga sesuai sumber; tidak ada tombol demo yang tidak cocok |

Subset tidak dijumlahkan menjadi jumlah tes unik. Tes otomatis tetap menolak `bank_sampah` sebagai target; tes MySQL memakai `bank_sampah_test_master20261009`, suffix `_suite` dan `_concurrency`. Uji restore backup memakai `bank_sampah_test_backup20261009`. Transaksi smoke pada dataset asli di-rollback; database utama tidak diberi saldo/transaksi buatan.

## Perubahan lokal yang selesai

Baseline utama sebelum reset: empat akun contoh, satu RT, empat harga, satu paket contoh, nol transaksi. Baseline tersebut diganti melalui reset berkonfirmasi dengan sumber tervalidasi yang dipegang dalam memori; username/password sumber dipertahankan. Transaksi lama dari dump sumber tidak dibawa. Paket sembako contoh juga tidak dibawa karena di luar subset yang diminta.

Backup privat berada di `C:\Skripsi\backup_db_2026-10-09\bank_sampah_before_master_import_20261009.sql`; sumber SQL asli tetap di Downloads. Media dummy sasaran kosong sebelum reset; tidak ada berkas media material yang terhapus. Untuk pemulihan baseline lama gunakan backup yang sudah diuji, bukan dump sumber yang membawa skema lama.

Konfigurasi Laragon `bank-sampah.test` masih menunjuk proyek lama `C:\Projek Website\Bank-Sampah\public`. Konfigurasi tersebut tidak diubah. Server testing manual proyek Sindangheula dijalankan pada `http://127.0.0.1:8884`, menggunakan `.env` lokal dan database utama `bank_sampah`. Server ini bukan deployment dan hanya menerima koneksi loopback. Jika proses berakhir, jalankan dari folder proyek: `php artisan serve --host=127.0.0.1 --port=8884`.

Telegram, deployment, ZIP, perubahan Word skripsi asli, UAT/SUS peserta nyata, serta login browser semua akun sumber tidak dilakukan. Validasi hash/source dan workflow disposable bukan klaim bahwa pengguna desa sudah menguji seluruh antarmuka.
