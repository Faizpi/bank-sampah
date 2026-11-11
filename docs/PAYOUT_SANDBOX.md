# Pencairan digital mandiri (Xendit Test Mode)

Implementasi 10 November 2026 tersedia pada source web dan Telegram. Fitur nonaktif secara default. Tidak ada API Key Xendit nyata pada pengujian ini, pengiriman uang riil, perubahan database utama, atau deployment. Uji provider sebenarnya dan persetujuan peserta bukan hasil yang boleh disimpulkan dari tes otomatis.

## Alur dan batas

1. Pada web, Warga membuka satu menu Cairkan Saldo, lalu memilih Tunai atau Digital (simulasi). Tujuan, nama/nomor tujuan uji, nominal, dan area bila dilayani beberapa area diisi setelah memilih digital. Tidak meminta lokasi/tanggal pengambilan tunai. Ganti metode mengembalikan pengguna ke pilihan awal; tidak ada kartu/tombol digital tambahan pada Beranda atau Layanan.
2. Web/bot menampilkan ringkasan tujuan/nominal dan penggunaan data uji. Konfirmasi menyimpan versi `payout-sandbox-v1` dan waktu server. Nama tujuan tidak disalin otomatis dari identitas warga.
3. Pengajuan membuat satu hold melalui LedgerService. Saldo tersedia berkurang karena disisihkan, tetapi belum ada mutasi keluar. Tidak ada persetujuan atau klaim Bendahara/Admin.
4. Cron mengirim snapshot terenkripsi dengan reference/idempotency key tetap. Network call berada di luar transaksi database keuangan.
5. Sukses API terverifikasi mengonversi hold sekali. Gagal final melepas hold. REVERSED mencatat mutasi pengembalian baru, bukan mengubah mutasi lama. Webhook hanya menjadwalkan pemeriksaan GET.
6. Warga melihat status/riwayat/bukti simulasi di kedua kanal. Telegram mengirim notifikasi hanya bila diizinkan. Pembatalan hanya sebelum pengiriman. Bendahara memantau digital searea; Admin memantau semua melalui Pengawasan. Petugas tidak mengakses digital.

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

## Upload dan aktivasi hosting

Tidak ada dependency Composer/npm baru. Build perlu diunggah karena Blade memakai kelas tampilan baru.

- Folder privat aplikasi: perubahan pada `app/`, `bootstrap/app.php`, `config/payouts.php`, `config/app.php`, `routes/`, `resources/views/`, dan migration `2026_11_10_100000_create_sandbox_payout_tables.php`.
- Document root: `public/build/` (manifest beserta semua aset), `public/images/payouts/`, dan `public/deploy.php` bila masih dipakai. Pada hosting terpisah, isi public masuk `public_html`.
- Jangan upload .env lokal, cache lokal, fixture tes, data privat, atau DB uji. Pertahankan APP_KEY hosting agar data terenkripsi tetap terbaca.

Urutan pada instalasi riset yang disiapkan pemilik:

1. Backup, maintenance, upload source dan build yang sama. Jangan reset/seed database lama.
2. Set konfigurasi privat dan publikasi privasi v2.2; `TERMS_VERSION=v2.2` jika versi dikunci melalui environment.
3. Migration biasa (`php artisan migrate --force`), lalu clear/rebuild cache. deploy.php menyediakan aksi migration/release dan diagnosis sandbox.
4. `php artisan payouts:diagnose` memeriksa konfigurasi, bukan menghubungi provider. Routing terisi bukan bukti channel diterima Xendit.
5. Daftarkan URL HTTPS instalasi riset `/xendit/payout-webhook` di dashboard Xendit Test Mode dengan token yang cocok. Webhook Telegram berbeda dan tidak perlu dipasang ulang.
6. Cron memanggil `php artisan schedule:run` setiap menit. `payouts:work --limit=10` berjalan tiap menit saat fitur aktif, dengan lease/lock overlap dan batas waktu. Tidak perlu queue daemon baru. deploy.php menyediakan proses manual maksimal lima pengajuan.
7. Uji akun provider sebenarnya untuk setiap tujuan yang akan digunakan. Catat hasil/provider ID, webhook, gagal, duplikat, timeout, reversal tanpa mempublikasikan data/rahasia. Bukti otomatis tiruan bukan pengganti langkah ini.

Jangan mematikan cron ketika permintaan masih pending. Selesaikan pemeriksaan hasil/hold sebelum menonaktifkan fitur; menonaktifkan fitur tidak membatalkan transaksi atau melepas hold otomatis.

## Pemulihan status tidak pasti

Dengan ID provider, worker memeriksa GET otomatis. Tanpa ID setelah timeout, status menjadi `uncertain`, saldo tetap ditahan dan worker tidak mengirim POST baru otomatis. Warga tidak boleh mengajukan ulang untuk pembayaran yang sama. Periksa dashboard Test Mode/status provider terlebih dahulu.

Operator teknis dapat menjalankan `php artisan payouts:recover ID --reference=REFERENSI_PENGAJUAN` setelah memeriksa referensi, minimal satu menit dari upaya terakhir. Perintah menolak transaksi yang bukan uncertain, sudah mempunyai ID provider, atau masih ber-lease. Tidak menerima nomor rekening, nominal, atau key baru; request memakai body dan idempotency key asli. Jangan memulihkan dengan key baru atau mengubah DB manual. Audit mencatat pemulihan sebagai sistem. Upaya tetap dapat gagal sampai konfigurasi provider dibenahi.

Respons yang tidak cocok tidak boleh mengubah saldo. Jangan melepas hold hanya karena exception/HTTP 4xx atau 5xx. Status provider yang belum dikenali tetap menunggu pemeriksaan (fail closed).

## Verifikasi yang dapat direproduksi

- SQLite: `php vendor/bin/pest tests/Feature/Payouts --compact`.
- MySQL: DB disposable `bank_sampah_test_*` loopback, lalu suite payout dan regresi Telegram/tunai/sembako/rekonsiliasi. Bootstrap tes menolak nama DB utama.
- Browser: set `E2E_PHP_BINARY` dan opsional `E2E_DB_PORT`, siapkan DB kosong khusus `bank_sampah_test_ui_payouts_20261110`, lalu `node tests/browser/payout-smoke.mjs`. Runner memakai fixture sintetik, API tiruan, screenshot responsif, dan axe.
- Pint, PHPStan, serta `npm run build`.

UAT/SUS, perangkat nyata, akses rekening/e-wallet sebenarnya, dan deployment tidak dibuktikan oleh pengujian lokal.

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
