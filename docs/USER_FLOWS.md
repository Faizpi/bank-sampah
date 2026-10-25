# Panduan pengguna web

Acuan aktif: [workflow empat role](WEB_WORKFLOW.md) dan [kontrak UI](../DESIGN.md), diperbarui 25 Oktober 2026. Telegram merupakan kanal opsional nonaktif secara default; WhatsApp tidak digunakan.

## Warga

1. Daftar dengan akun sendiri, RT/alamat, dan penerimaan ketentuan. Verifikasi akun oleh Admin dilakukan sekali, bukan setiap transaksi.
2. Beranda menampilkan saldo tersedia, dana yang disisihkan, dan pengajuan aktif. Dana disisihkan belum dibayarkan.
3. Menu Layanan berisi penjemputan, pencairan tunai, sembako, estimasi, dan jadwal keliling.
4. Penjemputan: isi satu form alamat/tanggal/jenis. Catatan, foto, dan perkiraan tambahan boleh dilewati. Periksa isian lalu kirim; Petugas mengambil tugas langsung.
5. Pencairan: isi nominal dan lokasi/tanggal. Pembayaran tunai penuh oleh Bendahara; tidak menunggu persetujuan Admin.
6. Sembako: pilih paket sesuai saldo. Petugas mengambil tugas saat paket tersedia; Siap diambil bukan tahapan wajib.
7. Sebelum klaim, pembatalan langsung selesai dan dana dilepas. Setelah klaim, tombol Minta pembatalan mengunci penyerahan sampai pelaksana memastikan manfaat belum diberikan. Ketidakpastian diperiksa Admin, bukan diserahkan dua kali.
8. Kuitansi tersedia setelah selesai. Draf adalah pekerjaan Petugas, bukan transaksi Rp0 yang harus dibuka Warga. Riwayat memisahkan Setoran/Pencairan/Sembako.

## Petugas

1. Pilih Menunggu diambil atau Tugas saya, lalu jenis tugas. Daftar dipaginasi; satu panel kosong tidak berarti semua jenis tugas kosong.
2. Ambil penjemputan searea, lanjutkan perjalanan/pengumpulan, masukkan timbang aktual, lalu catat selesai. Tugas terkunci tidak menyediakan operasi biasa.
3. Setoran: cari nama/nomor atau pindai QR. Jika hasil lebih dari satu, pilih warga berdasarkan nomor/domisili. Klik Nama sudah sesuai — catat setoran untuk membuka form.
4. Pilih konteks keliling hanya dari layanan aktif yang ditugaskan. Isi jenis/kondisi/berat, periksa subtotal/total, lalu satu pencatatan akhir. Draf dapat dilanjutkan dari Tugas saya.
5. Ambil sembako jika paket fisik tersedia; serahkan satu kali dan catat. Periksa nama/paket/nominal pada dialog, lalu konfirmasi pencatatan. Periksa lagi kembali tanpa mutasi. Foto ada dalam Tambahkan foto opsional. Jika paket tidak tersedia sebelum klaim, beri alasan; dana dilepas tanpa pengeluaran.
6. Layanan keliling lewat jam tetapi belum ditutup tetap muncul. Lihat rekap lalu tutup; tidak menerima setoran baru setelah jam atau kapasitas berakhir.
7. Buka Riwayat untuk Setoran/Penjemputan/Sembako yang sudah Anda kerjakan. Cari nama/nomor dan buka rinciannya. Ini hanya-baca; tidak dapat mencatat ulang transaksi atau melihat pekerjaan Petugas lain.

## Bendahara

1. Pengajuan sesuai area langsung masuk pembayaran. Pilih baris/rincian warga; ambil pembayaran hanya ketika tunai tersedia.
2. Pastikan nama dan nominal, serahkan uang satu kali, lalu klik Uang tunai sudah diserahkan — catat pembayaran. Periksa ringkasan dialog dan pilih Ya, catat pembayaran. Periksa lagi kembali tanpa mutasi. Lampiran foto tidak wajib.
3. Tugas saya berisi klaim yang diambil sendiri. Lepas tugas hanya jika uang belum diberikan. Permintaan batal Warga dapat dikonfirmasi jika uang dipastikan belum diberikan; ketidakpastian masuk pemeriksaan Admin.
4. Riwayat pembayaran saya hanya memuat pembayaran yang sudah Anda catat, bukan pengajuan menunggu. Pilih periode dan unduh Excel; tidak ada pilihan jenis/status yang mustahil.

