# Validasi Workflow Web 5 Oktober 2026

## Ruang lingkup

Validasi lokal pada kode aplikasi commit `5a0c773`, setelah perbaikan keamanan, konsistensi transaksi, dan penyederhanaan workflow. Baseline awal audit adalah `387612d`. Tidak ada bot Telegram, deployment, atau klaim UAT stakeholder dalam pekerjaan ini.

## Hasil yang dijalankan

| Pemeriksaan | Hasil |
| --- | --- |
| Composer check | Lulus: pemeriksaan format, analisis statis, dan suite tes |
| Pest dengan SQLite memori | 1.397 tes: 1.396 lulus, 1 dilewati, 7.256 assertion, tidak ada gagal/error |
| Transaksi dan keamanan pada MySQL 8.0.30 | 153 tes lulus, 731 assertion |
| Browser Chromium | 12 skenario lulus, tidak ada error JavaScript atau HTTP 500 yang tertangkap |
| Pengaman lingkungan browser | 2 tes lulus |
| PHPStan | 0 error |
| Build Vite | Lulus; manifest dan aset terbentuk |
| Generator flowchart dan proposal | Kedua pemeriksaan lulus |
| PDF proyek | Flowchart 41 halaman dan proposal 17 halaman dirender; batas halaman dan tata letak ditinjau |

Satu tes filesystem dilewati karena kemampuan sistem operasi, bukan dihitung sebagai lulus. Composer lokal mengeluarkan peringatan deprecation dependency Composer pada PHP 8.5.7; pemeriksaan aplikasi tetap selesai dengan exit code 0.

## Skenario browser

1. Login warga dan dashboard pada lebar 390 serta 1280 piksel.
2. Login petugas dan dashboard pada kedua lebar tersebut.
3. Login bendahara dan dashboard pada kedua lebar tersebut.
4. Login admin dan dashboard pada kedua lebar tersebut.
5. Login superadmin dan dashboard pada kedua lebar tersebut.
6. Alamat pickup terisi dari profil dan area tunggal tidak perlu dipilih lagi.
7. Admin menyetujui pencairan sekaligus menetapkan bendahara dalam satu dialog.
8. Admin menerima pickup sekaligus menetapkan petugas dan tanggal.
9. Petugas mencatat setoran dengan bukti dan satu konfirmasi akhir.
10. Bendahara mencatat tunai dengan nomor penerima, foto, dan satu konfirmasi akhir.
11. Petugas menandai sembako siap tanpa tahap Mulai siapkan.
12. Superadmin membuka konteks akun dan area tugas pada formulir terpadu.

Runner memeriksa overflow horizontal pada screenshot. Bukti lokal tersedia di `artifacts/workflow-audit-2026-10-05/results.json` dan screenshot pada folder yang sama. Artefak tersebut berisi data sintetis dan diabaikan Git; bukan bukti lapangan warga.

## Regresi yang ditambahkan

- Admin tanpa role.manage tidak dapat membuat atau mengambil alih superadmin.
- Health tidak mewarisi mutation method. Sesi yang dipulihkan remember-me tanpa timestamp sah membutuhkan login kembali.
- Permission dan sesi diperiksa pada request HTTP Livewire sebenarnya; perubahan izin mengakhiri akses lama.
- Editor pengguna mempertahankan nama, username, serta kontak tersembunyi pada serialisasi model; area kosong ditolak.
- Perubahan permission role menghasilkan audit sebelum/sesudah.
- Pickup bernilai tinggi tidak selesai atau menambah saldo sebelum keputusan pemeriksa; penolakan tidak menghasilkan saldo masuk.
- Petugas dapat mencatat kendala setelah berangkat tanpa membentuk setoran.
- Harga draf yang dilanjutkan sama pada ringkasan dan pembukuan.
- Pembatalan dengan objek status lama ditolak setelah persetujuan; hold tetap aktif.
- Formulir pencairan/sembako dapat memulihkan hasil permintaan yang sama setelah saldo tertahan.
- Pencairan berbantuan multi-area memerlukan area valid sebelum bukti persetujuan disimpan.
- Penggabungan tindakan admin tetap atomik dan dapat diulang tanpa audit/transaksi ganda.
- Paket dapat ditandai siap langsung dari disetujui tanpa saldo keluar.
- Filter antrean memakai jadwal yang dikonfirmasi, bukan tanggal permintaan yang sudah diganti.

## Lingkungan dan keselamatan data

Pengujian memakai PHP CLI 8.5.7 dan MySQL Laragon 8.0.30. Database `bank_sampah` hanya diperiksa baca-saja: 48 migrasi tercatat dan tidak ditemukan pickup selesai dengan setoran menunggu persetujuan/ditolak pada pemeriksaan agregat. Data operasional, akun asli, dan konfigurasi .env tidak diubah.

Tes unit/feature memakai database memori dan storage sementara. Tes MySQL/browser memakai database `bank_sampah_test_regression_20261005` dan `bank_sampah_test_workflow_20261005`, bukan database operasional. Server browser hanya mendengarkan loopback lokal dan sudah dihentikan setelah validasi.

Penghapusan otomatis ditolak oleh kebijakan eksekusi. Kedua database sintetis masih tersedia dan dapat dibuang setelah bukti pengujian tidak diperlukan. Salinan sumber sementara dari audit awal juga masih berada di folder TEMP bernama bank-sampah-audit-ab01d05cf4c94357a032f0d93b4313a8. Tidak ada data operasional yang dihapus.

## Batas bukti

Hasil ini tidak membuktikan setiap kemungkinan race condition MySQL, seluruh perangkat/browser warga, kesiapan hosting, atau kelulusan UAT/SUS. UAT dengan pengurus dan warga tetap menjadi langkah berikutnya. Tidak ada klaim kanal Telegram atau WhatsApp terkirim.

Review independen kode menyatakan APPROVE dan review arsitektur menyatakan CLEAR untuk temuan yang diperiksa setelah tindak lanjut. Pengujian dan pemeriksaan visual dilakukan pada pekerjaan lokal ini; tidak ada deployment atau push otomatis.
