# Workflow web dan Telegram empat role

Baseline empat role 5 Oktober 2026, disempurnakan melalui audit UI 8 dan 21 Oktober 2026. Hanya Warga, Petugas, Bendahara, dan Admin; satu role per akun. Semua warga menggunakan smartphone sendiri. Web tetap tersedia sebagai kanal utama; Telegram adalah kanal opsional yang memakai layanan domain dan pembukuan yang sama.

## Setoran

Petugas mencari nama/nomor atau memindai warga aktif, memilih dari beberapa hasil bila ada, lalu mengonfirmasi nama sekaligus membuka setoran. Petugas mengisi jenis, kondisi, dan berat aktual, memeriksa total, lalu mencatat setoran. Alur yang sama tersedia melalui Telegram selama akun Petugas tetap tertaut dan hak aksesnya masih sah, tanpa batas waktu atau pembaruan berkala. Semua setoran langsung final dan membuat satu mutasi masuk. Foto lampiran opsional. Transaksi final yang salah ditangani melalui koreksi atau pembalikan resmi.

## Pickup

Warga membuka Layanan di web atau menu Telegram dan mengisi bertahap: alamat, tanggal pilihan, dan jenis sampah. Perkiraan dan foto opsional. Petugas searea mengambil tugas melalui web atau Telegram; penjadwalan memakai tanggal pilihan, kemudian menuju lokasi, menandai dijemput, dan menyelesaikan penimbangan aktual. Pickup selesai hanya setelah setoran final. Warga membatalkan sebelum klaim; claimant melepas tugas hanya sebelum sampah dijemput.

## Pencairan tunai

Bagian ini khusus pembayaran fisik. Jalur tambahan [pencairan digital simulasi](PAYOUT_SANDBOX.md) tidak memakai antrean atau klaim Bendahara.

Pengajuan Warga melalui web atau Telegram langsung menahan saldo dan menunggu Bendahara. Bendahara mengambil pembayaran hanya jika uang tunai fisik tersedia. Setelah uang diberikan satu kali, claimant mencatat pembayaran dari web atau Telegram. Mutasi keluar, konversi hold, waktu, pelaksana, dan kuitansi disimpan atomik. Tidak ada transfer bank, approval Admin, pembayaran sebagian, tombol tunda kas, atau expiry otomatis.

## Sembako

Pengajuan paket aktif melalui web atau Telegram langsung menahan saldo dan menunggu Petugas. Petugas mengambil tugas hanya bila paket tersedia, lalu dapat langsung menyerahkan dan mencatat penyerahan dari web atau Telegram. Paket siap diambil merupakan pilihan untuk pengambilan kemudian, bukan tahap wajib. Tidak tersedia sebelum klaim memerlukan alasan dan melepas hold tanpa mutasi keluar.

## Kendala dan pengecualian

Claimant boleh melepas klaim hanya jika belum ada serah fisik; hold tetap aktif. Penyerahan yang belum dapat dipastikan dikunci dan dilaporkan ke Admin. Admin memastikan fakta, menulis alasan, dan melakukan autentikasi ulang. Jika sudah diserahkan, penyelesaian memakai sumber mutasi yang unik. Jika belum dan tidak ada permintaan batal, tugas kembali ke antrean dengan dana tetap disisihkan. Jika Warga meminta batal, transaksi dibatalkan dan hold dilepas. Jika belum pasti, tetap terkunci tanpa mutasi keluar.

## Saldo

Saldo tersedia adalah mutasi masuk dikurangi mutasi keluar dan hold aktif. Saldo tertahan belum merupakan mutasi keluar. Pembatalan sah atau paket tidak tersedia melepas hold tepat satu kali. Konfirmasi penyerahan membuat mutasi keluar tepat satu kali. Tombol yang dicoba ulang tidak boleh menyebabkan penyerahan fisik kedua.

Sebelum pencatatan pembayaran tunai atau penyerahan sembako, pelaksana melihat satu dialog nama, nomor, dan nominal. Periksa lagi hanya menutup dialog, tanpa mutasi. Konfirmasi tetap memanggil layanan domain yang memeriksa klaim, status, area, dan idempotensi; bukan approval Admin.

