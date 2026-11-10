# Aturan bisnis empat role

## Identitas dan akses

- Role tetap: `warga`, `petugas`, `bendahara`, `admin`. `users.role` wajib terisi dan database menolak nilai lainnya.
- Pendaftaran mandiri selalu Warga dan masih memerlukan verifikasi akun oleh Admin. Status akun `menunggu_verifikasi` bukan status pengajuan transaksi.
- Admin membuat akun pengurus; tidak membuat pengajuan atas nama Warga. Perubahan role, area, dan status diaudit.
- Akun Petugas/Bendahara dibuat bersama profil dan minimal satu area tugas sah dalam satu transaksi. Jika tugas tidak valid, akun tidak dibuat. Peran baru mengosongkan penugasan lama pada form untuk penetapan ulang eksplisit.
- Admin terakhir dijaga oleh singleton lock dan counter database. Kredensial/sesi diperiksa ulang; perubahan role membatalkan otorisasi sesi lama.

## Transaksi

- Setoran menghitung harga, kondisi, berat aktual, subtotal, dan pembulatan di server. Semua setoran langsung final; draf belum menambah saldo.
- Final tidak dapat diedit langsung. Koreksi/reversal resmi memerlukan Admin, alasan, autentikasi ulang, dan jejak transaksi asli.
- Pickup diklaim Petugas searea; hanya claimant aktif boleh melanjutkan. Perkiraan/foto bukan dasar saldo.
- Pencairan tunai diklaim Bendahara saat kas fisik tersedia dan tidak sebagian. Pencairan digital simulasi merupakan jalur riset terpisah menurut [PAYOUT_SANDBOX.md](PAYOUT_SANDBOX.md).
- Sembako diklaim Petugas saat paket tersedia. Siap diambil opsional; persiapan bukan tahapan wajib. Tidak tersedia sebelum klaim melepas hold.
- Tidak ada approval Admin rutin, expiry otomatis layanan, modul kas, tombol tunda kas, atau stok rinci.
- Lampiran rutin opsional; unggahan tetap tervalidasi, privat, dan menjadi bagian payload deduplikasi.

## Saldo dan kendala

Digital simulasi memakai hold/ledger yang sama pada database riset terpisah, bukan transaksi tunai. Warga mengonfirmasi sendiri dari web/Telegram; tidak ada klaim Bendahara/Admin. API terautentikasi yang cocok dengan identitas, tujuan, nominal, dan mata uang pengajuan menjadi dasar penyelesaian. Webhook hanya memicu pemeriksaan API. Sukses mendebit sekali, gagal final melepas hold, reversal mengkredit melalui mutasi baru. Timeout tetap menahan saldo; operator memulihkan dengan body dan kunci provider yang sama, bukan membuat pengajuan baru. Konfirmasi data uji dicatat sebelum pengiriman. Digital tidak dihitung sebagai pengeluaran kas fisik.

Saldo tersedia = masuk - keluar - hold aktif. Hold bukan pengeluaran. Completion mengonversi hold dan membuat satu mutasi keluar secara atomik; pembatalan sah melepas hold tanpa pengeluaran.

Pelepasan klaim sebelum serah fisik mengantrekan ulang dan mempertahankan hold. Ketidakpastian serah fisik mengunci request; Admin memastikan fakta sebelum penyelesaian. Admin dapat mengunci klaim saat akses pelaksana dicabut. Nomor, pelaksana fisik, waktu pencatatan, alasan, hasil, dan sumber mutasi unik dipertahankan. Jangan melakukan penyerahan kedua karena respons hilang.

Warga dapat membatalkan pencairan/sembako sebelum penyerahan, kecuali request sedang dikunci untuk pemeriksaan. Pickup dapat dibatalkan Warga sebelum klaim; setelah sampah dijemput, kendala masuk jalur pengecualian.

[Workflow lengkap](WEB_WORKFLOW.md) · [Akses](PERMISSIONS.md)

### Race pembatalan dan serah fisik

Pembatalan pengajuan yang belum diklaim langsung selesai. Setelah klaim, `cancellation_requested_at` mengunci penyerahan; hold dipertahankan hingga pelaksana memastikan belum diserahkan. Jika sudah/tidak pasti, exception Admin memutuskan hasil tanpa penyerahan kedua. Waktu serah yang diketahui terpisah dari waktu pencatatan.

## Pemeriksaan saldo dan identitas

Pemeriksaan saldo harian dapat ditutup satu Admin setelah total uang tunai dibayar diisi. Semua pembanding sesuai menghasilkan Selesai; selisih boleh dicatat sebagai Selesai dengan selisih dengan penjelasan minimal 10 karakter. Ini catatan pemeriksaan, bukan izin pembayaran atau koreksi saldo. Catatan yang ditutup tidak diedit; pemeriksaan ulang memakai versi baru.

Membuka kartu nasabah tidak mengubah nomor/QR/tanggal bergabung. Verifikasi akun menerbitkan identitas; kekurangan data dilengkapi melalui aksi Admin yang diaudit. QR sah dipertahankan saat melengkapi data.
