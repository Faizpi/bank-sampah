# Pencairan digital mandiri (Xendit Test Mode)

Implementasi 10 November 2026 tersedia pada source web dan Telegram. Fitur nonaktif secara default. Tidak ada API Key Xendit nyata pada pengujian ini, pengiriman uang riil, perubahan database utama, atau deployment. Uji provider sebenarnya dan persetujuan peserta bukan hasil yang boleh disimpulkan dari tes otomatis.

## Alur dan batas

1. Pada web, Warga membuka satu menu Cairkan Saldo, lalu memilih Tunai atau Digital (simulasi). Tujuan, nama/nomor tujuan uji, nominal, dan area bila dilayani beberapa area diisi setelah memilih digital. Tidak meminta lokasi/tanggal pengambilan tunai. Ganti metode mengembalikan pengguna ke pilihan awal; tidak ada kartu/tombol digital tambahan pada Beranda atau Layanan.
2. Web/bot menampilkan ringkasan tujuan/nominal dan penggunaan data uji. Konfirmasi menyimpan versi `payout-sandbox-v1` dan waktu server. Nama tujuan tidak disalin otomatis dari identitas warga.
3. Pengajuan membuat satu hold melalui LedgerService. Saldo tersedia berkurang karena disisihkan, tetapi belum ada mutasi keluar. Tidak ada persetujuan atau klaim Bendahara/Admin.
4. Cron mengirim snapshot terenkripsi dengan reference/idempotency key tetap. Network call berada di luar transaksi database keuangan.
5. Sukses API terverifikasi mengonversi hold sekali. Gagal final melepas hold. Penolakan pembuatan pada upaya pertama dengan kode error API yang dikenal dapat menutup pengajuan tanpa mutasi keluar, hanya jika belum ada ID provider atau webhook. REVERSED mencatat mutasi pengembalian baru, bukan mengubah mutasi lama. Webhook hanya menjadwalkan pemeriksaan GET.
6. Warga melihat status/riwayat/bukti simulasi di kedua kanal. Riwayat web menyatu pada `/warga/riwayat-pencairan` dengan pilihan Tunai dan Digital (simulasi), filter nomor/status/tanggal, serta URL lama yang diarahkan ke `?metode=digital`. Telegram memakai Riwayat lalu Pencairan lalu pilihan metode. Telegram mengirim notifikasi hanya bila diizinkan. Pembatalan hanya sebelum pengiriman. Bendahara memakai satu halaman laporan dengan pilihan Tunai/Digital dan ekspor Excel; tunai tetap pembayaran sendiri, digital mengikuti wilayah tugas aktif. Admin membaca detail pada modal tabel backoffice dan dapat memilih kategori digital pada laporan. URL pantauan bersama lama diarahkan sesuai peran. Petugas tidak mengakses digital.

Enam tujuan: BCA, Mandiri, BRI, BNI, DANA, GoPay. Logo asli lokal hanya penanda, bukan klaim afiliasi. Dukungan dan routing v3 harus dikonfirmasi pada akun Xendit. Belum ada bukti keenam tujuan diterima akun provider milik peneliti. Tujuan tanpa routing tidak dapat diajukan. Jangan menyalin `bank_code` API lama ke routing v3 secara asumsi.

Digital memakai `SandboxPayout`, bukan `WithdrawalRequest`, sehingga klaim/serah tunai tidak dapat menyelesaikannya. Tunai dan sembako tetap tersedia. Angka digital bukan pengeluaran kas fisik pada rekonsiliasi; ada pembanding ledger digital tersendiri.

## Penetapan database simulasi

Untuk melihat tampilan saja pada instalasi `APP_ENV=local`, jalankan migration tambahan lalu set `PAYOUT_SANDBOX_PREVIEW=true` dan pertahankan `PAYOUT_SANDBOX_ENABLED=false` di .env lokal. Beranda dan Layanan tetap memakai satu menu Cairkan Saldo; pilihan Tunai dan Digital (simulasi) berada di dalamnya. Enam tujuan digital dapat dipilih untuk melihat label/isian, tetapi pengajuan dinonaktifkan dan server tetap menolak pembuatan hold maupun pengiriman API. Mode ini tidak memerlukan key/routing palsu, tidak mengubah saldo, dan diabaikan pada production. Matikan preview sebelum menyiapkan uji provider pada database simulasi yang dikonfirmasi.

