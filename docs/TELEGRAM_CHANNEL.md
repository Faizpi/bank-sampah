# Kanal Bot Telegram

Status kode: kanal tersedia sejak 25 Oktober 2026 dan kebijakan akses persisten disetujui pada 28 Oktober 2026. Bot sudah aktif di production dengan token, username, dan webhook yang dikonfigurasi melalui environment server. `TELEGRAM_ENABLED=false` tetap nilai bawaan `.env.example` untuk instalasi baru. Aktivasi production bukan bukti UAT, SUS, atau hasil pengujian rilis 28 Oktober di hosting. WhatsApp tidak digunakan.

## Batas kanal dan akun

- Bot melayani Warga, Petugas, dan Bendahara. Admin tetap mengelola akun, master, laporan lintas akun, audit, dan pengecualian lewat panel web.
- Web dan bot memanggil service domain serta ledger yang sama. Tidak ada pembukuan atau saldo terpisah dan tidak perlu mengulang transaksi yang sudah dicatat di kanal lain.
- Satu akun web tertaut ke maksimal satu Telegram aktif dan satu Telegram ke maksimal satu akun web. Penautan dimulai dari Profil → Telegram, memakai kata sandi hanya di web, challenge acak sekali pakai berumur lima menit, lalu konfirmasi kepemilikan dari web. Username/password tidak diminta di chat.
- Akses Warga, Petugas, dan Bendahara tetap berlaku tanpa batas waktu otomatis selama tautan, akun, dan hak akses masih sah. Tidak ada pembatasan 30 hari, 8 jam, atau 60 menit tidak aktif; pengguna tidak harus kembali ke web karena lama tidak memakai bot. Status akun, peran, wilayah, claimant, status layanan, dan idempotensi tetap diperiksa saat tindakan. Perubahan data keamanan akun (termasuk kata sandi/peran) tetap memerlukan verifikasi ulang, bukan pembaruan berkala.
- Tautan awal sekali pakai tetap berlaku lima menit, tombol 15 menit, dan isian percakapan sementara 30 menit. Ini batas data sementara, bukan masa akses akun. Jika tombol/isian kedaluwarsa, cukup ketik `/menu` dan lanjutkan dari bot; tidak ada transaksi otomatis dari isian lama.
- `/keluar` hanya menjelaskan cara memutus tautan melalui web dan tidak mengakhiri akses dengan sendirinya. Putuskan Telegram di web atau pencabutan oleh Admin yang berwenang benar-benar menghentikan akses dan membatalkan kiriman tertunda. Pesan yang sudah dikirim tidak dapat ditarik dari Telegram.

## Fitur

Menu umum menyediakan katalog/foto/harga, perkiraan nilai, paket aktif, jadwal layanan keliling, pengumuman, target yang dipublikasikan, dan panduan. Data publik tetap tunduk pada batas privasi web.

- Warga: saldo tersedia/disisihkan dan ringkasan mutasi; riwayat/rincian; kartu QR tanpa memutar token; pickup dengan foto/perkiraan opsional; pencairan tunai; sembako; status serta pembatalan yang sesuai tahap.
- Petugas: antrean/tugas pickup; buka layanan keliling dan lihat rekap/kapasitas; cari Warga sesuai area; catat timbang/finalisasi setoran langsung, pickup, atau keliling; klaim/persiapan opsional/serah-terima sembako; riwayat pekerjaan sendiri.
- Bendahara: antrean pembayaran searea, klaim saat tunai tersedia, tinjau penerima/nominal dan satu konfirmasi setelah serah fisik, riwayat pembayaran sendiri, ringkasan periode, dan Excel pembayaran sendiri.
- Notifikasi transaksi, pengingat, ringkasan saldo, serta langganan pengumuman adalah pilihan terpisah dan **mati sampai pengguna memilihnya**. Frekuensi ringkasan dapat diatur. Pengingat dikirim maksimal satu kali per hari pada pukul 09.00 WIB, dan kiriman terjadwal (pengingat/ringkasan/pengumuman) menghormati jam tenang 20.00–07.00 WIB. Pengingat dibatasi dan kiriman ditinjau ulang terhadap izin/status sebelum dikirim.
- Foto bot tidak wajib, format/ukuran diperiksa, disimpan di disk privat, dan tidak pernah menjadi bukti independen bahwa serah fisik terjadi.