## Riwayat pekerjaan Petugas

Menu Riwayat memuat setoran final (termasuk yang kemudian dikoreksi/dibalik), penjemputan terminal yang ditugaskan kepadanya, dan sembako selesai yang diklaim/diserahkan sendiri. Daftar dipaginasi dan dapat dicari dengan nama atau nomor. Riwayat tidak menyediakan aksi finansial dan tidak menampilkan pekerjaan Petugas lain walaupun searea. Tugas aktif tetap berada di Tugas saya. Tautan tugas sembako yang sudah selesai dialihkan ke riwayat berotorisasi, bukan 404 bagi pelaksananya. Bukti setoran penjemputan dapat dibuka lagi setelah pemuatan ulang tanpa membuat mutasi baru.

## Akun dan batas akses

- Warga: Akun dan smartphone sendiri; saldo, riwayat, kartu, pengajuan pickup, pencairan tunai, dan sembako.
- Petugas: Pindai warga, setoran, pickup, layanan keliling, dan sembako pada area tugas aktif. Tidak membayar tunai.
- Bendahara: Mengambil serta mencatat pembayaran tunai pada area tugas aktif. Laporan hanya pembayaran sendiri.
- Admin: Akun sekaligus area tugas, verifikasi warga, wilayah, harga, paket, laporan, audit, pemeriksaan saldo satu Admin, koreksi resmi, pengecualian, dan Health baca-saja. Bukan pelaksana fisik rutin.

## Batas implementasi

- Satu akun memiliki tepat satu dari empat role tetap. Izin tidak dapat diedit melalui panel. Admin aktif terakhir dilindungi pada layanan dan database.
- Penimbangan/finalisasi setoran serta pencatatan persiapan dan serah-terima sembako dapat dilakukan melalui web atau bot Telegram; kegiatan fisiknya tetap dilakukan langsung. Kedua kanal memakai guard dan layanan domain yang sama. Admin tetap memakai web. WhatsApp tidak digunakan.
- Bot Telegram aktif di production dengan konfigurasi environment server; `TELEGRAM_ENABLED=false` tetap default `.env.example` untuk instalasi baru. Aktivasi instalasi baru tetap memerlukan token privat, HTTPS/webhook, scheduler, dokumentasi privasi yang disetujui, dan UAT terpisah. Satu akun web tertaut ke maksimal satu Telegram aktif dan satu akun Telegram ke maksimal satu akun web.
- Semua warga diasumsikan mempunyai smartphone; tidak ada pengajuan oleh pengurus atas nama Warga.
- Lampiran rutin opsional. Bila diunggah, file tetap tervalidasi dan privat. Kuitansi sistem bukan bukti independen bahwa uang/barang benar-benar berpindah.
- Tidak ada modul kas atau stok terperinci. Ketersediaan uang/paket merupakan tanggung jawab pelaksana sebelum mengambil tugas.
- Pengumuman, harga publik, estimasi nilai, target pengumpulan, partisipasi RT/RW, dan bank sampah keliling tetap tersedia sesuai role. Statistik publik tidak memuat data pribadi.
- Teknologi utama tetap Laravel 13, PHP 8.3 atau lebih baru, MySQL 8.0.30/InnoDB, Livewire 4, Filament 5, dan Vite.

UAT empat role harus mencatat keberhasilan menyelesaikan tugas, kebutuhan bantuan, salah klik, waktu, dan pemahaman status. Tes otomatis bukan pengukuran usability. Tidak ada klaim nilai SUS atau hasil peserta nyata sebelum sesi tersebut dilakukan.

## Pembatalan setelah tugas diambil

Sebelum klaim, pembatalan langsung melepas hold. Setelah klaim, permintaan batal mengunci penyerahan dan hold tetap aktif. Claimant mengonfirmasi pembatalan hanya bila belum ada serah fisik. Jika sudah diserahkan/tidak pasti atau claimant tidak lagi berwenang, Admin memastikan fakta melalui exception. Ini mencegah saldo dilepas saat uang/paket sedang berpindah; bukan approval rutin.