Keputusan pemilik pada 11 November 2026: database hosting yang sudah ada boleh dipakai karena seluruh data dan saldo masih dummy serta sistem belum digunakan secara operasional di desa. Web dan bot memakai ledger/hold yang sama pada database itu. Simulasi dapat benar-benar mengubah catatan saldo aplikasi, tetapi tidak mengirim dana riil. Jangan mengaktifkan fitur ini pada saldo operasional Warga.

Gerbang mensyaratkan nama database aktual sama persis dengan `PAYOUT_SANDBOX_DATABASE`; tidak ada kewajiban akhiran nama tertentu. Untuk hosting pemilik, `DB_DATABASE` tetap `smssinde_banksampah` dan `PAYOUT_SANDBOX_DATABASE=smssinde_banksampah`. Kunci Development, token webhook, migration, role/area, hold, dan idempotensi tetap wajib. SQLite `:memory:` memiliki pengecualian konfirmasi nama hanya saat testing. Tes otomatis tetap memakai database disposable dan API tiruan, bukan database hosting atau saldo operasional.

## Konfigurasi privat

Nama variabel tersedia di `.env.example`:

| Variabel | Nilai/tujuan |
| --- | --- |
| `PAYOUT_SANDBOX_ENABLED` | `false` sampai konfigurasi dan uji provider selesai |
| `PAYOUT_SANDBOX_DATABASE` | Nama persis database riset yang dipakai aplikasi |
| `XENDIT_PAYOUT_SECRET_KEY` | Development Secret API Key berawalan `xnd_development_` dengan izin MONEY-OUT |
| `XENDIT_PAYOUT_WEBHOOK_TOKEN` | Token verifikasi callback akun Xendit (minimal 16 karakter), bukan token Telegram |

Enam variabel routing terpisah: `XENDIT_PAYOUT_ROUTING_BCA`, `XENDIT_PAYOUT_ROUTING_MANDIRI`, `XENDIT_PAYOUT_ROUTING_BRI`, `XENDIT_PAYOUT_ROUTING_BNI`, `XENDIT_PAYOUT_ROUTING_DANA`, `XENDIT_PAYOUT_ROUTING_GOPAY`. Isi identifier dari dynamic schema v3 akun Test Mode, bukan contoh kode tiruan dari tests.

Rahasia tidak masuk chat, GitHub, URL, screenshot, atau command argument. Base URL dipatok ke `https://api.xendit.co`; Development key membedakan Test Mode. Request memakai `api-version: 2025-09-01`, IDR domestik, routing SWIFT untuk bank dan WALLET untuk e-wallet. Validasi dynamic schema, kemampuan akun, dan hasil sandbox harus diperiksa sebelum aktivasi. Jika akun/channel belum mendukung v3, jangan mengaktifkan dengan kode rekaan atau mengklaim berhasil.

Nama uji harus memenuhi given name/surname v3. Gunakan contoh dua kata seperti Siti Aminah; setiap bagian maksimal 50 karakter. Sistem tidak memalsukan nama belakang. Ini batas formulir riset, bukan kebijakan kependudukan desa.

Request v3 menyertakan objek `recipient.address` lengkap untuk jalur Test Mode: country, street_line_1, city, province_state, dan postal_code. Nilainya merupakan fixture alamat penelitian yang fiktif pada `config/payouts.php`, bukan alamat individu atau alamat resmi desa. Alamat rumah dari profil Warga tidak disalin. Konfigurasi alamat tidak lengkap ditolak sebelum membuat hold. Jalur ini tetap menolak kunci produksi. Kesesuaian dengan persyaratan khusus akun/channel masih perlu dibuktikan pada Test Mode, bukan diasumsikan dari tes tiruan.

