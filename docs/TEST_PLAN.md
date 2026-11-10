# Rencana pengujian cutover

## Pencairan digital simulasi

Jalankan `tests/Feature/Payouts` untuk pengajuan web/Telegram, enam tujuan, server-side confirmation, consent, kepemilikan/area, hold, idempotensi, timeout/pemulihan, callback terautentikasi, penyelesaian/reversal, notifikasi, CSV masked, dan pemisahan kas fisik. Runner `tests/browser/payout-smoke.mjs` memakai database MySQL khusus, API tiruan, serta screenshot responsif dan pemeriksaan axe. Ini bukan uji akun provider; penerimaan Xendit Test Mode harus dicatat terpisah. [Petunjuk](PAYOUT_SANDBOX.md).

## Otomatis

Tes kanal Telegram mencakup penautan 1:1, persetujuan web, akses setelah 400 hari tidak aktif pada tiga role, pengajuan pickup tanpa kembali ke web, pemulihan tombol melalui /menu, migrasi tautan lama tanpa mengubah identitas/consent, pencabutan dan generation lama, role/area/claim, idempotensi, media privat, outbox/retry, opt-in notifikasi, privasi target, dan laporan Bendahara sendiri. Jalankan `vendor/bin/pest tests/Feature/Telegram`; suite wajib lulus di SQLite in-memory dan di MySQL database disposable bernama `bank_sampah_test_telegram`. Jangan arahkan tes ke `bank_sampah`. Tes mocked API bukan bukti webhook publik atau UAT.

- Unit/feature: role scalar, constraint negatif, autentikasi/sesi, middleware persisten, scope, setoran, ledger/hold, file privat, laporan dan master.
- Cutover: routine tanpa foto, claim eksklusif, stale claimant/nonaktif/area dicabut, release mempertahankan hold, cancel melepas sekali, tidak tersedia, retry dan payload konflik.
- Exception: kunci, reautentikasi, bukan pihak transaksi, pemulihan claimant yang dicabut, pengalihan pickup dan konversi tepat sekali.
- MySQL: fresh migration/seed, counter Admin dan dua koneksi dengan snapshot lama serta klaim bersaing.
- Seeder: sumber privat literal, referensi/peran/profil, hash dipertahankan, QR baru, sumber hilang/tidak valid tanpa mutasi, penolakan overwrite, bootstrap production tanpa eskalasi, dan fixture hanya pada database disposable.
- Browser: empat role, mobile/desktop, tidak overflow, pengajuan/claim/final tanpa foto, kuitansi dan panel Admin tanpa approval rutin.
- Build, formatter, static analysis, generator PDF, validasi layout dan review independen.

Tes memakai storage/cache/sesi terpisah. MySQL dan browser menolak database selain `bank_sampah_test` atau suffix-nya; browser memerlukan loopback dan token preflight yang cocok.

Runner workflow/konkurensi memilih `Tests\Fixtures\WorkflowFixture` secara eksplisit setelah migrasi, bukan `DatabaseSeeder` aplikasi. Konverter: `node --test tests/browser/master-data-extraction.test.mjs`. Impor dataset pemilik di database uji: `tests/mysql/master-data-smoke.php`, dengan bootstrap/target testing loopback dan JSON privat yang tersedia. [Panduan seeder](SEEDING.md), [bukti terbaru](VALIDATION_MASTER_DATA_2026-10-09.md).

## Penerimaan pengguna

Gunakan [lembar UAT](UAT_EVIDENCE.md); peserta desa nyata belum dapat digantikan tes otomatis. Catat bantuan, salah klik, keberhasilan, waktu, dan pemahaman status. Jangan mengarang skor SUS.

## Regresi audit UI 8 Oktober 2026

`tests/Feature/UiSimplification` memeriksa hidrasi edit tanpa perubahan, cakupan target, pilihan konteks terotorisasi, anti-tamper bukti/laporan dan rotasi QR pada komponen terbuka, draf bukan kuitansi, akun+tugas atomik, pergantian tugas eksplisit, rekonsiliasi satu Admin, form pickup tanpa tahapan wajib, format angka lokal, navigasi Admin, lookup/pagination, kunci pickup, dan batas operasi teknis. MySQL tetap memakai database uji khusus. Tes lama yang mengharuskan layout/tahapan yang telah diganti diselaraskan dengan kontrak baru, bukan dengan melonggarkan domain keuangan.

Tambahan: input/error dua record rekonsiliasi, error tindakan setelah render/filter, akhir halaman dua antrean, kandidat retensi berubah walau count sama, token preview yang sudah dipakai, dan nominal paket 20.000 tersimpan 20000.

Regresi 21 Oktober: riwayat Petugas hanya pekerjaan sendiri (bukan seluruh area), pembatasan role/ID/jenis riwayat, pagination dan pencarian, tautan sembako selesai, bukti pickup setelah reload tanpa mutasi baru, perkiraan opsional bukan nol, status maskot/badge, dan satu konfirmasi nama/nominal sebelum pencatatan tunai/sembako. Pengujian dialog browser mencakup Periksa lagi tanpa pencatatan dan konfirmasi yang berhasil. Perbaikan ini tidak mengklaim foto katalog yang belum diunggah telah tersedia.

Browser tambahan: `tests/browser/seed-ui.php` membuat fixture pada database uji yang diminta, tanpa reset; `ui-simplification-smoke.mjs` memeriksa empat breakpoint, hub layanan, satu form, konteks keliling, edit data tersimpan, dan pemeriksaan satu Admin. Jalankan sesudah fixture baseline atau nyatakan prasyarat cash dari fixture aktual. [Hasil](IMPLEMENTATION_UI_2026-10-08.md).
