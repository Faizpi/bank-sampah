# Keamanan dan integritas

- Empat role tetap, satu akun per role, role/status kanonis dibatasi schema.
- Middleware sesi dan role tetap berlaku pada pembaruan Livewire. Domain memeriksa akses lagi di bawah lock.
- Admin terakhir terlindungi oleh guard dan trigger counter; akses yang dicabut tidak dapat dipakai menyelesaikan tugas.
- Penyerahan rutin hanya oleh claimant aktif searea. Exception Admin terpisah, bukan pihak transaksi, beralasan dan memerlukan autentikasi ulang.
- Koreksi dan penyesuaian resmi memerlukan autentikasi ulang Admin terbaru; upaya sandi dibatasi.
- Mutasi/hold memiliki sumber unik, request dikunci dan retry payload berbeda ditolak.
- Bukti dan ekspor privat; tidak ada tautan storage publik. File divalidasi dan foto diperkecil oleh pemilih bersama.
- Data publik/QR tidak memuat data pribadi atau saldo individu.
- `.env`, database, backup, token, log, dan hasil lokal tidak masuk GitHub.

Kuitansi/audit adalah catatan sistem, bukan bukti independen serah fisik. Tidak adanya foto wajib memperlemah bukti eksternal; pelaksana harus memeriksa nama dan nominal/paket sebelum memberikan manfaat satu kali.