Pemeriksaan hosting 13 November menemukan penolakan `recipient.address` setelah request yang hanya berisi country masih ditolak. Pelengkapan alamat ini berlaku bagi pengajuan baru; snapshot dan key pengajuan lama tidak diubah. Pengajuan GoPay berikutnya terpantau berhasil pada dashboard Xendit Test Mode. Pada pemeriksaan setelahnya, status website masih belum sinkron; hasil implementasi perbaikan di hosting harus diverifikasi terpisah.

## Upload dan aktivasi hosting

Tidak ada dependency Composer/npm baru. Build perlu diunggah karena Blade memakai kelas tampilan baru.

- Folder privat aplikasi: perubahan pada `app/`, `bootstrap/app.php`, `config/payouts.php`, `config/app.php`, `routes/`, `resources/views/`, dan migration `2026_11_10_100000_create_sandbox_payout_tables.php`.
- Document root: `public/build/` (manifest beserta semua aset), `public/images/payouts/`, dan `public/deploy.php` bila masih dipakai. Pada hosting terpisah, isi public masuk `public_html`.
- Jangan upload .env lokal, cache lokal, fixture tes, data privat, atau DB uji. Pertahankan APP_KEY hosting agar data terenkripsi tetap terbaca.

Urutan pada instalasi riset yang disiapkan pemilik:

1. Backup, maintenance, upload source dan build yang sama. Jangan reset/seed database lama.
2. Set konfigurasi privat dan publikasi privasi v2.2; `TERMS_VERSION=v2.2` jika versi dikunci melalui environment.
3. Migration biasa (`php artisan migrate --force`), lalu clear/rebuild cache. deploy.php menyediakan aksi migration/release dan diagnosis sandbox.
4. `php artisan payouts:diagnose` memeriksa konfigurasi dan snapshot. Untuk status tidak pasti yang sudah memiliki ID provider, perintah juga membaca status dengan GET tanpa mengubah saldo atau mengirim payout baru. Routing terisi bukan bukti channel diterima Xendit.
5. Daftarkan URL HTTPS instalasi riset `/xendit/payout-webhook` pada pengaturan Payouts v3 di dashboard Xendit Test Mode dengan token yang cocok. Lima nama `v3_payout.*` adalah nilai event JSON, bukan lima URL yang harus diisi jika dashboard hanya menyediakan satu kolom Payouts v3. Payload contoh dashboard bukan transaksi aplikasi; referensi non-UUID atau transaksi tidak dikenal tetap ditolak. Webhook Telegram berbeda dan tidak perlu dipasang ulang.
6. Cron memanggil `php artisan schedule:run` setiap menit. `payouts:work --limit=10` berjalan tiap menit saat fitur aktif, dengan lease/lock overlap dan batas waktu. Tidak perlu queue daemon baru. deploy.php menyediakan proses manual maksimal lima pengajuan.
7. Uji akun provider sebenarnya untuk setiap tujuan yang akan digunakan. Catat hasil/provider ID, webhook, gagal, duplikat, timeout, reversal tanpa mempublikasikan data/rahasia. Bukti otomatis tiruan bukan pengganti langkah ini.

Jangan mematikan cron ketika permintaan masih pending. Selesaikan pemeriksaan hasil/hold sebelum menonaktifkan fitur; menonaktifkan fitur tidak membatalkan transaksi atau melepas hold otomatis.

## Pemulihan status tidak pasti

Dengan ID provider, worker memeriksa GET otomatis. Tanpa ID setelah timeout, status menjadi `uncertain`, saldo tetap ditahan dan worker tidak mengirim POST baru otomatis. Warga tidak boleh mengajukan ulang untuk pembayaran yang sama. Periksa dashboard Test Mode/status provider terlebih dahulu.

Operator teknis dapat menjalankan `php artisan payouts:recover ID --reference=REFERENSI_PENGAJUAN` setelah memeriksa referensi, minimal satu menit dari upaya terakhir. Perintah menolak transaksi yang bukan uncertain, sudah mempunyai ID provider, atau masih ber-lease. Tidak menerima nomor rekening, nominal, atau key baru; request memakai body dan idempotency key asli. Jangan memulihkan dengan key baru atau mengubah DB manual. Audit mencatat pemulihan sebagai sistem. Upaya tetap dapat gagal sampai konfigurasi provider dibenahi.