Semua mutasi transaksi memakai layanan domain yang sudah digunakan web. Membuka saldo/informasi tidak membuat draft, hold, kartu baru, atau transaksi. Pengajuan pencairan/sembako menahan saldo; mutasi keluar hanya terjadi setelah penyelesaian fisik yang sah.

## Konfigurasi dan operasi host

1. Kebijakan privasi v2.1 tertanggal 28 Oktober 2026 menyelaraskan akses persisten dan petunjuk pemutusan. Publikasikan versi tersebut bersama rilis ini dan gunakan `TERMS_VERSION=v2.1` bila environment hosting mengatur versi secara eksplisit. Riwayat persetujuan v1.0/v2.0 tidak diubah dan tidak ada persetujuan ulang massal otomatis.
2. Buat bot melalui BotFather dan simpan token hanya di `.env` privat. Isi `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` (tanpa `@`), serta `TELEGRAM_WEBHOOK_SECRET` acak minimal 32 karakter. Jangan kirim token lewat chat, commit, screenshot, atau command argument.
3. Pastikan `APP_URL` adalah HTTPS publik; backup database/media, unggah build yang sama dengan source, lalu jalankan migration biasa melalui prosedur deployment. Migration 28 Oktober membuat penanda waktu lama nullable/tidak aktif; tidak menghapus tautan, generation, consent, akun, saldo, atau transaksi dan tidak memerlukan reset/seed. Selama mengganti kode dan menjalankan migration, gunakan maintenance sementara.
4. Set `TELEGRAM_ENABLED=true` hanya setelah langkah sebelumnya disetujui; bersihkan config cache, lalu jalankan `php artisan telegram:diagnose`. Pemeriksaan jaringan opsional: `php artisan telegram:diagnose --network` (memanggil `getMe` dan `getWebhookInfo`, tidak mengubah webhook).
5. Daftarkan webhook dengan `php artisan telegram:webhook`. Perintah menolak URL non-HTTPS/localhost dan mencocokkan username token. Jalur webhook hanya menerima chat pribadi dan tidak memakai sesi/CSRF browser.
6. Cron host tetap harus memanggil `php artisan schedule:run` setiap menit. Saat fitur aktif, scheduler memproses batch update/outbox tiap menit, pengingat/ringkasan tiap lima menit, dan retensi harian. Belum diperlukan worker daemon terpisah untuk konfigurasi ini.
7. Uji penautan serta tugas setiap role pada akun uji, verifikasi pencabutan/consent/media dan retry, lalu lakukan UAT dengan warga/pengurus sebelum penerapan. Tes lokal atau API mock bukan bukti webhook live, usability, SUS, ataupun persetujuan desa.

Untuk menghentikan dengan tertib, saat token masih tersedia jalankan `php artisan telegram:webhook --remove`, kemudian set `TELEGRAM_ENABLED=false` dan bersihkan config cache. Hapus token dari environment jika tidak lagi dipakai. Perintah penghapusan webhook mempertahankan update Telegram yang tertunda.

## Verifikasi lokal

Tes Telegram dijalankan dengan `vendor/bin/pest tests/Feature/Telegram`. Tes otomatis memakai SQLite `:memory:` atau MySQL loopback database khusus `bank_sampah_test_telegram`; `tests/bootstrap.php` menolak nama database lain. Jangan gunakan database `bank_sampah` atau hosting untuk tes yang dapat membuat/mengubah transaksi. Jalankan juga suite regresi web, Pint, dan PHPStan sebelum review.

Kredensial nyata tidak dicantumkan di repo. Pengujian lokal/mocked tidak membuktikan bahwa rilis ini sudah diunggah atau semua alur di hosting bekerja. UAT dan revisi naskah skripsi tetap pekerjaan tersendiri.

### Bukti lokal perubahan 28 Oktober 2026

- Regresi penuh SQLite: 1.377 tes lulus, satu dilewati; 6.490 assertions.
- Telegram: 66 tes/303 assertions lulus pada SQLite dan MySQL loopback disposable `bank_sampah_test_telegram_20261028`. Uji migration mencakup skema lama yang belum nullable, mempertahankan identitas/consent, serta down/up terisolasi.
- PHPStan tanpa error, Pint, pemeriksaan whitespace, serta build Vite lulus. Manifest merujuk delapan aset yang seluruhnya tersedia, tanpa `public/hot`.
- Tidak ada migration/reset/transaksi uji di database utama, upload hosting, atau klaim UAT/SUS yang dilakukan pada tahap ini.
