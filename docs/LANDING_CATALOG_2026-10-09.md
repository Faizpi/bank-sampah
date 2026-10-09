# Landing, statistik, dan foto katalog — 9 Oktober 2026

Permintaan: kartu ringkasan seperti referensi pengguna, mengikuti Green Ledger dan empat peran; sampah berfoto dikelola Admin dan sinkron pada landing, katalog, serta harga. Tidak ada identitas visual baru, foto stok/hotlink, bot, deployment, ZIP, atau perubahan Word skripsi.

## Pengelolaan foto

Admin membuka Data Master → Katalog Sampah → Jenis Sampah, lalu tombol **Foto** pada baris. Pilih JPG/PNG, tunggu **Pengunggahan selesai**, lalu **Simpan foto**. Aksi ini tidak mengubah kode, kategori, kondisi, harga, atau transaksi. Foto juga dapat diunggah dalam form jenis; foto wajib untuk jenis baru, sedangkan edit data lama boleh mempertahankan foto tersimpan dengan tidak mengunggah ulang.

File maksimal 5 MB/20 megapiksel. Server mendekode dan menyimpan JPEG maksimal 1 MB/sisi 2.000 px, membuang EXIF/GPS dan menjaga orientasi JPEG bila EXIF tersedia. Pilih foto sampah yang jelas, tanpa orang, lokasi rumah, atau bukti transaksi. Upload bukan persetujuan transaksi dan tidak membutuhkan Admin kedua.

Foto disimpan dalam `waste_type_photos` dan disk `waste_photos` (`storage/app/waste-catalog`), **bukan** pada media bukti privat. `waste_types.media_id` lama tidak pernah menjadi sumber gambar publik. Endpoint `/foto-sampah/{wasteType}/{photoUuid}` hanya menyajikan foto terkini milik jenis itu; jenis tidak aktif tidak dapat dilihat pengunjung, tetapi Admin dapat mempratinjaunya. Path/metadata upload tidak dibuka. Respons tidak disimpan cache/PWA; UUID lama tidak berlaku setelah penggantian. File lama tidak dihapus otomatis pada tahap ini dan tetap berada di folder nonpublik, bukan URL yang dapat diakses.

## Sumber bersama dan tampilan

`PublicWasteCatalog` menyaring jenis, kategori, satuan, dan kondisi aktif. Harga berlaku pada interval `[effective_from, effective_to)` dan hanya untuk pasangan jenis/kondisi yang masih diterima. Landing menampilkan maksimal delapan jenis dengan tautan katalog lengkap. Foto, nama, kondisi, dan harga berasal dari sumber yang sama; tidak perlu input ulang pada tiga halaman. Halaman yang sudah terbuka perlu dimuat ulang untuk melihat perubahan; tidak ada klaim pembaruan websocket.

Komponen foto bersama memiliki rasio/kotak stabil, alt bermakna, lazy loading, dan keterangan foto kosong/gagal. Data lama tetap menampilkan nama/harga agar informasi layanan tidak hilang hanya karena foto belum tersedia. Foto asli empat jenis pada database utama **belum diberikan/diunggah**; tidak diganti gambar buatan, foto orang, atau foto bukti Warga. Pengelola dapat melengkapinya melalui tombol Foto.

## Definisi empat angka

- **Warga aktif:** akun role Warga berstatus aktif dan memiliki profil nasabah. Bukan semua akun pengurus.
- **Petugas aktif:** role Petugas aktif, profil dan penugasan area berlaku pada tanggal berjalan, area aktif. Bukan Bendahara/Admin, bukan status online, dan bukan jaminan siap menjemput saat itu.
- **Setoran tercatat:** setoran final/dikoreksi yang telah terjadi; draft, dibalik, dan tanggal mendatang tidak dihitung.
- **Sampah terkumpul:** total berat tercatat pada setoran tersebut, bukan saldo atau nilai tunai. Nilai setoran asli tetap mengikuti kontrak audit; ini tidak membuat mutasi finansial.