Respons yang tidak cocok tidak boleh mengubah saldo. Timeout, HTTP 409/429/5xx, body tidak valid, kode error tidak dikenal, kegagalan GET, serta penolakan pada upaya pemulihan setelah hasil sebelumnya tidak pasti tetap menahan saldo. Penolakan POST pertama dengan kode yang didokumentasikan (`API_VALIDATION_ERROR`, `INVALID_VALUE_ERROR`, `INVALID_QUOTE`, `INVALID_API_KEY`, `REQUEST_FORBIDDEN_ERROR`, `INVALID_MERCHANT_SETTINGS`, atau `DATA_NOT_FOUND`, sesuai status HTTP) dapat melepas hold hanya jika belum ada ID provider, webhook, atau mutasi. Tidak ada POST ulang otomatis.

Diagnosis dan kode kegagalan menyimpan hanya status HTTP, kode provider yang diizinkan, serta satu nama field yang diizinkan. Pesan/body mentah provider, nilai isian, API key, dan header tidak disimpan. Rincian teknis hanya berada pada konsol terlindungi. Halaman Warga, Bendahara, dan backoffice memakai pesan umum yang mudah dibaca melalui `failureNotice`, bukan kode error atau nama field API. `payouts:diagnose` menampilkan lima kegagalan terbaru beserta pemeriksaan snapshot; untuk status tidak pasti dengan ID provider, GET hanya menampilkan status yang dikenal dan hasil kecocokan field tanpa nilai privat.

Diagnostik respons GET Xendit pada hosting menunjukkan `source_amount` tidak ada atau bernilai null, walaupun nominal tujuan, ID, referensi, mata uang dan rincian tujuan cocok. Pencocokan GET menerima kolom sumber yang tidak dikirim atau bernilai null, tetapi menolak nilainya jika dikirim dan berbeda. Nilai tujuan, identitas dan, bila belum pernah tervalidasi, rincian akun penerima tetap wajib cocok sebelum hold dikonversi. Pengujian lokal menggunakan respons tiruan bukan bukti bahwa rekonsiliasi hosting telah berhasil.

Kasus lama `payout.http_400` dapat ditutup melalui `payouts:resolve-rejected NOMOR_SIM --confirm-not-created` setelah operator memastikan pengajuan tidak tercatat pada Xendit Test Mode. deploy.php menyediakan aksi **Tutup pengajuan ditolak Xendit (HTTP 400)** dengan nomor SIM persis dan frasa konfirmasi. Aksi hanya menerima upaya pertama, tanpa ID/business provider, webhook, lease, atau mutasi; bukan jalan pintas untuk timeout atau hasil yang ambigu. Hold dilepas sekali, status menjadi gagal, audit ditambahkan, snapshot/key tidak diubah, dan tidak ada panggilan provider. Pengajuan baru dilakukan setelah penyebab penolakan diperbaiki dan pengajuan lama benar-benar ditutup.

## Verifikasi yang dapat direproduksi

- SQLite: `php vendor/bin/pest tests/Feature/Payouts --compact`.
- MySQL: DB disposable `bank_sampah_test_*` loopback, lalu suite payout dan regresi Telegram/tunai/sembako/rekonsiliasi. Bootstrap tes menolak nama DB utama.
- Browser: set `E2E_PHP_BINARY` dan opsional `E2E_DB_PORT`, siapkan DB kosong khusus `bank_sampah_test_ui_payouts_20261110`, lalu `node tests/browser/payout-smoke.mjs`. Runner memakai fixture sintetik, API tiruan, screenshot responsif, dan axe.
- Pint, PHPStan, serta `npm run build`.

UAT/SUS, perangkat nyata, akses rekening/e-wallet sebenarnya, dan deployment tidak dibuktikan oleh pengujian lokal.

## Verifikasi perbaikan 12 November 2026

