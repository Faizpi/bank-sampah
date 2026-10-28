# Keputusan cutover 5 Oktober 2026

Pemilik menyatakan akun, transaksi dan kegiatan lama adalah dummy serta mengizinkan reset bersih. Targetnya empat role tetap, satu akun satu role, semua Warga memakai smartphone, dan pengurus tidak merangkap.

Approval Admin rutin dan foto wajib dihilangkan. Claim mengikat pelaksana sebelum serah fisik. Kas/paket harus tersedia sebelum claim; tidak ada pembayaran sebagian, modul kas, tunda kas, atau expiry otomatis layanan.

Hold belum merupakan pengeluaran. Penyelesaian atomik dan deduplikasi tetap dipertahankan. Admin hanya mengelola sistem dan pengecualian beralasan dengan autentikasi ulang. Klaim yang akses pelaksananya dicabut tetap dikunci sampai fakta dipastikan, bukan dibatalkan diam-diam.

WhatsApp tidak digunakan. Telegram dipisahkan sebagai kanal opsional; tes otomatis tidak menggantikan UAT peserta nyata.

## Kanal Telegram 25 Oktober 2026

Catatan historis: batas waktu otorisasi dalam dua butir berikut telah digantikan keputusan 28 Oktober 2026 di bawah.

- Satu akun web aktif tertaut ke paling banyak satu Telegram, dan satu Telegram ke paling banyak satu akun web. Penautan dimulai dan dikonfirmasi dari web melalui challenge sekali pakai; username/password tidak dimasukkan di chat.
- Otorisasi tindakan berjangka (Warga 30 hari, Petugas/Bendahara 8 jam) terpisah dari tautan dan consent notifikasi. Role, status, area, claimant, status transaksi, dan idempotensi diperiksa ulang oleh layanan server.
- Izin Petugas/Bendahara juga berakhir setelah 60 menit tanpa aktivitas sah di bot dan diperbarui lewat konfirmasi ulang di web tanpa memasang ulang tautan. Klaim tautan tidak sah dari satu Telegram dibatasi lima kali per 15 menit, lalu ditunda 15 menit.
- Pengingat dikirim maksimal satu kali per hari pada pukul 09.00 WIB; kiriman terjadwal (pengingat/ringkasan/pengumuman) menghormati jam tenang 20.00–07.00 WIB. Notifikasi transaksi/tugas tetap segera karena tidak terjadwal.
- Informasi, pengajuan, finalisasi setoran, pencatatan serah fisik, kartu/QR, antrean, serta laporan yang telah disetujui tersedia melalui bot untuk Warga/Petugas/Bendahara. Admin tetap di web. Kanal web tidak dihapus.
- Keempat pilihan notifikasi bersifat opt-in; pengumuman punya consent tersendiri. Foto bot opsional, tervalidasi dan privat. Pesan yang sudah dikirim tidak dapat ditarik dari riwayat Telegram.
- Implementasi kode nonaktif secara default. Aktivasi produksi menunggu pembaruan/persetujuan pemberitahuan privasi, token privat, HTTPS webhook, scheduler, dan UAT. Tidak ada klaim integrasi Telegram live sebelum itu.
- Kebijakan privasi v2.0 diterbitkan 25 Oktober 2026 atas persetujuan pemilik, menambah pasal kanal Telegram opsional pada Ketentuan Operasional (penautan, batas 1:1, izin berjangka, pencabutan) dan Kebijakan Privasi (data kiriman, consent default mati, retensi tujuh hari/24 jam, pesan terkirim tidak dapat ditarik). Berlaku prospektif; riwayat penerimaan v1.0 dipertahankan dan tidak ada permintaan persetujuan ulang massal. Gerbang aktivasi produksi yang tersisa: token privat, HTTPS webhook, scheduler, dan UAT.

## Akses Telegram persisten 28 Oktober 2026

- Pemilik meminta penghapusan masa otorisasi dan batas tidak aktif bagi seluruh role bot. Warga dapat mengajukan penjemputan setelah lama tidak aktif tanpa kembali ke website; Petugas/Bendahara mendapat kebijakan akses waktu yang sama.
- Akses berlaku selama tautan dan hak akses masih sah, bukan sampai tanggal tertentu. Penautan 1:1, verifikasi awal, pencabutan pemilik/Admin, pemeriksaan perubahan data keamanan akun, role/status/area/claim, dan idempotensi tetap berlaku.
- Tautan awal, tombol, serta state percakapan sementara tetap berumur pendek. Kedaluwarsa tombol/isian ditangani lewat /menu di bot, bukan penautan ulang di web.
- /keluar hanya membuka petunjuk pemutusan, tidak diam-diam mengakhiri akses. Putuskan Telegram pada web tetap aksi pencabutan yang sebenarnya.
- Migration baru mempertahankan seluruh tautan dan consent, membuat kolom legacy `authorized_until` nullable serta menonaktifkan kedua penanda waktu lama. Kode tidak lagi membaca/menulisnya sebagai syarat akses. Tidak ada reset/seed atau perubahan transaksi utama.
- Ketentuan dan privasi v2.1 berlaku prospektif; penerimaan v1.0/v2.0 tidak ditimpa dan tidak ada persetujuan ulang massal otomatis. Sumber Word skripsi tidak diubah oleh pekerjaan ini.

## Penyempurnaan UI 8 Oktober 2026

Pengguna menyetujui semua perbaikan audit, termasuk form penjemputan satu layar, menu semua layanan, pencarian warga yang tidak memilih diam-diam kandidat pertama, antrean Menunggu/Tugas saya dengan pagination, lampiran dilipat, akun pengurus+tugas atomik, dan laporan Bendahara minimal. Rekonsiliasi selesai dengan satu Admin; selisih dan hasil pemeriksaan tetap dicatat, tidak otomatis mengubah uang.

Audit awal mengira halaman teknis tambahan aktif. Pemeriksaan registrasi memperjelas bahwa hanya Health aktif; halaman lain mewarisi `isDiscovered=false`. Operasinya tetap diperketat tanpa mengaktifkan menu baru.
