# Validasi cutover empat role

Tanggal 5 Oktober 2026. Baseline sebelum perubahan: `13310a6`. Ini bukti eksekusi cutover, bukan hasil checkpoint sebelumnya.

## Hasil teramati

| Pemeriksaan | Hasil |
| --- | --- |
| Unit/feature SQLite | 1.151 tes: 1.150 lulus, satu dilewati karena keterbatasan izin direktori Windows; 5.420 assertions; tanpa kegagalan/error |
| Cutover MySQL 8.0.30 | 76 lulus, 158 assertions |
| Dua koneksi MySQL | Empat skenario lulus: snapshot Admin lama, klaim bersaing, area dicabut dengan snapshot lama, saldo terkini setelah snapshot lama |
| Browser | 12/12 skenario empat role mobile/desktop; tidak ada page error/HTTP 5xx atau horizontal overflow |
| Ledger hasil browser | Enam entry, tepat dua outflow, semua hold selesai/dilepas, tanpa media wajib, saldo akhir simulasi Rp83.000 |
| Browser preflight guard | Dua pemeriksaan lulus |
| PHPStan | Nol error |
| Pint | Lulus |
| Vite | Build lulus |
| PDF | Proposal lima halaman, flowchart 12 halaman/sembilan diagram; generator/layout/batas halaman dan pemeriksaan visual lulus |
| Review independen core | Code-reviewer APPROVE; architect CLEAR setelah perbaikan blocker |

Pengujian memakai PHP Laragon 8.5.7 dan MySQL/InnoDB 8.0.30. Minimum deklarasi proyek PHP 8.3+ bukan klaim pengujian lintas semua versi. Satu skip adalah tes perubahan writability direktori yang tidak diwakili Windows; tidak ada fitur produk yang sengaja dilewati untuk menutupi kegagalan.

## Temuan yang diperbaiki selama verifikasi

Pengaman status SQLite sempat hilang saat rebuild tabel; pengaman dikembalikan. Antrean pickup sempat tersembunyi/berada di slot non-interaktif; dipindahkan ke root Livewire dan dibuktikan browser. Snapshot MySQL lama dapat melewati hitungan Admin/saldo/area; mutation memakai current locking read dan counter Admin database.

Permintaan batal setelah claim tidak langsung melepas hold karena race serah fisik. Konfirmasi claimant hanya untuk asal `cancellation_request`; eskalasi fisik/Admin menutupnya. UI retry memakai lookup terminal yang terotorisasi dan waktu kuitansi tersimpan, bukan waktu retry.

## Reset dummy lokal

Dry run memverifikasi tepat `bank_sampah` pada 127.0.0.1 dan root `storage/app/media` di workspace. Sebelum reset: 23 akun dummy; setoran, ledger, hold, pickup, pencairan, sembako, dan media kosong. Backup SQL checkpoint berada di luar repository, SHA256 `68EA28B387AF810726D3FB7E1C763B5A682A5EA787102C4DDC688DD2AC1AE218`.

Command berkonfirmasi berhasil. Hasil: empat akun aktif, counter Admin satu, dua assignment area, empat harga, satu paket, dan seluruh tabel transaksi kosong. Tabel role/permission/pivot dan layanan atas nama Warga tidak ada. Yang dihapus adalah baseline dummy lokal, bukan database produksi. Backup dapat dipakai bersama kode checkpoint untuk pemulihan.

## Batas bukti

Browser menggunakan data simulasi, bukan serah uang/paket nyata. UAT desa dan SUS belum dilakukan. Review independen terbatas pada core yang ditelaah; bukan persetujuan deployment. Tidak ada deployment, WhatsApp, atau bot Telegram yang diimplementasikan pada tahap ini.
