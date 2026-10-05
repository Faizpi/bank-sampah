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
