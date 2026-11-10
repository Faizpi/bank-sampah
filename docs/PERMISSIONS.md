# Role dan capability tetap

## Pencairan digital simulasi

Warga aktif mengajukan dan melihat miliknya sendiri dengan hak `withdrawal.request/view`. Bendahara aktif dengan `withdrawal.view` hanya melihat digital pada area tugas aktifnya; Admin aktif dapat melihat keseluruhan lewat Pengawasan. Petugas tidak mempunyai akses digital. Tidak ada permission klaim/bayar digital bagi pengurus. Worker bertindak sebagai sistem, bukan menyamar sebagai Bendahara. [Batas dan konfigurasi](PAYOUT_SANDBOX.md).

Sumber kebenaran: `UserRole`, `RoleCapabilities`, `PermissionChecker`, middleware `RequireRole`, policy, dan guard layanan. Tidak ada editor izin atau pivot role.

| Tindakan | Warga | Petugas | Bendahara | Admin |
| --- | --- | --- | --- | --- |
| Profil dan notifikasi sendiri | Ya | Ya | Ya | Ya |
| Pengajuan dan saldo Warga sendiri | Ya | Tidak | Tidak | Tidak |
| Scan, setoran, pickup, sembako | Tidak | Area aktif | Tidak | Tidak |
| Klaim dan pembayaran tunai | Tidak | Tidak | Area aktif | Tidak |
| Laporan | Riwayat sendiri | Tidak | Pembayaran sendiri | Global |
| Akun, area, master, audit | Tidak | Tidak | Tidak | Ya |
| Koreksi dan penyesuaian resmi | Tidak | Tidak | Tidak | Alasan + autentikasi ulang |
| Pemulihan klaim bermasalah | Tidak | Lapor tugas sendiri | Lapor tugas sendiri | Pengecualian beralasan |
| Pemeriksaan saldo harian | Tidak | Tidak | Tidak | Catat hasil sendiri; tanpa Admin kedua |

Admin tidak memiliki capability membuat/finalisasi setoran, menjalankan pickup, membayar tunai, menyiapkan atau menyerahkan sembako. Akses laporan global bukan bypass aksi fisik. Penugasan memakai `staff_service_areas`, tanggal efektif, dan status area terkini.

Health Admin baca-saja; fasilitas teknis yang tidak didaftarkan di panel tidak dijanjikan sebagai menu aktif.
