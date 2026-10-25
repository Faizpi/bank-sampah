# Keamanan dan integritas

- Empat role tetap, satu akun per role, role/status kanonis dibatasi schema.
- Middleware sesi dan role tetap berlaku pada pembaruan Livewire. Domain memeriksa akses lagi di bawah lock.
- Admin terakhir terlindungi oleh guard dan trigger counter; akses yang dicabut tidak dapat dipakai menyelesaikan tugas.
- Penyerahan rutin hanya oleh claimant aktif searea. Exception Admin terpisah, bukan pihak transaksi, beralasan dan memerlukan autentikasi ulang.
- Koreksi dan penyesuaian resmi memerlukan autentikasi ulang Admin terbaru; upaya sandi dibatasi.
- Mutasi/hold memiliki sumber unik, request dikunci dan retry payload berbeda ditolak.
- Bukti dan ekspor privat; tidak ada tautan storage publik. File divalidasi dan foto diperkecil oleh pemilih bersama.
- Data publik/QR tidak memuat data pribadi atau saldo individu.
- Telegram memiliki tautan aktif unik 1:1 yang dikonfirmasi dari web; password tidak diminta di chat. Webhook memakai secret header dan inbox idempoten, tombol bertanda tangan capability acak berumur terbatas, payload privat antrean dienkripsi, dan akses/consent diperiksa kembali saat kirim.
- Notifikasi Telegram opt-in terpisah dari otorisasi transaksi. Pencabutan membatalkan kiriman tertunda; pesan yang telah dikirim tidak dapat dihapus dari riwayat Telegram.
- Ringkasan bukti publik dan laporan tidak menerima penggantian nilai melalui state klien. Bukti publik diambil ulang dari transaksi pada setiap render; QR yang dirotasi tidak lagi valid pada komponen yang sudah terbuka.
- Penerbitan/perbaikan kartu dilakukan eksplisit dan diaudit; halaman kartu baca-saja.
- `.env`, database, backup, token, log, dan hasil lokal tidak masuk GitHub.

Kuitansi/audit adalah catatan sistem, bukan bukti independen serah fisik. Tidak adanya foto wajib memperlemah bukti eksternal; pelaksana harus memeriksa nama dan nominal/paket sebelum memberikan manfaat satu kali.
