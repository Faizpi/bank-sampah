# Arsitektur implementasi

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

## Transaksi atomik

Request dikunci sebelum claim/completion. Guard membaca ulang actor, area dan assignment dengan locking read. Completion memeriksa claimant/status, konversi sumber sebelumnya, lalu menulis ledger, hold, kuitansi, dan audit dalam satu transaksi. Payload checksum null konsisten untuk permintaan tanpa foto.

UI Petugas/Bendahara memakai antrean bersama dengan ringkasan sebelum penyerahan. Panel Admin hanya menjalankan administrasi atau pengecualian yang terpisah. Telegram belum diimplementasikan.