## Admin

1. Pusat kerja menyoroti verifikasi akun dan pengecualian yang perlu perhatian; tidak ada persetujuan rutin penjemputan/pembayaran/sembako.
2. Buat akun pengurus sekaligus area dan tanggal tugas. Bila tugas tidak sah, akun tidak dibuat. Pergantian peran mengharuskan tugas baru yang eksplisit; jangan merangkap peran.
3. Di ponsel, tabel memakai baris bertumpuk; desktop tetap tabel lengkap. Form edit mempertahankan data dan cakupan yang tersimpan.
4. Pemeriksaan saldo: pilih tanggal dan total tunai yang telah dibayarkan (0 bila tidak ada), lihat pembanding, lalu catat hasil dengan akun Admin yang sama. Selisih memerlukan penjelasan. Setiap tanggal/versi mempunyai isian sendiri; pemeriksaan tidak mengubah saldo. Gunakan koreksi resmi untuk kesalahan transaksi.
5. Sebelum memutuskan pengecualian, lihat rincian klaim/paket/dana/riwayat. Pastikan fakta, isi alasan, dan autentikasi ulang. Jika Warga meminta batal dan manfaat belum diberikan, transaksi dibatalkan serta dana dilepas; jika tidak ada permintaan batal, tugas kembali ke antrean dengan dana masih disisihkan.
6. Kartu Warga baca-saja. Penerbitan saat verifikasi atau pelengkapan kartu dilakukan melalui aksi yang diaudit; bukan otomatis saat halaman kartu dibuka.
7. Health adalah menu teknis aktif. Fasilitas teknis internal lain tidak diaktifkan menjadi menu baru oleh perbaikan ini.

## Telegram (opsional)

1. Masuk ke web, buka Profil → Telegram, masukkan kata sandi di website, lalu buka tautan sekali pakai ke bot. Kembali ke web dan cocokkan nama serta empat digit akhir ID sebelum mengonfirmasi.
2. Satu akun web hanya dapat tertaut ke satu akun Telegram aktif, dan sebaliknya. Admin tetap mengelola akun serta pengecualian di panel web.
3. Setelah otorisasi tindakan habis, tautan dan notifikasi yang dipilih tetap aktif, tetapi tindakan perlu diotorisasi ulang dari web. /keluar mengakhiri otorisasi tindakan; putuskan tautan menghentikan akses bot.
4. Warga dapat melihat saldo/riwayat/kartu dan mengajukan pickup, pencairan, serta sembako. Petugas dapat menangani pickup, timbang/finalisasi setoran, sembako, dan layanan keliling. Bendahara dapat mengambil, mencatat pembayaran tunai, serta mengirim laporan sendiri.
5. Menu Informasi umum tidak perlu login. Pilihan info transaksi, pengingat, ringkasan, dan pengumuman mati sampai dipilih sendiri; frekuensi ringkasan dapat diubah. Pesan yang sudah terkirim tetap tersimpan di Telegram.
6. Gunakan web jika kanal Telegram belum aktif atau ada perubahan akun/area/akses. Kedua kanal memakai layanan domain yang sama; setelah serah fisik jangan mengulang penyerahan karena balasan bot terlambat.

## Format dan pengaman

Berat menerima 1,25 atau 1.25 sebelum dinormalisasi; batas tiga desimal tetap berlaku. Nominal pencairan menerima 20000 atau 20.000, tanpa pecahan rupiah. Telepon boleh memakai 08, 62, atau +62. Nilai, wilayah, role, status, klaim, dan saldo tetap diperiksa server.

Claim belum berarti uang/paket sudah diberikan. Respons hilang bukan alasan mengulang serah fisik; coba pencatatan kembali atau gunakan pengecualian bila faktanya tidak pasti. Tes otomatis dan panduan ini belum membuktikan UAT atau nilai SUS peserta desa. Video eksternal lama, bila ada, perlu ditinjau pemilik; panduan teks di atas adalah acuan terbaru.
