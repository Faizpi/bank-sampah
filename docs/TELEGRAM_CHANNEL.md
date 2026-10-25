# Kanal Bot Telegram

Status implementasi: fitur tersedia pada working copy per 25 Oktober 2026, tetapi `TELEGRAM_ENABLED=false` secara default. Token bot belum dipasang, webhook publik belum didaftarkan, dan belum ada UAT/aktivasi produksi. WhatsApp tidak digunakan.

## Batas kanal dan akun

- Bot melayani Warga, Petugas, dan Bendahara. Admin tetap mengelola akun, master, laporan lintas akun, audit, dan pengecualian lewat panel web.
- Web dan bot memanggil service domain serta ledger yang sama. Tidak ada pembukuan atau saldo terpisah dan tidak perlu mengulang transaksi yang sudah dicatat di kanal lain.
- Satu akun web tertaut ke maksimal satu Telegram aktif dan satu Telegram ke maksimal satu akun web. Penautan dimulai dari Profil → Telegram, memakai kata sandi hanya di web, challenge acak sekali pakai berumur lima menit, lalu konfirmasi kepemilikan dari web. Username/password tidak diminta di chat.
- Tautan bersifat persisten; otorisasi tindakan terpisah: 30 hari untuk Warga dan 8 jam untuk Petugas/Bendahara, dengan izin Petugas/Bendahara juga berakhir setelah 60 menit tanpa aktivitas sah di bot dan diperbarui dari web tanpa memasang ulang tautan. Percobaan klaim tautan tidak sah dari satu Telegram dibatasi lima kali per 15 menit, lalu ditunda 15 menit. Role, status akun, wilayah, claimant, status layanan, dan idempotensi diperiksa ulang saat tindakan. Notifikasi tidak memperpanjang otorisasi.
- Pengguna dapat mengakhiri otorisasi dengan `/keluar`, memperbaruinya dari web, atau memutus tautan dari web. Admin yang berwenang dapat mencabut tautan beserta kiriman yang masih tertunda. Pesan yang sudah dikirim tidak dapat ditarik dari Telegram.

## Fitur

Menu umum menyediakan katalog/foto/harga, perkiraan nilai, paket aktif, jadwal layanan keliling, pengumuman, target yang dipublikasikan, dan panduan. Data publik tetap tunduk pada batas privasi web.

- Warga: saldo tersedia/disisihkan dan ringkasan mutasi; riwayat/rincian; kartu QR tanpa memutar token; pickup dengan foto/perkiraan opsional; pencairan tunai; sembako; status serta pembatalan yang sesuai tahap.
- Petugas: antrean/tugas pickup; buka layanan keliling dan lihat rekap/kapasitas; cari Warga sesuai area; catat timbang/finalisasi setoran langsung, pickup, atau keliling; klaim/persiapan opsional/serah-terima sembako; riwayat pekerjaan sendiri.
- Bendahara: antrean pembayaran searea, klaim saat tunai tersedia, tinjau penerima/nominal dan satu konfirmasi setelah serah fisik, riwayat pembayaran sendiri, ringkasan periode, dan Excel pembayaran sendiri.
- Notifikasi transaksi, pengingat, ringkasan saldo, serta langganan pengumuman adalah pilihan terpisah dan **mati sampai pengguna memilihnya**. Frekuensi ringkasan dapat diatur. Pengingat dikirim maksimal satu kali per hari pada pukul 09.00 WIB, dan kiriman terjadwal (pengingat/ringkasan/pengumuman) menghormati jam tenang 20.00–07.00 WIB. Pengingat dibatasi dan kiriman ditinjau ulang terhadap izin/status sebelum dikirim.
- Foto bot tidak wajib, format/ukuran diperiksa, disimpan di disk privat, dan tidak pernah menjadi bukti independen bahwa serah fisik terjadi.

Semua mutasi transaksi memakai layanan domain yang sudah digunakan web. Membuka saldo/informasi tidak membuat draft, hold, kartu baru, atau transaksi. Pengajuan pencairan/sembako menahan saldo; mutasi keluar hanya terjadi setelah penyelesaian fisik yang sah.

## Konfigurasi dan operasi host

1. Kebijakan privasi v2.0 telah diterbitkan pada 25 Oktober 2026 dan mencakup alur penautan, data yang dikirim ke Telegram, retensi kiriman, consent, serta cara pencabutan. Publikasikan versi tersebut di website (halaman ketentuan sudah menampilkan v2.0); riwayat penerimaan v1.0 tetap berlaku secara prospektif.
2. Buat bot melalui BotFather dan simpan token hanya di `.env` privat. Isi `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` (tanpa `@`), serta `TELEGRAM_WEBHOOK_SECRET` acak minimal 32 karakter. Jangan kirim token lewat chat, commit, screenshot, atau command argument.
3. Pastikan `APP_URL` adalah HTTPS publik; backup database/media, unggah build yang sama dengan source, lalu jalankan migration additive melalui prosedur deployment. Migration Telegram menambah tabel dan tidak me-reset atau men-seed data transaksi.
4. Set `TELEGRAM_ENABLED=true` hanya setelah langkah sebelumnya disetujui; bersihkan config cache, lalu jalankan `php artisan telegram:diagnose`. Pemeriksaan jaringan opsional: `php artisan telegram:diagnose --network` (memanggil `getMe` dan `getWebhookInfo`, tidak mengubah webhook).
5. Daftarkan webhook dengan `php artisan telegram:webhook`. Perintah menolak URL non-HTTPS/localhost dan mencocokkan username token. Jalur webhook hanya menerima chat pribadi dan tidak memakai sesi/CSRF browser.
6. Cron host tetap harus memanggil `php artisan schedule:run` setiap menit. Saat fitur aktif, scheduler memproses batch update/outbox tiap menit, pengingat/ringkasan tiap lima menit, dan retensi harian. Belum diperlukan worker daemon terpisah untuk konfigurasi ini.
7. Uji penautan serta tugas setiap role pada akun uji, verifikasi pencabutan/consent/media dan retry, lalu lakukan UAT dengan warga/pengurus sebelum penerapan. Tes lokal atau API mock bukan bukti webhook live, usability, SUS, ataupun persetujuan desa.

Untuk menghentikan dengan tertib, saat token masih tersedia jalankan `php artisan telegram:webhook --remove`, kemudian set `TELEGRAM_ENABLED=false` dan bersihkan config cache. Hapus token dari environment jika tidak lagi dipakai. Perintah penghapusan webhook mempertahankan update Telegram yang tertunda.

## Verifikasi lokal

Tes Telegram dijalankan dengan `vendor/bin/pest tests/Feature/Telegram`. Tes otomatis memakai SQLite `:memory:` atau MySQL loopback database khusus `bank_sampah_test_telegram`; `tests/bootstrap.php` menolak nama database lain. Jangan gunakan database `bank_sampah` atau hosting untuk tes yang dapat membuat/mengubah transaksi. Jalankan juga suite regresi web, Pint, dan PHPStan sebelum review.

Kredensial nyata tidak dicantumkan di repo. Sampai token, HTTPS, dan scheduler disiapkan secara terpisah, status bot adalah **implementasi lokal—belum aktif**, bukan “bot sudah live”. UAT dan revisi Bab III skripsi tetap pekerjaan tersendiri.
