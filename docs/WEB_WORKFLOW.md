# Workflow web empat role

Baseline empat role 5 Oktober 2026, disempurnakan melalui audit UI 8 Oktober 2026. Hanya Warga, Petugas, Bendahara, dan Admin; satu role per akun. Semua warga menggunakan smartphone sendiri.

## Setoran

Petugas mencari nama/nomor atau memindai warga aktif, memilih dari beberapa hasil bila ada, lalu mengonfirmasi nama sekaligus membuka setoran. Petugas mengisi jenis, kondisi, dan berat aktual, memeriksa total, lalu mencatat setoran. Semua setoran langsung final dan membuat satu mutasi masuk. Foto lampiran opsional. Transaksi final yang salah ditangani melalui koreksi atau pembalikan resmi.

## Pickup

Warga membuka Layanan dan mengisi satu form: alamat, tanggal pilihan, dan jenis sampah. Perkiraan dan foto opsional. Petugas searea mengambil tugas; penjadwalan memakai tanggal pilihan, kemudian menuju lokasi, menandai dijemput, dan menyelesaikan penimbangan aktual. Pickup selesai hanya setelah setoran final. Warga membatalkan sebelum klaim; claimant melepas tugas hanya sebelum sampah dijemput.

## Pencairan tunai

Pengajuan Warga langsung menahan saldo dan menunggu Bendahara. Bendahara mengambil pembayaran hanya jika uang tunai fisik tersedia. Setelah uang diberikan satu kali, claimant mencatat pembayaran. Mutasi keluar, konversi hold, waktu, pelaksana, dan kuitansi disimpan atomik. Tidak ada transfer bank, approval Admin, pembayaran sebagian, tombol tunda kas, atau expiry otomatis.

## Sembako

Pengajuan paket aktif langsung menahan saldo dan menunggu Petugas. Petugas mengambil tugas hanya bila paket tersedia, lalu dapat langsung menyerahkan dan mencatat penyerahan. Paket siap diambil merupakan pilihan untuk pengambilan kemudian, bukan tahap wajib. Tidak tersedia sebelum klaim memerlukan alasan dan melepas hold tanpa mutasi keluar.

## Kendala dan pengecualian

Claimant boleh melepas klaim hanya jika belum ada serah fisik; hold tetap aktif. Penyerahan yang belum dapat dipastikan dikunci dan dilaporkan ke Admin. Admin memastikan fakta, menulis alasan, dan melakukan autentikasi ulang. Jika sudah diserahkan, penyelesaian memakai sumber mutasi yang unik. Jika belum dan tidak ada permintaan batal, tugas kembali ke antrean dengan dana tetap disisihkan. Jika Warga meminta batal, transaksi dibatalkan dan hold dilepas. Jika belum pasti, tetap terkunci tanpa mutasi keluar.

## Saldo

Saldo tersedia adalah mutasi masuk dikurangi mutasi keluar dan hold aktif. Saldo tertahan belum merupakan mutasi keluar. Pembatalan sah atau paket tidak tersedia melepas hold tepat satu kali. Konfirmasi penyerahan membuat mutasi keluar tepat satu kali. Tombol yang dicoba ulang tidak boleh menyebabkan penyerahan fisik kedua.

## Akun dan batas akses

- Warga: Akun dan smartphone sendiri; saldo, riwayat, kartu, pengajuan pickup, pencairan tunai, dan sembako.
- Petugas: Pindai warga, setoran, pickup, layanan keliling, dan sembako pada area tugas aktif. Tidak membayar tunai.
- Bendahara: Mengambil serta mencatat pembayaran tunai pada area tugas aktif. Laporan hanya pembayaran sendiri.
- Admin: Akun sekaligus area tugas, verifikasi warga, wilayah, harga, paket, laporan, audit, pemeriksaan saldo satu Admin, koreksi resmi, pengecualian, dan Health baca-saja. Bukan pelaksana fisik rutin.

## Batas implementasi

- Satu akun memiliki tepat satu dari empat role tetap. Izin tidak dapat diedit melalui panel. Admin aktif terakhir dilindungi pada layanan dan database.
- Penimbangan/finalisasi setoran serta penyiapan pilihan dan serah-terima sembako tetap melalui web. Bot Telegram belum diimplementasikan. WhatsApp tidak digunakan.
- Semua warga diasumsikan mempunyai smartphone; tidak ada pengajuan oleh pengurus atas nama Warga.
- Lampiran rutin opsional. Bila diunggah, file tetap tervalidasi dan privat. Kuitansi sistem bukan bukti independen bahwa uang/barang benar-benar berpindah.
- Tidak ada modul kas atau stok terperinci. Ketersediaan uang/paket merupakan tanggung jawab pelaksana sebelum mengambil tugas.
- Pengumuman, harga publik, estimasi nilai, target pengumpulan, partisipasi RT/RW, dan bank sampah keliling tetap tersedia sesuai role. Statistik publik tidak memuat data pribadi.
- Teknologi utama tetap Laravel 13, PHP 8.3 atau lebih baru, MySQL 8.0.30/InnoDB, Livewire 4, Filament 5, dan Vite.

UAT empat role harus mencatat keberhasilan menyelesaikan tugas, kebutuhan bantuan, salah klik, waktu, dan pemahaman status. Tes otomatis bukan pengukuran usability. Tidak ada klaim nilai SUS atau hasil peserta nyata sebelum sesi tersebut dilakukan.

## Pembatalan setelah tugas diambil

Sebelum klaim, pembatalan langsung melepas hold. Setelah klaim, permintaan batal mengunci penyerahan dan hold tetap aktif. Claimant mengonfirmasi pembatalan hanya bila belum ada serah fisik. Jika sudah diserahkan/tidak pasti atau claimant tidak lagi berwenang, Admin memastikan fakta melalui exception. Ini mencegah saldo dilepas saat uang/paket sedang berpindah; bukan approval rutin.
