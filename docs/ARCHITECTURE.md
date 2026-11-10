# Arsitektur implementasi

## Adaptor Xendit Test Mode

Domain `Payouts` menyimpan `SandboxPayout` terpisah dari permintaan tunai, tetapi memakai LedgerService/BalanceHold yang sama di database riset. Livewire dan PayoutTelegramBot memanggil PayoutService yang sama. Worker memakai lease, POST ber-idempotency, serta GET untuk rekonsiliasi. Webhook tanpa sesi browser memvalidasi token, mendeduplikasi event, dan menjadwalkan GET; body callback tidak dapat mengubah saldo langsung. API tidak dipanggil selama transaksi database keuangan. [Kontrak lengkap](PAYOUT_SANDBOX.md).

Laravel 13 dan PHP 8.3+ memakai modular monolith: domain mengatur transaksi, query, scope, dan audit; Livewire/Filament menjadi antarmuka, bukan sumber otorisasi akhir. Database MySQL 8.0.30/InnoDB menjadi mesin operasional. SQLite memori dipakai untuk tes cepat, bukan bukti locking MySQL.

## Identitas

`users.role` satu nilai enum. Capability statis dan middleware role membatasi masing-masing dashboard. `AdminAvailabilityGuard` memakai singleton `access_role_locks`; trigger counter menolak penghilangan Admin aktif terakhir termasuk perubahan SQL langsung. Role/status dibandingkan secara kanonis di database.

## Batas domain

- Identity: akun, profil, area tugas, sesi dan persetujuan ketentuan.
- CustomersRegions: kartu/QR dan hierarki wilayah.
- Deposits: draf, snapshot timbang/harga, final dan mutasi masuk.
- Pickups: pengajuan, claim, perjalanan, pengumpulan, setoran dan pengecualian.
- Withdrawals/Groceries: pengajuan dan akses domain masing-masing.
- Operations: guard tugas dan workflow serah fisik bersama, reset demo yang dibatasi, scheduler/health.
- Ledger: rekening, hold, mutasi append-only dan sumber unik.
- Corrections, Reports, AuditReconciliation: koreksi resmi, laporan berizin dan audit.
- Communication, Notifications, MobileServices, Programs, Statistics: informasi serta program pendukung.
- Telegram: adaptor percakapan/webhook dan outbox; seluruh transaksi tetap memanggil service domain yang sama dengan web.

## Transaksi atomik

Request dikunci sebelum claim/completion. Guard membaca ulang actor, area dan assignment dengan locking read. Completion memeriksa claimant/status, konversi sumber sebelumnya, lalu menulis ledger, hold, kuitansi, dan audit dalam satu transaksi. Payload checksum null konsisten untuk permintaan tanpa foto.

UI Petugas/Bendahara dan adaptor Telegram memakai guard serta service domain bersama dengan ringkasan sebelum pencatatan serah fisik. Webhook Telegram berada di luar sesi web, memverifikasi secret header, menyimpan update yang sudah disaring secara idempoten, dan memproses tombol melalui capability acak, sekali pakai, berumur terbatas. Penautan aktif unik 1:1 dikonfirmasi dari web dan memberikan akses tanpa batas waktu selama tautan serta hak akses masih sah. Server memeriksa ulang kewenangan dan consent; tidak ada pembaruan akses berkala. Payload privat antrean dienkripsi. Bot hanya melayani Warga, Petugas, dan Bendahara; Admin tetap di panel web. Telegram aktif di production melalui konfigurasi environment server; `.env.example` tetap memakai nilai bawaan nonaktif untuk instalasi baru. Detail operasi dan batas aktivasi ada di [TELEGRAM_CHANNEL.md](TELEGRAM_CHANNEL.md).

Penyempurnaan UI 8 Oktober: selected detail/replay terpisah dari pagination/filter; data bukti/laporan otoritatif tidak dapat diganti melalui state klien; akun+tugas atomik; rekonsiliasi dicatat satu Admin dengan input per record. Tabel Filament memakai stacking native di ponsel. Operasi teknis dormant dibatasi per halaman dan pratinjau server/kandidat yang dikunci. [Bukti](IMPLEMENTATION_UI_2026-10-08.md).
