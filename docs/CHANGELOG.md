# Catatan perubahan

## Kanal Telegram (kode lokal, 25 Oktober 2026)

- Menambahkan webhook terverifikasi, update/outbox idempoten, penautan aktif 1:1 dari web, masa otorisasi tindakan, pencabutan Admin, dan antarmuka konfigurasi.
- Menambahkan alur Informasi, Warga, Petugas, dan Bendahara melalui service domain yang sama; termasuk penimbangan/finalisasi, pickup, sembako, layanan keliling, dan ekspor laporan pribadi.
- Menambahkan consent terpisah yang opt-in secara default mati untuk notifikasi transaksi, pengingat, ringkasan berkala, dan langganan pengumuman.
- Menambahkan penanganan foto opsional privat, pengingat/ringkasan, target publik dengan pembatasan kelompok kecil, retry dan retensi.
- Kolom kategori antrean diperlebar ke 40 karakter agar MySQL menerima label seperti `transaction_notifications`; suite Telegram lulus 44/44 di SQLite dan MySQL disposable.
- Menyelaraskan empat parameter Tabel 3 skripsi: izin Petugas/Bendahara berakhir juga setelah 60 menit tanpa aktivitas sah (kolom `last_activity_at`), klaim tautan tidak sah dibatasi lima kali per 15 menit per Telegram lalu ditunda, pengingat dipatok pukul 09.00 WIB sekali per hari, dan kiriman terjadwal menghormati jam tenang 20.00–07.00 WIB.
- Menerbitkan Ketentuan Operasional dan Kebijakan Privasi v2.0 yang mencakup kanal Telegram (penautan, data kiriman, consent default mati, retensi, pencabutan); versi default pendaftaran baru naik ke v2.0 tanpa permintaan persetujuan ulang massal bagi penerima v1.0.
- Harness tes menyediakan `.env.testing` kosong pada direktori runtime terisolasi sehingga suite tanpa file tersebut tetap bebas peringatan dan tidak pernah membaca `.env` developer; halaman pencabutan Admin diuji dengan fitur aktif tanpa mengubah konfigurasi normal.
- Telegram tetap nonaktif secara default. Ini bukan bukti token/webhook produksi, UAT, atau persetujuan kebijakan privasi.

## Perapian copy publik dan urutan landing 9 Oktober 2026

- Catatan teknis statistik/foto di bawah kartu, penjelasan harga berulang, dan slogan footer generik dihapus dari UI publik.
- Judul ringkasan/katalog/harga dibuat langsung; petunjuk pemilahan, status foto/harga, angka dan tautan tetap ada.
- Kepala Desa dipindahkan utuh tepat sesudah hero, sebelum ringkasan dan sampah.
- Backend, hak akses, privasi, database, dan pengelolaan foto tidak diubah. [Bukti cleanup](PUBLIC_COPY_CLEANUP_2026-10-09.md).

## Landing dan foto katalog 9 Oktober 2026

- Ringkasan Warga aktif, Petugas aktif, setoran final, dan berat seluruh desa, dengan perlindungan kelompok kecil dan penghormatan publikasi Admin.
- Kartu jenis sampah/harga aktif pada landing; query, foto, dan harga yang sama pada katalog dan harga.
- Foto melalui aksi khusus Admin atau form jenis; unggahan wajib untuk jenis baru, edit teks mempertahankan foto lama.
- Foto publik berada di tabel/disk terpisah, dinormalisasi JPEG tanpa EXIF/GPS dan dengan orientasi kamera; bukti privat tidak dibuka.
- Relasi pilihan Filament disimpan melalui domain, bukan penulis otomatis kedua; label aksesibel/placeholder dirapikan.
- Migrasi hanya menambah tabel foto, tidak me-reset data utama. [Bukti dan panduan](LANDING_CATALOG_2026-10-09.md).

## Data utama 9 Oktober 2026

- Satu `DatabaseSeeder` menggunakan sumber SQL pemilik yang diekstrak menjadi JSON privat, bukan menjalankan dump mentah.
- Data sumber: 26 akun, 5 RW, 19 RT, empat penugasan, empat jenis sampah/harga. Superadmin menjadi Admin, tanpa rangkap peran.
- Hash kata sandi tetap; QR diterbitkan ulang dengan kunci lokal. Profil Warga pada akun Petugas lama tidak dibawa.
- Seeder demo/awal dipangkas; fixture workflow pindah ke `tests/Fixtures`. Bootstrap Admin production tetap terlindungi.
- Reset lokal memvalidasi dan membekukan sumber sebelum perubahan. Seed biasa menolak database berisi data.
- Data privat tidak diunggah ke GitHub. [Panduan](SEEDING.md).

## Penyempurnaan UI 8 Oktober 2026

- Perbaikan pilihan keliling, form edit jadwal/target, bukti publik anti-tamper, kuitansi draf, laporan, navigasi Admin, pickup terkunci, dan penutupan layanan lewat jam.
- Menu semua layanan, pickup satu form, hasil pencarian warga eksplisit, antrean Menunggu/Tugas saya dengan pagination dan lampiran dilipat.
- Akun pengurus+tugas atomik, tugas baru saat peran berubah, format angka/telepon lokal, label wilayah lengkap, dan tabel Admin bertumpuk di ponsel.
- Pemeriksaan saldo satu Admin dengan isian per record, selisih beralasan, tanpa perubahan saldo otomatis.
- Rincian/akibat pengecualian, kartu baca-saja dan pelengkapan diaudit, pengamanan preview/operasi teknis dormant.
- Kontrak desain, panduan, tutorial, README, proposal dan flowchart diperbarui. [Bukti pelaksanaan](IMPLEMENTATION_UI_2026-10-08.md).

## Cutover empat role 5 Oktober 2026

- Role scalar dengan constraint kanonis dan pengaman Admin terakhir.
- Penghapusan role/izin dinamis dan pengajuan oleh pengurus atas nama Warga.
- Setoran langsung final serta lampiran rutin opsional.
- Pickup, pencairan tunai, dan sembako menggunakan claim pelaksana searea.
- Tidak ada approval Admin rutin atau expiry otomatis layanan.
- Jalur exception, pemulihan akses yang dicabut, dan pengalihan pickup beralasan.
- Laporan Bendahara dibatasi ke pembayaran sendiri; koreksi resmi memakai reautentikasi.
- Reset demo lokal terjaga, seed empat akun/master tanpa transaksi.
- Panduan, README, proposal/flowchart, dan tes mengikuti kontrak baru.

Checkpoint sebelumnya tetap tersedia dalam riwayat Git. Hasil pengujian terbaru dicatat pada [validasi cutover](VALIDATION_CUTOVER_2026-10-05.md), bukan dianggap sama dengan checkpoint lama.