Ringkasan mencakup seluruh desa sejak pencatatan sistem, tanpa filter orang/RT dari pengunjung. Tidak ada nama, kontak, saldo, atau transaksi per orang. Data nonkosong dari jumlah penyetor di bawah ambang privasi ditampilkan “—”, bukan nol palsu. Nol ditampilkan jika benar-benar belum ada setoran yang dihitung.

Jika publikasi `public-dashboard` sudah dikonfigurasi Admin, allowlist dan status nonaktifnya dihormati. Metrik Petugas aktif ditambahkan pada pilihan Admin. Tanpa konfigurasi lama, empat ringkasan global mengikuti permintaan fitur ini; agregat transaksi tetap memakai ambang privasi bawaan. Ini bukan perluasan statistik terperinci atau publikasi data pribadi.

## Migrasi, penyimpanan, dan backup

Jalankan migrasi `2026_10_09_130000_create_waste_type_photos_table` sebelum menggunakan versi ini. Migrasi hanya menambah tabel foto, tanpa reset/upsert akun, harga, atau saldo. Backup SQL lokal sebelum migrasi: `C:\Skripsi\backup_db_2026-10-09\bank_sampah_before_public_catalog_20261009.sql`.

Backup setelah foto diunggah harus mencakup database **dan** folder `storage/app/waste-catalog`, selain folder bukti privat `storage/app/media` yang sudah ada. SQL saja tidak menyertakan bytes gambar. Jangan mengunggah folder runtime/foto, `.env`, backup, atau data pribadi ke GitHub; file foto bukan aset yang dibundel dengan commit kode.

Database utama sesudah migrasi masih 20 Warga, 3 Petugas, 1 Bendahara, 2 Admin; empat jenis/harga; nol setoran, nol foto katalog. Angka ini adalah snapshot dummy lokal saat validasi, bukan bukti penggunaan nyata di desa.

## Bukti pengujian

| Pemeriksaan | Hasil |
|---|---|
| Suite lengkap SQLite | 1.216 lulus, 1 skip khusus Windows; 5.740 assertions |
| Regresi baru | 17 lulus: publikasi, kelompok kecil, final/reversal, foto/UUID, hak akses, path, metadata, orientasi, form create/edit/aksi foto |
| MySQL terpilih | 109 lulus, 850 assertions: public site, statistik, Filament, platform/media |
| PHPStan, Pint, Vite | Lulus |
| Upload browser Admin | Foto uji mencapai status Pengunggahan selesai, disimpan lewat aksi Foto, lalu thumbnail dapat dimuat |
| Sinkron foto publik | UUID/URL yang sama dan harga Rp1.500 tampil pada landing, katalog, serta harga sebagai pengunjung tanpa login |
| Responsif | 12/12 kombinasi tiga halaman × 360/390/768/1280; foto termuat, tanpa overflow horizontal |
| Admin ponsel | Tab aktif diuji pada 390 px; empat tombol Foto tetap tersedia, tanpa overflow halaman |
| Database utama | Migrasi penambahan saja; tidak diberi foto/transaksi uji atau perubahan credential |

Database uji: `bank_sampah_test_landing20261009_suite` dan `_browser`; fixture hanya pada testing/loopback/disposable. Gambar browser adalah file sintetis untuk menguji transfer/decoder, bukan foto sampah desa. Artefak ada di `artifacts/landing-2026-10-09` (diabaikan Git). Tes korelasi kini memakai migrasi karena home tidak lagi statis; fixture nomor setoran dibatasi sesuai skema, dan tes kegagalan metadata memakai constraint yang benar pada MySQL/SQLite.

Pengujian otomatis/browser ini tidak menggantikan UAT, foto asli, kamera/perangkat desa, atau pemeriksaan pembaca layar nyata. Telegram belum diimplementasikan. Konfigurasi domain Laragon terpisah dari fitur ini; preview proyek yang benar tetap dapat dibuka pada `http://127.0.0.1:8884`.
