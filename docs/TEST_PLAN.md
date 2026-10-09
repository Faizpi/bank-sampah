# Rencana pengujian cutover

## Otomatis

- Unit/feature: role scalar, constraint negatif, autentikasi/sesi, middleware persisten, scope, setoran, ledger/hold, file privat, laporan dan master.
- Cutover: routine tanpa foto, claim eksklusif, stale claimant/nonaktif/area dicabut, release mempertahankan hold, cancel melepas sekali, tidak tersedia, retry dan payload konflik.
- Exception: kunci, reautentikasi, bukan pihak transaksi, pemulihan claimant yang dicabut, pengalihan pickup dan konversi tepat sekali.
- MySQL: fresh migration/seed, counter Admin dan dua koneksi dengan snapshot lama serta klaim bersaing.
- Browser: empat role, mobile/desktop, tidak overflow, pengajuan/claim/final tanpa foto, kuitansi dan panel Admin tanpa approval rutin.
- Build, formatter, static analysis, generator PDF, validasi layout dan review independen.

Tes memakai storage/cache/sesi terpisah. MySQL dan browser menolak database selain `bank_sampah_test` atau suffix-nya; browser memerlukan loopback dan token preflight yang cocok.

## Penerimaan pengguna

Gunakan [lembar UAT](UAT_EVIDENCE.md); peserta desa nyata belum dapat digantikan tes otomatis. Catat bantuan, salah klik, keberhasilan, waktu, dan pemahaman status. Jangan mengarang skor SUS.

## Regresi audit UI 8 Oktober 2026

`tests/Feature/UiSimplification` memeriksa hidrasi edit tanpa perubahan, cakupan target, pilihan konteks terotorisasi, anti-tamper bukti/laporan dan rotasi QR pada komponen terbuka, draf bukan kuitansi, akun+tugas atomik, pergantian tugas eksplisit, rekonsiliasi satu Admin, form pickup tanpa tahapan wajib, format angka lokal, navigasi Admin, lookup/pagination, kunci pickup, dan batas operasi teknis. MySQL tetap memakai database uji khusus. Tes lama yang mengharuskan layout/tahapan yang telah diganti diselaraskan dengan kontrak baru, bukan dengan melonggarkan domain keuangan.

Tambahan: input/error dua record rekonsiliasi, error tindakan setelah render/filter, akhir halaman dua antrean, kandidat retensi berubah walau count sama, token preview yang sudah dipakai, dan nominal paket 20.000 tersimpan 20000.

Browser tambahan: `tests/browser/seed-ui.php` membuat fixture pada database uji yang diminta, tanpa reset; `ui-simplification-smoke.mjs` memeriksa empat breakpoint, hub layanan, satu form, konteks keliling, edit data tersimpan, dan pemeriksaan satu Admin. Jalankan sesudah fixture baseline atau nyatakan prasyarat cash dari fixture aktual. [Hasil](IMPLEMENTATION_UI_2026-10-08.md).
