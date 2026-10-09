# Catatan perubahan

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
