# Aturan bisnis empat role

## Identitas dan akses

- Role tetap: `warga`, `petugas`, `bendahara`, `admin`. `users.role` wajib terisi dan database menolak nilai lainnya.
- Pendaftaran mandiri selalu Warga dan masih memerlukan verifikasi akun oleh Admin. Status akun `menunggu_verifikasi` bukan status pengajuan transaksi.
- Admin membuat akun pengurus; tidak membuat pengajuan atas nama Warga. Perubahan role, area, dan status diaudit.
- Admin terakhir dijaga oleh singleton lock dan counter database. Kredensial/sesi diperiksa ulang; perubahan role membatalkan otorisasi sesi lama.

## Transaksi

- Setoran menghitung harga, kondisi, berat aktual, subtotal, dan pembulatan di server. Semua setoran langsung final; draf belum menambah saldo.
- Final tidak dapat diedit langsung. Koreksi/reversal resmi memerlukan Admin, alasan, autentikasi ulang, dan jejak transaksi asli.
- Pickup diklaim Petugas searea; hanya claimant aktif boleh melanjutkan. Perkiraan/foto bukan dasar saldo.
- Pencairan diklaim Bendahara saat kas fisik tersedia. Pencairan hanya tunai dan tidak sebagian.
- Sembako diklaim Petugas saat paket tersedia. Siap diambil opsional; persiapan bukan tahapan wajib. Tidak tersedia sebelum klaim melepas hold.
- Tidak ada approval Admin rutin, expiry otomatis layanan, modul kas, tombol tunda kas, atau stok rinci.
- Lampiran rutin opsional; unggahan tetap tervalidasi, privat, dan menjadi bagian payload deduplikasi.

## Saldo dan kendala

Saldo tersedia = masuk - keluar - hold aktif. Hold bukan pengeluaran. Completion mengonversi hold dan membuat satu mutasi keluar secara atomik; pembatalan sah melepas hold tanpa pengeluaran.

Pelepasan klaim sebelum serah fisik mengantrekan ulang dan mempertahankan hold. Ketidakpastian serah fisik mengunci request; Admin memastikan fakta sebelum penyelesaian. Admin dapat mengunci klaim saat akses pelaksana dicabut. Nomor, pelaksana fisik, waktu pencatatan, alasan, hasil, dan sumber mutasi unik dipertahankan. Jangan melakukan penyerahan kedua karena respons hilang.

Warga dapat membatalkan pencairan/sembako sebelum penyerahan, kecuali request sedang dikunci untuk pemeriksaan. Pickup dapat dibatalkan Warga sebelum klaim; setelah sampah dijemput, kendala masuk jalur pengecualian.

[Workflow lengkap](WEB_WORKFLOW.md) · [Akses](PERMISSIONS.md)

### Race pembatalan dan serah fisik

Pembatalan pengajuan yang belum diklaim langsung selesai. Setelah klaim, `cancellation_requested_at` mengunci penyerahan; hold dipertahankan hingga pelaksana memastikan belum diserahkan. Jika sudah/tidak pasti, exception Admin memutuskan hasil tanpa penyerahan kedua. Waktu serah yang diketahui terpisah dari waktu pencatatan.