Pengujian akhir pada database disposable MySQL 8.0.30 mencakup 160 tes dan 1.201 assertions untuk payout, penolakan/timeout, pemulihan terarah, riwayat gabungan, login, salinan antarmuka, dan konsol deployment. Seluruhnya lulus. Regresi seluruh aplikasi: 1.486 tes, 1.485 lulus, 1 dilewati oleh runner karena EXIF tidak tersedia pada PHP yang dipakainya, dengan 7.271 assertions. Tes orientasi JPEG yang dilewati tersebut dijalankan terpisah memakai PHP Laragon dengan EXIF dan lulus (1 tes, 2 assertions). Pint, PHPStan, pemeriksaan sintaks runner browser, dan build Vite lulus.

Pemeriksaan browser pada server uji terpisah membuktikan pergantian Tunai/Digital, filter status, tautan detail dan kembali ke riwayat, serta status memuat login selebar formulir: 301 px pada viewport 390 px dan 640 px pada viewport 1280 px. Status memuat memakai flex, bukan inline-block. Pemeriksaan tidak membuat transaksi pada hosting. Semua respons provider pada tes otomatis tetap tiruan; hasil pengiriman Xendit sebenarnya harus diuji setelah berkas perbaikan diunggah. Server web dan MySQL sementara telah dihentikan; database utama tidak diubah.

## Bukti pengujian lokal rilis ini

Penyelarasan UI 11 November 2026: satu pintu Cairkan Saldo, dua pilihan metode, aksi Ganti metode dan penghapusan tombol digital tambahan diuji ulang. Suite SQLite terakhir: 1.435 tes, 1.434 lulus, 1 dilewati (ekstensi EXIF), 6.896 assertions. Pint, PHPStan, dan build lulus. Navigasi Tunai/Ganti metode/Digital diverifikasi pada Laragon `bank-sampah.test` di source yang sama, termasuk tampilan ponsel 390 px tanpa overflow horizontal. Pengujian browser ini tidak mengirim pengajuan atau mengubah saldo.

Pemeriksaan dimulai 10 November dan diselesaikan 11 November 2026 (Asia/Jakarta). Tes fitur payout terakhir pada MySQL 8.0.30 Laragon di database disposable: 51 tes, 313 assertions, lulus. Regresi MySQL mencakup 125 tes/648 assertions untuk payout, Telegram, tunai, sembako, dan rekonsiliasi; pengaman pra-migrasi diuji lagi melalui suite payout terakhir. Pint/PHPStan dan build Vite lulus. Browser Chromium headless menyelesaikan delapan layar/alur pada lebar 360/390/768/1280, tanpa error JS/HTTP 500 atau temuan axe serius/kritis pada layar yang diuji. Artefak lokal tersimpan di `artifacts/payouts-2026-11-10` dan tidak dipublikasikan.

Suite baseline sebelum penyelarasan UI di SQLite: 1.429 tes, 1.428 lulus, 1 dilewati, 6.823 assertions. EXIF tidak tersedia pada PHP suite SQLite, sehingga tes orientasi foto kamera dilewati, bukan dinyatakan lulus. Hasil penyelarasan UI terbaru tercatat pada paragraf di atas.

Semua respons Xendit/Telegram pada pengujian ini tiruan. Laragon utama tetap berjalan; data `bank_sampah`, hosting, dan akun provider tidak diubah. Instance MySQL sementara port 3311 telah dihentikan; database uji disposable dan screenshot lokal tetap merupakan artefak pengujian, bukan data desa atau bukti UAT.

## Referensi provider

- [Create Payout v3](https://docs.xendit.co/apidocs/create-payout-v3): autentikasi, nama, request dan idempotency.
- [Get Payout v3](https://docs.xendit.co/apidocs/get-payout-v3-by-id): rekonsiliasi hasil.
- [Payout lifecycle](https://docs.xendit.co/docs/payout-status-lifecycle): hasil dan pembalikan.
- [Test scenarios](https://docs.xendit.co/docs/test-scenarios-payouts): simulasi, bukan validasi rekening nyata.
- [Handling webhooks](https://docs.xendit.co/docs/handling-webhooks): verifikasi callback.
- [Payout webhook v3](https://docs.xendit.co/apidocs/payout-v3-webhook): lima event yang diterima endpoint.
- [Sumber logo lokal](../public/images/payouts/SOURCES.md).
