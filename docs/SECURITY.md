# Keamanan dan integritas

## Tambahan sandbox Xendit

- Fitur default nonaktif; hanya Development key dan database riset terkonfirmasi. API URL tetap, redirect HTTP dilarang, dan exception provider disaring tanpa body/header rahasia.
- Webhook memakai `x-callback-token`, limit ukuran, allowlist lima event, reference/provider ID, dan deduplikasi. Body callback tidak bisa mendebit; sistem melakukan GET terautentikasi untuk memeriksa identitas/tujuan/nominal/IDR.
- Tujuan/payload terenkripsi; snapshot, consent dan referensi keuangan immutable. Laporan menyamarkan nomor dan melindungi formula CSV. Semua perubahan saldo memakai sumber unik dan transaksi database; kegagalan ambigu tidak melepas hold.
- Pemulihan eksplisit memakai body/key lama, lease dan jeda satu menit. Status final tidak boleh mundur menjadi pending/gagal hanya karena event terlambat; reversal mencatat mutasi pengembalian baru.
- Gunakan data uji dan pemberitahuan privasi v2.2 beserta consent per pengajuan. [Operasi dan batas](PAYOUT_SANDBOX.md).

- Empat role tetap, satu akun per role, role/status kanonis dibatasi schema.
- Middleware sesi dan role tetap berlaku pada pembaruan Livewire. Domain memeriksa akses lagi di bawah lock.
- Admin terakhir terlindungi oleh guard dan trigger counter; akses yang dicabut tidak dapat dipakai menyelesaikan tugas.
- Penyerahan rutin hanya oleh claimant aktif searea. Exception Admin terpisah, bukan pihak transaksi, beralasan dan memerlukan autentikasi ulang.
- Koreksi dan penyesuaian resmi memerlukan autentikasi ulang Admin terbaru; upaya sandi dibatasi.
- Mutasi/hold memiliki sumber unik, request dikunci dan retry payload berbeda ditolak.
- Bukti dan ekspor privat; tidak ada tautan storage publik. File divalidasi dan foto diperkecil oleh pemilih bersama.
- Data publik/QR tidak memuat data pribadi atau saldo individu.
- Telegram memiliki tautan aktif unik 1:1 yang dikonfirmasi dari web; password tidak diminta di chat. Webhook memakai secret header dan inbox idempoten, tombol bertanda tangan capability acak berumur terbatas, payload privat antrean dienkripsi, dan akses/consent diperiksa kembali saat kirim.
- Akses Telegram tidak kedaluwarsa karena waktu atau tidak aktif; tautan, generation, status akun, data keamanan, peran, area, kepemilikan, claimant, dan status transaksi tetap diperiksa pada server. Perubahan data keamanan dapat memerlukan verifikasi ulang, bukan pembaruan berkala. Batas waktu tombol/isian sementara tidak mengakhiri akses akun.
- Notifikasi Telegram tetap opt-in. Pencabutan membatalkan kiriman tertunda; pesan yang telah dikirim tidak dapat dihapus dari riwayat Telegram.
- Ringkasan bukti publik dan laporan tidak menerima penggantian nilai melalui state klien. Bukti publik diambil ulang dari transaksi pada setiap render; QR yang dirotasi tidak lagi valid pada komponen yang sudah terbuka.
- Penerbitan/perbaikan kartu dilakukan eksplisit dan diaudit; halaman kartu baca-saja.
- `.env`, database, backup, token, log, dan hasil lokal tidak masuk GitHub.

Kuitansi/audit adalah catatan sistem, bukan bukti independen serah fisik. Tidak adanya foto wajib memperlemah bukti eksternal; pelaksana harus memeriksa nama dan nominal/paket sebelum memberikan manfaat satu kali.
