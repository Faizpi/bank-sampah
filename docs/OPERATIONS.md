# Operasional empat role

Gunakan [workflow](WEB_WORKFLOW.md). Petugas menangani setoran/pickup/sembako; Bendahara menangani uang tunai; Admin menangani akun/master/laporan dan kendala khusus. Jangan merangkap akun/peran.

## Klaim dan kendala

Ambil tugas hanya ketika dapat dilayani. Lepas klaim hanya jika serah fisik dipastikan belum terjadi. Hold tetap aktif sampai transaksi dibatalkan atau diselesaikan. Jika tidak pasti, kunci dan minta pemeriksaan Admin; jangan menyerahkan ulang. Saat pelaksana dinonaktifkan atau area dicabut, Admin dapat membekukan klaim dan memulihkan melalui jalur pengecualian.

## Scheduler

`operations:expire` tidak lagi mengakhiri pencairan, sembako, atau pickup otomatis. Retensi ekspor dan pembersihan operasional teknis tetap terpisah dari status layanan. Foto pickup opsional tidak mengubah kebijakan privasi lampiran yang ada.

## Reset demo lokal

`app:reset-demo-baseline --confirm-reset-demo --dry-run` menampilkan sasaran/count tanpa perubahan. Tanpa dry-run command menghapus data dummy dan seed empat akun/master tanpa transaksi. Command menolak production, host non-loopback, database selain tepat `bank_sampah`, flag yang tidak lengkap, dan root media di luar workspace. Simpan backup sebelum menjalankannya.
